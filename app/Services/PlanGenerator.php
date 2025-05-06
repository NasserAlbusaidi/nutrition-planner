<?php

namespace App\Services;

use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonInterval; // For easier time formatting

class PlanGenerator
{
    // Configuration Constants
    protected const INTERVAL_MINUTES = 15; // Base scheduling interval
    protected const MAX_FLUID_PER_INTERVAL_ML = 600; // Sensible max fluid intake in 15 mins
    protected const MAX_CARBS_PER_HOUR = 100; // User's max tolerable hourly carb rate (could be user-specific later)
    protected const WATER_PER_GEL_ML = 200; // Recommended water with a gel/chew
    protected const MIN_FLUID_SCHEDULE_ML = 150; // Minimum amount of fluid to schedule at once
    protected const SODIUM_PRIORITY_THRESHOLD = 0.7; // How much of sodium need triggers priority (70%)
    protected const FLUID_PRIORITY_THRESHOLD = 0.6; // How much of fluid need triggers priority (60%)

    /**
     * Generate the nutrition plan schedule.
     *
     * @param User $user
     * @param int $durationSeconds
     * @param array $hourlyTargets // From NutritionCalculator: [['hour'=>int, 'carb_g'=>int, 'fluid_ml'=>int, 'sodium_mg'=>int], ...]
     * @param Collection $pantryProducts // Collection of user's Product models
     * @return array Array of plan item data ready for DB insertion.
     */
    public function generateSchedule(User $user, int $durationSeconds, array $hourlyTargets, Collection $pantryProducts): array
    {
        Log::info("PlanGenerator v2: generateSchedule START", [
            'user_id' => $user->id, 'duration_sec' => $durationSeconds,
            'targets_count' => count($hourlyTargets), 'products_count' => $pantryProducts->count()
        ]);
        if ($pantryProducts->isEmpty()) {
             Log::warning("PlanGenerator v2: Pantry is empty. Cannot generate plan.");
             // Maybe return an error indicator or empty array with a message?
             return [['error' => 'Pantry is empty. Please add products.']]; // Example error structure
        }
        Log::debug("PlanGenerator v2: First pantry product sample.", ['product' => $pantryProducts->first()->toArray()]);

        $schedule = [];
        $intervalSeconds = self::INTERVAL_MINUTES * 60;
        $currentTimeOffset = 0; // Seconds into the activity

        // --- Pre-process and Sort Pantry ---
        $processedPantry = $this->preprocessPantry($pantryProducts);
        // Add a conceptual "Plain Water" product
        $processedPantry->push(new Product([
             'id' => 'WATER', 'name' => 'Plain Water', 'type' => 'water',
             'carbs_g' => 0, 'sodium_mg' => 0, 'serving_volume_ml' => self::MIN_FLUID_SCHEDULE_ML, // Represents a schedulable unit
             'fluid_ml_per_serving' => self::MIN_FLUID_SCHEDULE_ML, // Actual fluid value
             'carbs_per_100ml' => 0, 'sodium_per_100ml' => 0, // Concentrations
             'sort_priority' => 99 // Lowest priority unless specifically needed
        ]));
        // Sort: Prioritize items that meet multiple needs (drinks), then fast carbs, then slower, then water
        $sortedPantry = $processedPantry->sortBy('sort_priority');
        Log::info("PlanGenerator v2: Pantry preprocessed and sorted.", ['count' => $sortedPantry->count()]);

        // --- Initialize Tracking Variables ---
        $cumulativeTargets = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
        $cumulativeConsumed = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
        // Track consumption within rolling windows (e.g., last hour) for rate limiting
        $consumptionHistory = []; // Store [time_offset, carbs, fluid, sodium] for recent items

        Log::info("PlanGenerator v2: Entering main scheduling loop.", ['total_intervals' => ceil($durationSeconds / $intervalSeconds)]);

        // --- Main Scheduling Loop ---
        while ($currentTimeOffset < $durationSeconds) {
            $intervalStartTime = $currentTimeOffset;
            $currentTimeOffset += $intervalSeconds; // Schedule *at* the end of the interval
            Log::info("PlanGenerator v2: LOOP ITERATION START. Time: " . $this->formatTime($currentTimeOffset));

            // Calculate cumulative targets *up to this point*
            $cumulativeTargets = $this->calculateCumulativeTargets($hourlyTargets, $currentTimeOffset);

            // Calculate current deficits
            $needs = [
                'carbs' => max(0, $cumulativeTargets['carbs'] - $cumulativeConsumed['carbs']),
                'fluid' => max(0, $cumulativeTargets['fluid'] - $cumulativeConsumed['fluid']),
                'sodium' => max(0, $cumulativeTargets['sodium'] - $cumulativeConsumed['sodium']),
            ];

            // Calculate consumption rates for the last hour (for capping)
            $recentConsumption = $this->calculateRecentConsumption($consumptionHistory, $currentTimeOffset, 3600);

             Log::debug("PlanGenerator v2: Interval State @ " . $this->formatTime($currentTimeOffset), [
                'cumulative_targets' => array_map('round', $cumulativeTargets, [2]),
                'cumulative_consumed' => array_map('round', $cumulativeConsumed, [2]),
                'current_needs' => array_map('round', $needs, [2]),
                'recent_consumption_1hr' => array_map('round', $recentConsumption, [2])
             ]);

            // --- Product Selection Logic ---
            $productToSchedule = null;
            $calculatedQuantity = 1.0; // Can be fraction or ml
            $calculatedNutrition = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
            $instructionType = 'consume';
            $quantityDescription = '1 serving';
            $notes = '';
            $scheduleWaterAlongside = false;

            // Determine priority need (simplistic relative deficit for now)
            $priorityNeed = $this->determinePriorityNeed($needs, $cumulativeTargets);
            Log::debug("PlanGenerator v2: Priority Need determined: {$priorityNeed}");

            // Iterate through sorted pantry to find the best fit for the current needs & context
            foreach ($sortedPantry as $product) {
                // Basic Check: Can this product potentially help?
                if (($needs['carbs'] > 5 && ($product->carbs_g ?? 0) > 0) || // Need > 5g carbs
                    ($needs['fluid'] > self::MIN_FLUID_SCHEDULE_ML * 0.5 && ($product->fluid_ml_per_serving ?? 0) > 0) || // Need > 75ml fluid
                    ($needs['sodium'] > 20 && ($product->sodium_mg ?? 0) > 0)) // Need > 20mg sodium
                {
                     Log::debug("PlanGenerator v2: Considering Product ID: {$product->id} ({$product->name})");

                    // --- Specific Logic per Product Type ---

                    // 1. Drink Mixes (Prioritize if fluid/sodium need is high)
                    if ($product->type === 'drink_mix' && ($priorityNeed === 'fluid' || $priorityNeed === 'sodium')) {
                        if ($needs['fluid'] >= self::MIN_FLUID_SCHEDULE_ML) {
                             // Calculate volume needed, capped by interval max and actual need
                             $volumeToMakeMl = min(
                                 $needs['fluid'],
                                 self::MAX_FLUID_PER_INTERVAL_ML, // Max per interval
                                 ($this->getMaxFluidPerHour() - $recentConsumption['fluid']) // Remaining hourly allowance
                             );
                             $volumeToMakeMl = max(self::MIN_FLUID_SCHEDULE_ML, $volumeToMakeMl); // Ensure minimum

                             if ($volumeToMakeMl >= self::MIN_FLUID_SCHEDULE_ML) {
                                 // Calculate nutrients from this volume
                                 $calculatedNutrition['fluid'] = $volumeToMakeMl;
                                 $calculatedNutrition['carbs'] = $volumeToMakeMl * ($product->carbs_per_100ml / 100);
                                 $calculatedNutrition['sodium'] = $volumeToMakeMl * ($product->sodium_per_100ml / 100);

                                 // Check Carb Cap
                                 if (($recentConsumption['carbs'] + $calculatedNutrition['carbs']) <= self::MAX_CARBS_PER_HOUR) {
                                     $productToSchedule = $product;
                                     $quantityDescription = round($volumeToMakeMl) . "ml";
                                     $instructionType = 'drink';
                                     // $calculatedQuantity = $volumeToMakeMl / ($product->serving_volume_ml ?: 500); // Optional: calculate servings/scoops
                                     $notes = "Mix {$product->name} and drink {$quantityDescription}";
                                     Log::info("PlanGenerator v2: SELECTED Drink Mix: ID {$product->id}, Vol: {$quantityDescription}");
                                     break; // Found a suitable drink
                                 } else {
                                     Log::debug("PlanGenerator v2: Drink mix skipped (ID: {$product->id}) - Exceeds carb cap.");
                                 }
                             }
                        }
                    }

                    // 2. Gels / Chews (Prioritize if carb need is high)
                    elseif (in_array($product->type, ['gel', 'chews']) && $priorityNeed === 'carbs') {
                         if ($needs['carbs'] > ($product->carbs_g ?? 0) * 0.5) { // Need at least half a gel's worth
                             // Check Carb Cap
                             if (($recentConsumption['carbs'] + ($product->carbs_g ?? 0)) <= self::MAX_CARBS_PER_HOUR) {
                                 $productToSchedule = $product;
                                 $calculatedNutrition['carbs'] = $product->carbs_g ?? 0;
                                 $calculatedNutrition['sodium'] = $product->sodium_mg ?? 0;
                                 $calculatedNutrition['fluid'] = 0; // Gel itself provides negligible fluid
                                 $calculatedQuantity = 1.0;
                                 $quantityDescription = "1 serving";
                                 $instructionType = 'consume';
                                 $notes = "Consume 1 {$product->name}";
                                 // Check if water should be scheduled alongside
                                 if (($needs['fluid'] + $recentConsumption['fluid']) < $this->getMaxFluidPerHour()) {
                                      $scheduleWaterAlongside = true;
                                 }
                                 Log::info("PlanGenerator v2: SELECTED Gel/Chew: ID {$product->id}");
                                 break; // Found suitable gel/chew
                             } else {
                                 Log::debug("PlanGenerator v2: Gel/Chew skipped (ID: {$product->id}) - Exceeds carb cap.");
                             }
                         }
                    }

                    // 3. Bars / Real Food (Lower priority, maybe earlier in activity)
                    elseif (in_array($product->type, ['bar', 'real_food']) && $priorityNeed === 'carbs') {
                        // Simple logic: Use bars only if significant carb need exists and maybe not too late
                        $activityProgress = $currentTimeOffset / $durationSeconds;
                        if ($needs['carbs'] > ($product->carbs_g ?? 0) * 0.7 && $activityProgress < 0.75) { // Need significant carbs, not in the last 25%
                             // Check Carb Cap
                             if (($recentConsumption['carbs'] + ($product->carbs_g ?? 0)) <= self::MAX_CARBS_PER_HOUR) {
                                 $productToSchedule = $product;
                                 $calculatedNutrition['carbs'] = $product->carbs_g ?? 0;
                                 $calculatedNutrition['sodium'] = $product->sodium_mg ?? 0;
                                 $calculatedNutrition['fluid'] = 0;
                                 $calculatedQuantity = 1.0;
                                 $quantityDescription = "1 serving";
                                 $instructionType = 'consume';
                                 $notes = "Consume 1 {$product->name}";
                                 Log::info("PlanGenerator v2: SELECTED Bar/Food: ID {$product->id}");
                                 break; // Found suitable bar/food
                             } else {
                                 Log::debug("PlanGenerator v2: Bar/Food skipped (ID: {$product->id}) - Exceeds carb cap.");
                             }
                        }
                    }

                     // 4. Other/Fallback (e.g., capsules, less common items)
                    elseif (!in_array($product->type, ['drink_mix', 'gel', 'chews', 'bar', 'real_food', 'water'])) {
                          // General check if it meets *any* significant need without exceeding caps
                          if ( ($needs['carbs'] > 5 && ($product->carbs_g ?? 0) > 0 && ($recentConsumption['carbs'] + ($product->carbs_g ?? 0)) <= self::MAX_CARBS_PER_HOUR) ||
                               ($needs['sodium'] > 20 && ($product->sodium_mg ?? 0) > 0 /* No real sodium cap */) )
                          {
                               $productToSchedule = $product;
                               $calculatedNutrition['carbs'] = $product->carbs_g ?? 0;
                               $calculatedNutrition['sodium'] = $product->sodium_mg ?? 0;
                               $calculatedNutrition['fluid'] = 0; // Assume negligible unless specified
                               $calculatedQuantity = 1.0;
                               $quantityDescription = "1 serving";
                               $instructionType = 'consume';
                               $notes = "Consume 1 {$product->name}";
                               Log::info("PlanGenerator v2: SELECTED Other: ID {$product->id}");
                               break; // Found suitable other item
                          }
                    }

                } // End if product could potentially help
            } // End foreach product loop

            // --- Schedule Plain Water if Needed ---
            $waterProduct = $sortedPantry->firstWhere('id', 'WATER'); // Find our conceptual water product
            if ($waterProduct) { // Check if water product exists
                $shouldScheduleWater = false;
                $waterVolume = self::MIN_FLUID_SCHEDULE_ML; // Default amount

                // Reason 1: Water explicitly needed alongside gel/chew
                if ($scheduleWaterAlongside && !$productToSchedule) { // Only schedule if nothing else chosen OR combine logic needed
                    $shouldScheduleWater = true;
                    $waterVolume = self::WATER_PER_GEL_ML;
                    Log::debug("PlanGenerator v2: Scheduling water alongside previously chosen item.");
                }
                // Reason 2: Fluid deficit remains high and no fluid source chosen
                elseif (!$productToSchedule && $priorityNeed === 'fluid' && $needs['fluid'] >= self::MIN_FLUID_SCHEDULE_ML) {
                     $shouldScheduleWater = true;
                     $waterVolume = min($needs['fluid'], self::MAX_FLUID_PER_INTERVAL_ML, ($this->getMaxFluidPerHour() - $recentConsumption['fluid']));
                     $waterVolume = max(self::MIN_FLUID_SCHEDULE_ML, $waterVolume); // Ensure minimum
                     Log::debug("PlanGenerator v2: Scheduling water due to fluid deficit.");
                }

                // Check fluid caps before scheduling water
                if ($shouldScheduleWater && $waterVolume >= self::MIN_FLUID_SCHEDULE_ML) {
                     if (($recentConsumption['fluid'] + $waterVolume) <= $this->getMaxFluidPerHour()) {
                         $productToSchedule = $waterProduct; // Schedule water
                         $calculatedNutrition['fluid'] = $waterVolume;
                         $calculatedNutrition['carbs'] = 0;
                         $calculatedNutrition['sodium'] = 0;
                         $quantityDescription = round($waterVolume) . "ml";
                         $instructionType = 'drink';
                         $notes = "Drink {$quantityDescription} Plain Water";
                         Log::info("PlanGenerator v2: SELECTED Plain Water: Vol: {$quantityDescription}");
                     } else {
                         Log::debug("PlanGenerator v2: Plain Water skipped - Exceeds fluid cap.");
                     }
                }
            } else {
                 Log::warning("PlanGenerator v2: 'WATER' product not found in processed pantry.");
            }


            // --- Add Item to Schedule ---
            if ($productToSchedule && ($calculatedNutrition['carbs'] > 0 || $calculatedNutrition['fluid'] > 0 || $calculatedNutrition['sodium'] > 0)) {
                 $schedule[] = [
                    'plan_id' => null, // Will be set when saving
                    'time_offset_seconds' => $currentTimeOffset,
                    'instruction_type' => $instructionType,
                    'product_id' => $productToSchedule->id === 'WATER' ? null : $productToSchedule->id, // Store null for water ID
                    'product_name_override' => $productToSchedule->id === 'WATER' ? $productToSchedule->name : null, // Store name for water
                    'quantity_description' => $quantityDescription,
                    'calculated_carbs_g' => round($calculatedNutrition['carbs'], 1),
                    'calculated_fluid_ml' => round($calculatedNutrition['fluid']),
                    'calculated_sodium_mg' => round($calculatedNutrition['sodium']),
                    'notes' => $notes,
                ];

                // Update cumulative consumed values
                $cumulativeConsumed['carbs'] += $calculatedNutrition['carbs'];
                $cumulativeConsumed['fluid'] += $calculatedNutrition['fluid'];
                $cumulativeConsumed['sodium'] += $calculatedNutrition['sodium'];

                // Add to consumption history for rate limiting
                 $consumptionHistory[] = [
                     'time' => $currentTimeOffset,
                     'carbs' => $calculatedNutrition['carbs'],
                     'fluid' => $calculatedNutrition['fluid'],
                     'sodium' => $calculatedNutrition['sodium']
                 ];

                Log::info("PlanGenerator v2: ADDED TO SCHEDULE @ " . $this->formatTime($currentTimeOffset) . ": " . $notes);

            } else {
                Log::info("PlanGenerator v2: No product scheduled for interval ending @ " . $this->formatTime($currentTimeOffset));
            }

             Log::info("PlanGenerator v2: LOOP ITERATION END. Time: " . $this->formatTime($currentTimeOffset));

        } // End while loop

        Log::info("PlanGenerator v2: generateSchedule END", ['user' => $user->id, 'item_count' => count($schedule)]);
        return $schedule;
    }

