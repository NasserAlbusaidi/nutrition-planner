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
    protected const INTERVAL_MINUTES = 15;
    protected const MAX_FLUID_PER_INTERVAL_ML = 350; // Reduced from 600; 250-350ml in 15min is more realistic
    protected const MAX_CARBS_PER_HOUR = 100; // User's max tolerable hourly carb rate
    protected const WATER_PER_SOLID_ML = 200; // Recommended water with a gel/chew/bar
    protected const MIN_FLUID_SCHEDULE_ML = 150;
    protected const SODIUM_PRIORITY_THRESHOLD = 0.7;
    protected const FLUID_PRIORITY_THRESHOLD = 0.6;
    protected const MIN_CARBS_TO_SCHEDULE_G = 10; // Don't bother scheduling if carb need is less than this
    protected const MAX_ITEMS_PER_INTERVAL = 2; // E.g., a drink and a gel, or two small items

    /**
     * Generate the nutrition plan schedule.
     *
     * @param User $user
     * @param int $durationSeconds
     * @param array $hourlyTargets // From NutritionCalculator: [['hour'=>int, 'carb_g'=>int, 'fluid_ml'=>int, 'sodium_mg'=>int], ...]
     * @param Collection $pantryProducts // Collection of user's Product models
     * @return array Array of plan item data ready for DB insertion or an error structure.
     */
    public function generateSchedule(User $user, int $durationSeconds, array $hourlyTargets, Collection $pantryProducts): array
    {
        Log::info("PlanGenerator v2 - Refactored: generateSchedule START", [
            'user_id' => $user->id, 'duration_sec' => $durationSeconds,
            'targets_count' => count($hourlyTargets), 'products_count' => $pantryProducts->count()
        ]);

        if ($durationSeconds <= 0 || empty($hourlyTargets)) {
            Log::warning("PlanGenerator Refactor: Invalid duration or targets provided.", ['duration' => $durationSeconds, 'targets_count' => count($hourlyTargets)]);
            return [['error' => 'Invalid activity duration or nutrition targets for plan generation.']];
        }
        if ($pantryProducts->isEmpty()) {
             Log::warning("PlanGenerator Refactor: Pantry is empty.");
             return [['error' => 'Pantry is empty. Please add products.']];
        }

        $schedule = [];
        $intervalSeconds = self::INTERVAL_MINUTES * 60;
        $currentTimeOffset = 0; // Represents the END time of the interval being considered

        // Pre-process pantry (assuming helper methods exist and work as discussed)
        $processedPantry = $this->preprocessPantry($pantryProducts);
        $processedPantry->push($this->getWaterProduct());
        $sortedPantry = $processedPantry->sortBy('sort_priority');
        Log::info("PlanGenerator Refactor: Pantry preprocessed and sorted.", ['count' => $sortedPantry->count()]);

        // Initialize Tracking Variables
        $cumulativeConsumed = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
        $consumptionHistory = []; // Stores [time_offset_end_interval, carbs, fluid, sodium]

        // --- Main Loop (Iterating through intervals of the activity) ---
        Log::info("PlanGenerator Refactor: Entering main scheduling loop.", ['total_intervals' => ceil($durationSeconds / $intervalSeconds)]);

        while ($currentTimeOffset < $durationSeconds) {
            $currentTimeOffset += $intervalSeconds; // Time at the END of the current interval
            Log::info("PlanGenerator Refactor: ==== INTERVAL START ==== Considering interval ending @ " . $this->formatTime($currentTimeOffset));

            $cumulativeTargets = $this->calculateCumulativeTargets($hourlyTargets, $currentTimeOffset);
            Log::debug("PlanGenerator Refactor: Cumulative Targets @ end of interval:", array_map(fn($n) => round($n,1), $cumulativeTargets));
            Log::debug("PlanGenerator Refactor: Cumulative Consumed before this interval:", array_map(fn($n) => round($n,1), $cumulativeConsumed));


            $itemsScheduledThisIntervalCount = 0;
            $nutrientsAddedThisInterval = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0]; // Track additions in this specific interval

            // INNER LOOP: Try to schedule up to MAX_ITEMS_PER_INTERVAL
            while ($itemsScheduledThisIntervalCount < self::MAX_ITEMS_PER_INTERVAL) {

                // Calculate current needs *before* selecting next item for this interval
                $needsForThisPass = [
                    'carbs' => max(0, $cumulativeTargets['carbs'] - ($cumulativeConsumed['carbs'] + $nutrientsAddedThisInterval['carbs'])),
                    'fluid' => max(0, $cumulativeTargets['fluid'] - ($cumulativeConsumed['fluid'] + $nutrientsAddedThisInterval['fluid'])),
                    'sodium' => max(0, $cumulativeTargets['sodium'] - ($cumulativeConsumed['sodium'] + $nutrientsAddedThisInterval['sodium'])),
                ];

                // If all significant needs met, exit inner loop for this interval
                if ($needsForThisPass['carbs'] < self::MIN_CARBS_TO_SCHEDULE_G &&
                    $needsForThisPass['fluid'] < self::MIN_FLUID_SCHEDULE_ML &&
                    $needsForThisPass['sodium'] < 50) {
                    Log::debug("PlanGenerator Refactor: Inner loop - Needs minimal. Ending pass for this interval.", $needsForThisPass);
                    break; // Exit inner loop
                }

                // Get recent consumption *up to the end of the PREVIOUS interval* + what's *already added this interval* for cap checking
                $effectiveRecentConsumption = $this->calculateRecentConsumption($consumptionHistory, $currentTimeOffset, 3600); // Returns total in last hour WINDOW
                // Add nutrients already added *this interval* to check caps for the *next* item
                $consumptionForCapCheck = [
                    'carbs' => $effectiveRecentConsumption['carbs'] + $nutrientsAddedThisInterval['carbs'],
                    'fluid' => $effectiveRecentConsumption['fluid'] + $nutrientsAddedThisInterval['fluid'],
                ];

                 Log::debug("PlanGenerator Refactor: Inner loop - Current Pass Needs:", array_map(fn($n)=>round($n,1), $needsForThisPass));
                 Log::debug("PlanGenerator Refactor: Inner loop - Consumption for Cap Check (Rolling Hour + This Interval):", array_map(fn($n)=>round($n,1), $consumptionForCapCheck));


                $priorityNeed = $this->determinePriorityNeed($needsForThisPass, $cumulativeTargets, $cumulativeConsumed); // Determine priority based on current pass needs
                Log::debug("PlanGenerator Refactor: Inner loop - Priority Need: {$priorityNeed}");


                $bestProductChoiceThisPass = null;
                $bestProductNutritionThisPass = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
                $bestProductNotesThisPass = '';
                $bestProductQtyDescThisPass = '';
                $bestProductInstructionThisPass = 'consume';
                $foundSuitableProductThisPass = false;

                // --- Iterate through sorted pantry ---
                foreach ($sortedPantry as $product) {
                    if ($product->id === 'WATER') continue; // Handle water separately

                    $itemCarbs = 0; $itemFluid = 0; $itemSodium = 0;
                    $canUseProduct = false; // Reset for each product

                    // Try to schedule Drink Mixes if a priority or needed
                    if ($product->type === Product::TYPE_DRINK_MIX) {
                        $pCarbsPerStd = $product->carbs_g ?? 0;
                        $pSodiumPerStd = $product->sodium_mg ?? 0;
                        $pStdVolMl = $product->final_drink_volume_per_serving_ml; // Water needed for standard mix

                        if ($pStdVolMl > 0 && $needsForThisPass['fluid'] >= self::MIN_FLUID_SCHEDULE_ML * 0.75) {
                            $volToConsume = floor(min(
                                $needsForThisPass['fluid'], // Need for this pass
                                self::MAX_FLUID_PER_INTERVAL_ML - $nutrientsAddedThisInterval['fluid'], // Remaining allowance in interval
                                ($this->getMaxFluidPerHour() - $consumptionForCapCheck['fluid']) // Remaining allowance in hourly window
                            ));
                            $volToConsume = max(self::MIN_FLUID_SCHEDULE_ML, $volToConsume);

                            if ($volToConsume >= self::MIN_FLUID_SCHEDULE_ML) {
                                $proportion = $volToConsume / $pStdVolMl;
                                $itemCarbs = $pCarbsPerStd * $proportion;
                                $itemSodium = $pSodiumPerStd * $proportion;
                                $itemFluid = $volToConsume;

                                if (($consumptionForCapCheck['carbs'] + $itemCarbs) <= $this->getMaxCarbsPerHour($user)) {
                                     Log::info("PlanGenerator Refactor: -> Evaluating Drink Mix OK: ID {$product->id}, Vol {$volToConsume}ml");
                                     $canUseProduct = true;
                                } else { Log::debug("Drink Mix {$product->name} rejected: carb cap."); }
                            }
                        }
                    }
                    // Try to schedule Gels/Chews if priority or needed
                    elseif (in_array($product->type, [Product::TYPE_GEL, Product::TYPE_ENERGY_CHEW]) && $needsForThisPass['carbs'] >= self::MIN_CARBS_TO_SCHEDULE_G) {
                         $itemCarbs = $product->carbs_g ?? 0;
                         $itemSodium = $product->sodium_mg ?? 0;
                         $itemFluid = 0; // Assume negligible

                         if (($consumptionForCapCheck['carbs'] + $itemCarbs) <= $this->getMaxCarbsPerHour($user)) {
                              Log::info("PlanGenerator Refactor: -> Evaluating Gel/Chew OK: ID {$product->id}");
                              $canUseProduct = true;
                         } else { Log::debug("Gel/Chew {$product->name} rejected: carb cap."); }
                    }
                    // Try Bars/Real Food if priority or needed
                    elseif (in_array($product->type, [Product::TYPE_ENERGY_BAR, Product::TYPE_REAL_FOOD]) && $needsForThisPass['carbs'] >= self::MIN_CARBS_TO_SCHEDULE_G) {
                         // Maybe add stricter conditions, e.g., only early in the race, higher need threshold?
                        $itemCarbs = $product->carbs_g ?? 0;
                        $itemSodium = $product->sodium_mg ?? 0;
                        $itemFluid = 0;

                        if (($consumptionForCapCheck['carbs'] + $itemCarbs) <= $this->getMaxCarbsPerHour($user)) {
                             Log::info("PlanGenerator Refactor: -> Evaluating Bar/Food OK: ID {$product->id}");
                             $canUseProduct = true;
                        } else { Log::debug("Bar/Food {$product->name} rejected: carb cap."); }
                    }
                    // Add Hydration Tablets logic if needed (primarily for sodium/fluid)
                    elseif ($product->type === Product::TYPE_HYDRATION_TABLET && $needsForThisPass['sodium'] > 50){
                        // Needs careful calculation - tablet adds sodium, but assumes fluid intake comes from the water it's dissolved in
                        // You might schedule the tablet and trigger scheduling water alongside
                    }

                    // If this product is deemed suitable...
                    if ($canUseProduct) {
                         // Simple selection: Pick the first suitable product found in this pass.
                         // More advanced: Calculate a score based on priorityNeed, nutrient fulfillment, etc.
                         $bestProductChoiceThisPass = $product;
                         $bestProductNutritionThisPass = ['carbs' => $itemCarbs, 'fluid' => $itemFluid, 'sodium' => $itemSodium];

                         if ($product->type === Product::TYPE_DRINK_MIX) {
                             $bestProductQtyDescThisPass = round($itemFluid) . "ml";
                             $bestProductInstructionThisPass = 'drink';
                             $proportion = $itemFluid / ($product->final_drink_volume_per_serving_ml ?: 1); // Avoid division by zero
                             $unitsConsumed = round($proportion / ($product->units_per_serving ?? 1), 1); // Assume units_per_serving if exists
                             $bestProductNotesThisPass = "Prepare {$product->name} as directed (e.g. {$product->serving_size_description}) and drink {$bestProductQtyDescThisPass}.";
                         } else {
                             $bestProductQtyDescThisPass = $product->serving_size_description ?? "1 serving";
                             $bestProductInstructionThisPass = 'consume';
                             $bestProductNotesThisPass = "Consume {$bestProductQtyDescThisPass} of {$product->name}";
                         }
                         Log::info("PlanGenerator Refactor: INNER PASS FOUND SUITABLE PRODUCT", ['id' => $product->id, 'name' => $product->name]);
                         break; // Found the best item for this pass, break pantry loop
                    }
                } // --- End foreach $sortedPantry loop for this pass ---

                // --- Add the selected item (if any) for this pass ---
                if ($bestProductChoiceThisPass) {
                    $isWater = ($bestProductChoiceThisPass->id === 'WATER');
                    $schedule[] = [
                        'time_offset_seconds' => $currentTimeOffset,
                        'instruction_type' => $bestProductInstructionThisPass,
                        'product_id' => $isWater ? null : $bestProductChoiceThisPass->id,
                        'product_name_override' => $isWater ? $bestProductChoiceThisPass->name : null,
                        'product_name' => $bestProductChoiceThisPass->name,
                        'quantity_description' => $bestProductQtyDescThisPass,
                        'calculated_carbs_g' => round($bestProductNutritionThisPass['carbs'], 1),
                        'calculated_fluid_ml' => round($bestProductNutritionThisPass['fluid']),
                        'calculated_sodium_mg' => round($bestProductNutritionThisPass['sodium']),
                        'notes' => $bestProductNotesThisPass,
                    ];
                    $nutrientsAddedThisInterval['carbs'] += $bestProductNutritionThisPass['carbs'];
                    $nutrientsAddedThisInterval['fluid'] += $bestProductNutritionThisPass['fluid'];
                    $nutrientsAddedThisInterval['sodium'] += $bestProductNutritionThisPass['sodium'];
                    $itemsScheduledThisIntervalCount++;
                    Log::info("PlanGenerator Refactor: INNER PASS - ADDED ITEM to Schedule @ " . $this->formatTime($currentTimeOffset) . ": " . $bestProductNotesThisPass);

                    // Decide if water should *still* be scheduled alongside solids just added
                    if (!$isWater && $bestProductNutritionThisPass['fluid'] == 0 && $itemsScheduledThisIntervalCount < self::MAX_ITEMS_PER_INTERVAL) {
                        $waterVolumeNeeded = self::WATER_PER_SOLID_ML;
                        $currentFluidNeed = max(0, $cumulativeTargets['fluid'] - ($cumulativeConsumed['fluid'] + $nutrientsAddedThisInterval['fluid']));
                        $hourlyFluidRemaining = $this->getMaxFluidPerHour() - ($consumptionForCapCheck['fluid'] + $bestProductNutritionThisPass['fluid']); // Update cap check consumption
                        $waterToAdd = floor(min($waterVolumeNeeded, $currentFluidNeed, $hourlyFluidRemaining));

                        if ($waterToAdd > self::MIN_FLUID_SCHEDULE_ML * 0.5) { // Add if at least half min amount
                            $waterProduct = $sortedPantry->firstWhere('id', 'WATER');
                            if ($waterProduct) {
                                Log::info("PlanGenerator Refactor: Adding water alongside solid.", ['volume' => $waterToAdd]);
                                // Schedule water immediately as the second item (breaks MAX_ITEMS_PER_INTERVAL rule here for simplicity)
                                 $schedule[] = [ /* ... Water item details ... */ 'calculated_fluid_ml' => $waterToAdd ];
                                 $nutrientsAddedThisInterval['fluid'] += $waterToAdd;
                                 // IMPORTANT: Update $consumptionHistory immediately AFTER adding water
                                 $consumptionHistory[] = ['time' => $currentTimeOffset, 'carbs' => 0, 'fluid' => $waterToAdd, 'sodium' => 0 ];
                                 // Since water was just added, effectively break the inner loop? Or increment count?
                                 $itemsScheduledThisIntervalCount++; // Counts as an item for the interval
                            }
                        }
                    }

                } else {
                    Log::info("PlanGenerator Refactor: Inner pass - No suitable product found. Ending pass for interval.");
                    break; // Exit inner while loop if no product found in this pass
                }

            } // --- End while (inner loop for max items per interval) ---


            // --- Final Updates for the Interval ---
            $cumulativeConsumed['carbs'] += $nutrientsAddedThisInterval['carbs'];
            $cumulativeConsumed['fluid'] += $nutrientsAddedThisInterval['fluid'];
            $cumulativeConsumed['sodium'] += $nutrientsAddedThisInterval['sodium'];

            // Update consumption history using the nutrients *actually added* this interval
             if ($nutrientsAddedThisInterval['carbs'] > 0 || $nutrientsAddedThisInterval['fluid'] > 0 || $nutrientsAddedThisInterval['sodium'] > 0) {
                $consumptionHistory[] = [
                    'time' => $currentTimeOffset, // Time at the END of the interval
                    'carbs' => $nutrientsAddedThisInterval['carbs'],
                    'fluid' => $nutrientsAddedThisInterval['fluid'],
                    'sodium' => $nutrientsAddedThisInterval['sodium']
                ];
             }

            Log::info("PlanGenerator Refactor: ==== INTERVAL END ==== Time: " . $this->formatTime($currentTimeOffset) . ". Cumulatives:", array_map(fn($n)=>round($n,1), $cumulativeConsumed));

        } // --- End while ($currentTimeOffset < $durationSeconds) [Main Loop] ---

        Log::info("PlanGenerator Refactor: generateSchedule END", ['user' => $user->id, 'item_count' => count($schedule)]);
        return $schedule;
    }

    // --- Helper Methods ---

    /**
     * Get the maximum carbs per hour (could be user-specific later).
     */
    protected function getMaxCarbsPerHour(User $user = null): int // User might influence this later
    {
        // Could be a user profile setting "max_tolerable_carbs_per_hour"
        return self::MAX_CARBS_PER_HOUR; // Using the class constant
    }

    /**
     * Prepares pantry items with calculated fields and sort priority.
     */
    protected function preprocessPantry(Collection $pantryProducts): Collection
{
    return $pantryProducts->map(function (Product $product) {
        // 'fluid_ml_per_serving' is the fluid THIS PRODUCT *PROVIDES* in one described serving.
        // For a gel/bar/food, this is usually 0 or very low.
        // For a drink mix *powder*, its inherent fluid is 0. The fluid comes from water it's mixed with.
        // For a ready-to-drink product, this would be its volume.
        // Your $product->serving_size_ml is the key: for drink mixes, it's the water used for 1 serving of powder.
        $product->final_drink_volume_per_serving_ml = $product->serving_size_ml ?? 0; // Water to mix one serving for DRINK MIXES
        $product->provided_fluid_per_serving_ml = 0; // Default for solids/powders

        if ($product->type === Product::TYPE_DRINK_MIX) {
            $product->provided_fluid_per_serving_ml = $product->final_drink_volume_per_serving_ml; // If you consume 1 serving mixed, you get this much fluid.
        } elseif (in_array($product->type, [Product::TYPE_PLAIN_WATER])) {
            $product->provided_fluid_per_serving_ml = $product->serving_size_ml ?? 0;
        }
        // If you had a "Ready To Drink" type, it would be $product->serving_size_ml for its volume

        // Sort priority
        $product->sort_priority = match ($product->type) {
            Product::TYPE_DRINK_MIX => 1,
            Product::TYPE_HYDRATION_TABLET => 1, // Similar priority if primary need is electrolytes/fluid
            Product::TYPE_GEL => 2,
            Product::TYPE_ENERGY_CHEW => 3,
            Product::TYPE_ENERGY_BAR => 4,
            Product::TYPE_REAL_FOOD => 5,
            Product::TYPE_PLAIN_WATER => 10, // Lower, but available
            default => 6,
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
     * Get a conceptual "Plain Water" product.
     */

     protected function getWaterProduct(): Product
    {
        return new Product([
             'id' => 'WATER', 'name' => 'Plain Water', 'type' => Product::TYPE_PLAIN_WATER, // Use constant
             'carbs_g' => 0, 'sodium_mg' => 0,
             'serving_size_ml' => self::MIN_FLUID_SCHEDULE_ML, // This is the 'unit' of water
             'final_drink_volume_per_serving_ml' => self::MIN_FLUID_SCHEDULE_ML, // Water is ready-to-drink
             'provided_fluid_per_serving_ml' => self::MIN_FLUID_SCHEDULE_ML,
             'sort_priority' => 100 // Lowest priority
        ]);
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