    // --- Helper Methods ---

    /**
     * Prepares pantry items with calculated fields and sort priority.
     */
    protected function preprocessPantry(Collection $pantryProducts): Collection
    {
        return $pantryProducts->map(function (Product $product) {
            // Calculate fluid per serving (often just serving volume, but could differ)
            $product->fluid_ml_per_serving = $product->serving_volume_ml ?? 0;

            // Calculate concentrations (avoid division by zero)
            $servingVol = $product->serving_volume_ml ?: ($product->fluid_ml_per_serving ?: 0);
            $product->carbs_per_100ml = ($servingVol > 0) ? (($product->carbs_g ?? 0) / $servingVol * 100) : 0;
            $product->sodium_per_100ml = ($servingVol > 0) ? (($product->sodium_mg ?? 0) / $servingVol * 100) : 0;

            // Assign sort priority
            $product->sort_priority = match ($product->type) {
                'drink_mix' => 1, // Highest priority as often meets multiple needs
                'gel' => 2,
                'chews' => 3,
                'bar' => 4,
                'real_food' => 5,
                default => 6, // Other/Unknown
            };
            return $product;
        });
    }

    /**
     * Calculates cumulative targets up to a given time offset.
     */
    protected function calculateCumulativeTargets(array $hourlyTargets, int $currentTimeOffset): array
    {
        $totalTargets = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
        $elapsedHours = $currentTimeOffset / 3600.0;
        $fullHours = floor($elapsedHours);
        $partialHourFraction = $elapsedHours - $fullHours;

        for ($h = 0; $h < $fullHours; $h++) {
            $target = $hourlyTargets[$h] ?? end($hourlyTargets); // Use last if needed
            if ($target) {
                $totalTargets['carbs'] += $target['carb_g'] ?? 0;
                $totalTargets['fluid'] += $target['fluid_ml'] ?? 0;
                $totalTargets['sodium'] += $target['sodium_mg'] ?? 0;
            }
        }

        if ($partialHourFraction > 0) {
            $target = $hourlyTargets[$fullHours] ?? end($hourlyTargets); // Target for the current partial hour
            if ($target) {
                $totalTargets['carbs'] += ($target['carb_g'] ?? 0) * $partialHourFraction;
                $totalTargets['fluid'] += ($target['fluid_ml'] ?? 0) * $partialHourFraction;
                $totalTargets['sodium'] += ($target['sodium_mg'] ?? 0) * $partialHourFraction;
            }
        }

        return $totalTargets;
    }

     /**
      * Determines the most critical need based on relative deficit.
      * (This is a simple approach, could be more sophisticated)
      */
     protected function determinePriorityNeed(array $needs, array $cumulativeTargets): string
     {
         $priority = 'none';
         $maxRelativeDeficit = 0;

         // Avoid division by zero if target is 0
         $relativeCarbDeficit = ($cumulativeTargets['carbs'] > 0) ? $needs['carbs'] / $cumulativeTargets['carbs'] : 0;
         $relativeFluidDeficit = ($cumulativeTargets['fluid'] > 0) ? $needs['fluid'] / $cumulativeTargets['fluid'] : 0;
         $relativeSodiumDeficit = ($cumulativeTargets['sodium'] > 0) ? $needs['sodium'] / $cumulativeTargets['sodium'] : 0;

        // Give priority if threshold met, otherwise based on highest relative deficit
        if ($relativeFluidDeficit > self::FLUID_PRIORITY_THRESHOLD && $needs['fluid'] > self::MIN_FLUID_SCHEDULE_ML) {
            return 'fluid';
        }
        if ($relativeSodiumDeficit > self::SODIUM_PRIORITY_THRESHOLD && $needs['sodium'] > 50) { // Need at least 50mg sodium
             return 'sodium';
        }

         if ($relativeCarbDeficit > $maxRelativeDeficit && $needs['carbs'] > 5) {
             $maxRelativeDeficit = $relativeCarbDeficit;
             $priority = 'carbs';
         }
         if ($relativeFluidDeficit > $maxRelativeDeficit && $needs['fluid'] > self::MIN_FLUID_SCHEDULE_ML * 0.5) {
             $maxRelativeDeficit = $relativeFluidDeficit;
             $priority = 'fluid';
         }
         if ($relativeSodiumDeficit > $maxRelativeDeficit && $needs['sodium'] > 20) {
            // Max relative deficit already checked, keep priority if it was higher
            if ($priority === 'none' || $relativeSodiumDeficit > $maxRelativeDeficit){
                 $maxRelativeDeficit = $relativeSodiumDeficit;
                 $priority = 'sodium';
            }
         }


         // Fallback if no specific need stands out significantly but something *is* needed
         if ($priority === 'none') {
             if ($needs['carbs'] > 5) return 'carbs';
             if ($needs['fluid'] > self::MIN_FLUID_SCHEDULE_ML * 0.5) return 'fluid';
             if ($needs['sodium'] > 20) return 'sodium';
         }


         return $priority;
     }

     /**
      * Calculates total consumption in a recent window ending at current time.
      */
     protected function calculateRecentConsumption(array &$consumptionHistory, int $currentTimeOffset, int $windowSeconds): array
     {
         $windowStart = $currentTimeOffset - $windowSeconds;
         $recentTotals = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];

         // Remove old history items & sum recent ones
         $consumptionHistory = array_filter($consumptionHistory, function ($item) use ($windowStart, &$recentTotals) {
             if ($item['time'] > $windowStart) {
                 $recentTotals['carbs'] += $item['carbs'];
                 $recentTotals['fluid'] += $item['fluid'];
                 $recentTotals['sodium'] += $item['sodium'];
                 return true; // Keep item
             }
             return false; // Discard item
         });
          // Ensure array keys are reset if needed after filtering (though not strictly necessary here)
         // $consumptionHistory = array_values($consumptionHistory);

         return $recentTotals;
     }

     /**
      * Simple helper to get max fluid/hr (could be user-specific later).
      */
     protected function getMaxFluidPerHour(): int
     {
         // Could read from user profile, e.g., based on sweat rate estimates
         return 1000; // Default 1L/hour
     }

     /**
      * Format seconds into HH:MM:SS for logging.
      */
     protected function formatTime(int $seconds): string
     {
          if ($seconds < 0) {
              return '00:00:00';
          }
          return CarbonInterval::seconds($seconds)->cascade()->format('%H:%I:%S');
     }
}
