<?php

namespace App\Services;

use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonInterval;
use Illuminate\Support\Str;

class PlanGenerator
{
    // --- Core Configuration ---
    protected const INTERVAL_MINUTES = 15;
    protected const MAX_ITEMS_PER_INTERVAL = 2; // Max distinct items (e.g., drink + gel) per interval

    // --- Nutrient Cap Configuration (Per Hour unless specified) ---
    protected const MAX_CARBS_PER_HOUR = 100;     // User's general max tolerable hourly carb rate
    protected const MAX_FLUID_PER_HOUR_ML = 1000;   // General max tolerable hourly fluid rate
    protected const MAX_FLUID_PER_INTERVAL_ML = 350; // Max fluid from a single item or in one go within an interval
    protected const MAX_SODIUM_PER_HOUR_MG = 1000; // User's general max tolerable hourly sodium rate

    // --- Scheduling Heuristics ---
    protected const MIN_FLUID_TO_SCHEDULE_ML = 100; // Minimum amount of fluid to bother scheduling
    protected const MIN_CARBS_TO_SCHEDULE_G = 5;    // Minimum carb need to trigger looking for a carb source
    protected const MIN_SODIUM_TO_SCHEDULE_MG = 50; // Minimum sodium need to trigger looking for a sodium source

    protected const WATER_WITH_SOLID_ML = 200;      // Water suggested with non-liquid items
    protected const SELECTION_MODE_CARB_CAP_MULTIPLIER = 1.20; // Allow 20% over hourly carb cap for user selected items
    protected const SELECTION_MODE_FLUID_CAP_MULTIPLIER = 1.15; // Allow 15% over hourly fluid cap for user selected items

    // --- Priority Calculation Thresholds (as a fraction of total target deficit) ---
    protected const FLUID_PRIORITY_DEFICIT_THRESHOLD = 0.3;  // If fluid deficit is >30% of total fluid target
    protected const SODIUM_PRIORITY_DEFICIT_THRESHOLD = 0.4; // If sodium deficit is >40% of total sodium target
    protected const CARB_PRIORITY_DEFICIT_THRESHOLD = 0.2;   // If carb deficit is >20% of total carb target

    // --- Minimum Need Constants ---
    protected const MIN_CARB_NEED_G = 30; // Minimum carb need to consider scheduling
    protected const MIN_FLUID_NEED_ML = 150; // Minimum fluid need to consider scheduling
    protected const MIN_SODIUM_NEED_MG = 20; // Minimum sodium need to consider scheduling

    // --- State Variables (Internal during generation) ---
    protected array $consumptionHistory = [];
    protected array $cumulativeConsumed = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
    protected array $currentCumulativeTargets = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0]; // Track current target
    protected User $currentUser;




    /**
     * Main entry point for AUTOMATIC plan generation using the full pantry.
     */
    public function generateSchedule(User $user, int $durationSeconds, array $hourlyTargets, Collection $pantryProducts): array
    {
        $this->initializeGeneratorState($user);
        Log::info("PlanGenerator (Automatic): START", $this->getCommonLogContext($durationSeconds, $hourlyTargets, $pantryProducts->count()));

        if (!$this->validateInputs($durationSeconds, $hourlyTargets)) {
            return [['error' => 'Invalid duration or targets.']];
        }
        if ($pantryProducts->isEmpty()) {
            return [['error' => 'Pantry is empty.']];
        }

        $availableProducts = $this->preprocessProductList($pantryProducts, true); // Add water, sort

        return $this->runSchedulingLoop($durationSeconds, $hourlyTargets, $availableProducts, false);
    }

    /**
     * Main entry point for USER SELECTION based plan generation.
     */
    public function generateScheduleFromSelection(User $user, int $durationSeconds, array $hourlyTargets, Collection $userCarryListWithQuantities): array
    {
        $this->initializeGeneratorState($user);
        $productCount = $userCarryListWithQuantities->sum('quantity');
        Log::info("PlanGenerator (Selection): START", $this->getCommonLogContext($durationSeconds, $hourlyTargets, $productCount));

        if (!$this->validateInputs($durationSeconds, $hourlyTargets)) {
            return $this->formatSelectionResult([], ['Invalid duration or targets.'], []);
        }
        if ($userCarryListWithQuantities->isEmpty()) {
            return $this->formatSelectionResult([], ['No items selected by user.'], []);
        }

        // Prepare products from carry list, add water if not present
        $carryProducts = $userCarryListWithQuantities->pluck('product');
        $availableProducts = $this->preprocessProductList($carryProducts, true)->keyBy('id');

        // Track remaining quantities of user's items
        $remainingQuantities = $userCarryListWithQuantities->mapWithKeys(fn($item, $productId) => [$productId => $item['quantity']])->toArray();
        if (!$userCarryListWithQuantities->has('WATER') && $availableProducts->has('WATER')) {
            $remainingQuantities['WATER'] = 999; // Assume abundant water
        }

        $schedule = $this->runSchedulingLoop($durationSeconds, $hourlyTargets, $availableProducts, true, $remainingQuantities);

        // After loop, assess deficits and format leftovers
        $finalRecommendedTotals = $this->getRecommendedTotalsForDuration($hourlyTargets, $durationSeconds);
        $warnings = $this->generateDeficitWarnings($this->cumulativeConsumed, $finalRecommendedTotals, true);
        $leftovers = $this->formatLeftoverItems($remainingQuantities, $availableProducts);

        // Add warning if average hourly carb intake seems too high for user selected items
        $avgHourlyCarbsConsumed = $this->calculateAverageHourlyConsumption($this->cumulativeConsumed, $durationSeconds)['carbs'];
        $maxCarbs = $this->getMaxCarbsPerHour($this->currentUser);
        if ($avgHourlyCarbsConsumed > $maxCarbs * self::SELECTION_MODE_CARB_CAP_MULTIPLIER * 0.9) { // Warn if close to lenient cap
            $warnings[] = "Your selection averages " . round($avgHourlyCarbsConsumed) . "g carbs/hr, which is high. Monitor tolerance (user cap: {$maxCarbs}g/hr).";
        }


        Log::info("PlanGenerator (Selection): END", ['items_scheduled' => count($schedule), 'warnings' => count($warnings), 'leftovers' => count($leftovers)]);
        return $this->formatSelectionResult($schedule, $warnings, $leftovers, $this->cumulativeConsumed, $finalRecommendedTotals);
    }


    // --- Core Scheduling Logic ---

    protected function initializeGeneratorState(User $user): void
    {
         $this->currentUser = $user;
        $this->consumptionHistory = [];
        $this->cumulativeConsumed = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
        $this->currentCumulativeTargets = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
    }

    protected function validateInputs(int $durationSeconds, array $hourlyTargets): bool
    {
        if ($durationSeconds <= 0) {
            Log::warning("PlanGenerator: Invalid duration.", ['duration' => $durationSeconds]);
            return false;
        }
        if (empty($hourlyTargets)) {
            Log::warning("PlanGenerator: Hourly targets are empty.");
            return false;
        }
        // Ensure $hourlyTargets is numerically indexed from 0
        if (count($hourlyTargets) > 0 && !isset($hourlyTargets[0])) {
            Log::error("PlanGenerator: Hourly targets are not 0-indexed.", ['keys' => array_keys($hourlyTargets)]);
            // Consider re-indexing here or failing. For now, fail.
            return false;
        }
        return true;
    }

    protected function getCommonLogContext(int $durationSeconds, array $hourlyTargets, int $productCount): array
    {
        return [
            'user_id' => $this->currentUser->id,
            'duration_sec' => $durationSeconds,
            'targets_count' => count($hourlyTargets),
            'product_count' => $productCount,
        ];
    }

    /**
     * The main loop that iterates through time intervals and schedules items.
     */
    protected function runSchedulingLoop(int $durationSeconds, array $hourlyTargets, Collection $availableProducts, bool $isSelectionMode, array &$remainingQuantities = []): array
    {
        $schedule = [];
        $intervalSeconds = self::INTERVAL_MINUTES * 60;
        $currentTimeOffset = 0;
        $finalRecommendedTotals = $this->getRecommendedTotalsForDuration($hourlyTargets, $durationSeconds); // Needed for final cap checks

        while ($currentTimeOffset < $durationSeconds) {
            $currentTimeOffset += $intervalSeconds;
            Log::info("PlanGenerator: ==== INTERVAL START @ {$this->formatTime($currentTimeOffset)} ====", ['selection_mode' => $isSelectionMode]);

            $this->currentCumulativeTargets = $this->calculateCumulativeTargets($hourlyTargets, $currentTimeOffset); // Update cumulative target
            $intervalTargets = $this->_calculateIntervalTargets($hourlyTargets, $currentTimeOffset, $intervalSeconds); // Target for THIS 15min block
            Log::debug("PlanGenerator: Targets", [
                'cumulative' => $this->currentCumulativeTargets,
                'interval' => $intervalTargets,
                'current_consumption' => $this->cumulativeConsumed
            ]);

            $nutrientsAddedThisInterval = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
            $itemsScheduledThisIntervalCount = 0;

            // Inner loop: Try to schedule items for this interval
            while ($itemsScheduledThisIntervalCount < self::MAX_ITEMS_PER_INTERVAL) {

                // Needs *within* this specific 15min interval
                $needsWithinInterval = [
                    'carbs' => max(0, $intervalTargets['carbs'] - $nutrientsAddedThisInterval['carbs']),
                    'fluid' => max(0, $intervalTargets['fluid'] - $nutrientsAddedThisInterval['fluid']),
                    'sodium' => max(0, $intervalTargets['sodium'] - $nutrientsAddedThisInterval['sodium']),
                ];

                // Overall cumulative deficit/surplus (negative means surplus)
                $cumulativeDeficit = [
                    'carbs' => $this->currentCumulativeTargets['carbs'] - ($this->cumulativeConsumed['carbs'] + $nutrientsAddedThisInterval['carbs']),
                    'fluid' => $this->currentCumulativeTargets['fluid'] - ($this->cumulativeConsumed['fluid'] + $nutrientsAddedThisInterval['fluid']),
                    'sodium' => $this->currentCumulativeTargets['sodium'] - ($this->cumulativeConsumed['sodium'] + $nutrientsAddedThisInterval['sodium']),
                ];

                if ($this->_shouldStopSchedulingForInterval($needsWithinInterval, $cumulativeDeficit)) {
                    Log::debug("PlanGenerator: Stopping scheduling for interval.", ['interval_needs' => $needsWithinInterval, 'cumulative_deficit' => $cumulativeDeficit]);
                    break; // No more significant needs for this interval or cumulatively ahead
                }

                $recentConsumptionWindow = $this->getRecentConsumption($this->consumptionHistory, $currentTimeOffset, 3600);
                $consumptionForCapCheck = [
                    'carbs' => $recentConsumptionWindow['carbs'] + $nutrientsAddedThisInterval['carbs'],
                    'fluid' => $recentConsumptionWindow['fluid'] + $nutrientsAddedThisInterval['fluid'],
                ];

                // Priority is based on largest *relative cumulative* deficit
                $priorityNeed = $this->_determinePriorityBasedOnCumulativeDeficit($cumulativeDeficit, $this->currentCumulativeTargets);
                Log::debug("PlanGenerator: Needs", [
                    'interval' => $needsWithinInterval,
                    'cumulative_deficit' => $cumulativeDeficit,
                    'priority' => $priorityNeed,
                    'caps_check' => $consumptionForCapCheck
                ]);

                // Find item considering interval needs, cumulative deficit, and priority
                $itemToSchedule = $this->findBestItemForInterval(
                    $availableProducts,
                    $needsWithinInterval, // Pass interval needs for scoring base
                    $cumulativeDeficit,   // Pass cumulative deficit for scoring bonus
                    $consumptionForCapCheck,
                    $priorityNeed,
                    $isSelectionMode,
                    $remainingQuantities,
                    $finalRecommendedTotals // Still needed for the absolute ceiling check
                );

                if ($itemToSchedule) {
                    // Important: Update item offset before adding to schedule
                    $itemToSchedule['details']['time_offset_seconds'] = $currentTimeOffset;

                    $schedule[] = $itemToSchedule['details'];
                    $this->recordConsumption($itemToSchedule['nutrients'], $currentTimeOffset, $nutrientsAddedThisInterval);
                    $itemsScheduledThisIntervalCount++;

                    if ($isSelectionMode && $itemToSchedule['productIdKey'] !== 'WATER') {
                        if (isset($remainingQuantities[$itemToSchedule['productIdKey']])) {
                            $remainingQuantities[$itemToSchedule['productIdKey']]--;
                        } else {
                             Log::warning("Selection Mode: Attempted to decrement quantity for product key not found in remainingQuantities.", ['key' => $itemToSchedule['productIdKey']]);
                        }
                    }


                    // Attempt to schedule water with solid
                     if ($this->shouldScheduleWaterWithSolid($itemToSchedule, $itemsScheduledThisIntervalCount)) {
                        // Water needs check should also use interval logic perhaps?
                        // For now, let's keep it simple: check immediate needs & caps
                         $currentNeedsAfterSolid = $this->_calculateCurrentNeedsWithinInterval($intervalTargets, $nutrientsAddedThisInterval); // Needs *within* interval after solid
                        $waterConsumptionCheck = [ // Update cap check info after solid added
                             'carbs' => $recentConsumptionWindow['carbs'] + $nutrientsAddedThisInterval['carbs'],
                             'fluid' => $recentConsumptionWindow['fluid'] + $nutrientsAddedThisInterval['fluid'],
                        ];
                         $waterItem = $this->scheduleWaterAlongsideSolid($availableProducts, $currentNeedsAfterSolid, $waterConsumptionCheck, $isSelectionMode, $remainingQuantities);
                         if($waterItem){
                            $waterItem['details']['time_offset_seconds'] = $currentTimeOffset; // Set time
                             $schedule[] = $waterItem['details'];
                             $this->recordConsumption($waterItem['nutrients'], $currentTimeOffset, $nutrientsAddedThisInterval);
                             $itemsScheduledThisIntervalCount++;
                         }
                     }

                } else {
                    Log::info("PlanGenerator: No suitable item found for this pass.");
                    break;
                }
            } // End inner item scheduling loop

            // Update cumulative consumed *outside* the inner loop
            $this->cumulativeConsumed['carbs'] += $nutrientsAddedThisInterval['carbs'];
            $this->cumulativeConsumed['fluid'] += $nutrientsAddedThisInterval['fluid'];
            $this->cumulativeConsumed['sodium'] += $nutrientsAddedThisInterval['sodium'];

            Log::info("PlanGenerator: ==== INTERVAL END @ {$this->formatTime($currentTimeOffset)} ====", ['consumed_this_interval' => $nutrientsAddedThisInterval, 'cumulative_total' => $this->cumulativeConsumed]);
        } // End main time loop
        return $schedule;
    }


    /**
      * Determine if scheduling should stop for the current interval.
      * Stops if interval needs are met AND cumulative deficit is not significant.
      */
      private function _shouldStopSchedulingForInterval(array $needsWithinInterval, array $cumulativeDeficit): bool
      {
           $intervalNeedsMet = $needsWithinInterval['carbs'] < self::MIN_CARBS_TO_SCHEDULE_G * 0.5 && // Lower threshold for stopping
                                $needsWithinInterval['fluid'] < self::MIN_FLUID_TO_SCHEDULE_ML * 0.5 &&
                                $needsWithinInterval['sodium'] < self::MIN_SODIUM_TO_SCHEDULE_MG * 0.5;

           // Also consider stopping if we are cumulatively ahead or very close to target
           $cumulativelyCloseOrAhead = ($cumulativeDeficit['carbs'] <= (self::MIN_CARBS_TO_SCHEDULE_G * 1.5)) && // Allow being slightly behind cumulatively
                                        ($cumulativeDeficit['fluid'] <= (self::MIN_FLUID_TO_SCHEDULE_ML * 0.5)) &&
                                        ($cumulativeDeficit['sodium'] <= (self::MIN_SODIUM_TO_SCHEDULE_MG * 1.5));


          // Stop IF interval needs met OR IF cumulatively close/ahead (unless there's a significant *interval* need still somehow?)
           // Prioritize stopping if interval needs met.
          if ($intervalNeedsMet) {
               return true;
          }

          // If interval needs NOT fully met, still consider stopping if cumulatively WAY ahead.
          $cumulativelySignificantlyAhead = $cumulativeDeficit['carbs'] < -($this->getMaxCarbsPerHour($this->currentUser) / 4) || // More than an interval's worth of carbs ahead?
                                           $cumulativeDeficit['fluid'] < -(self::MAX_FLUID_PER_INTERVAL_ML * 0.5); // Significantly ahead on fluid?

          if ($cumulativelySignificantlyAhead) {
               Log::debug("Stopping interval scheduling: Cumulatively significantly ahead.", ['deficit' => $cumulativeDeficit]);
              return true;
          }


           return false; // Otherwise, continue trying to schedule
      }


      /**
       * Determines the priority need based on the largest *relative cumulative* deficit.
       */
      private function _determinePriorityBasedOnCumulativeDeficit(array $cumulativeDeficit, array $currentCumulativeTargets): string
      {
          $priorities = [];
          $minCarbNeed = self::MIN_CARBS_TO_SCHEDULE_G; // Use a minimum deficit amount to consider it a priority
          $minFluidNeed = self::MIN_FLUID_TO_SCHEDULE_ML * 0.5; // Half the minimum scheduling amount
          $minSodiumNeed = self::MIN_SODIUM_TO_SCHEDULE_MG;

          if (($currentCumulativeTargets['carbs'] ?? 0) > 0 && $cumulativeDeficit['carbs'] > $minCarbNeed) {
              $priorities['carbs'] = $cumulativeDeficit['carbs'] / $currentCumulativeTargets['carbs'];
          }
          if (($currentCumulativeTargets['fluid'] ?? 0) > 0 && $cumulativeDeficit['fluid'] > $minFluidNeed) {
              $priorities['fluid'] = $cumulativeDeficit['fluid'] / $currentCumulativeTargets['fluid'];
          }
          if (($currentCumulativeTargets['sodium'] ?? 0) > 0 && $cumulativeDeficit['sodium'] > $minSodiumNeed) {
              $priorities['sodium'] = $cumulativeDeficit['sodium'] / $currentCumulativeTargets['sodium'];
          }

          if (empty($priorities)) return 'none';
          arsort($priorities); // Highest relative deficit first
          return key($priorities);
      }

      /**
     * Finds the best item to schedule in the current pass (Modified for Rate-Based).
     */
    protected function findBestItemForInterval(
        Collection $availableProducts,
        array $needsWithinInterval, // Needs for the current 15min block
        array $cumulativeDeficit,   // Overall deficit up to this point in the activity
        array $consumptionForCapCheck,
        string $priorityNeed, // Based on cumulative deficit
        bool $isSelectionMode,
        array $remainingQuantities,
        array $finalRecommendedTotals
    ): ?array {
        $bestCandidate = null;
        $bestScore = -1;

        foreach ($availableProducts as $productIdKey => $product) {
             if ($isSelectionMode && ($remainingQuantities[$productIdKey] ?? 0) <= 0) continue;

            // Use $needsWithinInterval to guide how much of a drink mix/water to take
             $potentialNutrition = $this->calculateNutrientsForOneItem(
                 $product, $needsWithinInterval, $consumptionForCapCheck, $isSelectionMode
             );

            if ($potentialNutrition) {
                 // Check against final target limits remains important
                 if ($this->_wouldExceedFinalTarget($potentialNutrition, $finalRecommendedTotals)) {
                      continue; // Skip this product if it pushes over the absolute limit
                 }

                $numericNutrientsForScoring = [ /* Extract carbs, fluid, sodium */ ]; // As before
                 $numericNutrientsForScoring = [
                    'carbs' => floatval($potentialNutrition['carbs'] ?? 0.0),
                    'fluid' => floatval($potentialNutrition['fluid'] ?? 0.0),
                    'sodium' => floatval($potentialNutrition['sodium'] ?? 0.0),
                ];


                 $score = $this->scoreProductCandidate(
                    $numericNutrientsForScoring,
                    $needsWithinInterval, // Score based on meeting interval needs
                    $cumulativeDeficit,   // Pass deficit for bonus scoring
                    $priorityNeed,        // Based on cumulative deficit
                    $product->type,
                    $isSelectionMode
                 );

                 Log::debug("PlanGenerator: Evaluating candidate {$product->name} (ID: {$productIdKey})", [
                     'score' => $score, 'nutrients_item' => $numericNutrientsForScoring,
                     'interval_needs' => $needsWithinInterval, 'cumulative_deficit' => $cumulativeDeficit,
                     'priority' => $priorityNeed
                 ]);

                 if ($score > $bestScore) {
                     $bestScore = $score;
                     // Construct $bestCandidate as before, ensuring product_type is included in details
                     $bestCandidate = [ /* ... structure as before ... */];
                      $bestCandidate = [
                         'productIdKey' => $productIdKey,
                         'details' => [
                            'time_offset_seconds' => 0, // Placeholder
                            'instruction_type' => $potentialNutrition['instruction'],
                            'product_id' => ($productIdKey === 'WATER') ? null : $product->id,
                            'product_name' => $product->name,
                            'product_name_override' => ($productIdKey === 'WATER') ? 'Plain Water' : null,
                            'product_type' => $product->type,
                            'quantity_description' => $potentialNutrition['desc'],
                            'calculated_carbs_g' => round($numericNutrientsForScoring['carbs'], 1),
                            'calculated_fluid_ml' => round($numericNutrientsForScoring['fluid']),
                            'calculated_sodium_mg' => round($numericNutrientsForScoring['sodium']),
                            'notes' => $potentialNutrition['notes'],
                        ],
                         'nutrients' => $numericNutrientsForScoring,
                     ];
                 }
            }
        } // End foreach

        if ($bestCandidate) Log::info("PlanGenerator: Best item for pass: {$bestCandidate['details']['product_name']} (Score: {$bestScore})");
        return $bestCandidate;
    }

    /** Helper to check against absolute final limits */
    private function _wouldExceedFinalTarget(array $itemNutrients, array $finalRecommendedTotals): bool
    {
        $nutrientKeys = ['carbs', 'fluid', 'sodium'];
        foreach ($nutrientKeys as $key) {
            $futureTotal = ($this->cumulativeConsumed[$key] ?? 0.0) + ($itemNutrients[$key] ?? 0.0);
             $targetLimit = ($finalRecommendedTotals[$key] ?? 0.0) * 1.20; // Allow 20% over final recommended
            if ($targetLimit > 0 && $futureTotal > $targetLimit) {
                 Log::debug("PlanGenerator: Rejecting item - Adding would exceed final target limit for {$key}.", [
                    'item_adds' => $itemNutrients[$key] ?? 0.0,
                     'cumulative_before' => $this->cumulativeConsumed[$key] ?? 0.0,
                     'future_total' => $futureTotal,
                     'final_target_limit' => $targetLimit
                 ]);
                return true; // Will exceed
            }
        }
        return false; // Within limits
    }



    /**
     * Calculate target nutrients for a specific 15-minute interval.
     */
    private function _calculateIntervalTargets(array $hourlyTargets, int $currentTimeOffset, int $intervalSeconds): array
    {
        if (empty($hourlyTargets)) return ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];

        $hourIndex = floor(($currentTimeOffset - 1) / 3600); // Hour index (0-based) this interval *ends* in
        $targetForThisHour = $hourlyTargets[$hourIndex] ?? end($hourlyTargets); // Fallback to last hour's rate

        if (!$targetForThisHour) return ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0]; // Should not happen if hourlyTargets validated

        $intervalsPerHour = 3600 / $intervalSeconds; // Typically 4

        return [
            'carbs' => ($targetForThisHour['carb_g'] ?? 0) / $intervalsPerHour,
            'fluid' => ($targetForThisHour['fluid_ml'] ?? 0) / $intervalsPerHour,
            'sodium' => ($targetForThisHour['sodium_mg'] ?? 0) / $intervalsPerHour,
        ];
    }

    /**
     * Calculate needs within the current interval.
     */
    private function _calculateCurrentNeedsWithinInterval(array $intervalTargets, array $nutrientsAddedThisInterval): array
    {
         return [
             'carbs' => max(0, $intervalTargets['carbs'] - $nutrientsAddedThisInterval['carbs']),
             'fluid' => max(0, $intervalTargets['fluid'] - $nutrientsAddedThisInterval['fluid']),
             'sodium' => max(0, $intervalTargets['sodium'] - $nutrientsAddedThisInterval['sodium']),
         ];
    }

    protected function areNeedsMet(array $needs): bool
    {
        return $needs['carbs'] < self::MIN_CARBS_TO_SCHEDULE_G &&
            $needs['fluid'] < self::MIN_FLUID_TO_SCHEDULE_ML && // Use the general min fluid to schedule
            $needs['sodium'] < self::MIN_SODIUM_TO_SCHEDULE_MG;
    }

    protected function recordConsumption(array $nutrients, int $currentTimeOffset, array &$nutrientsAddedThisInterval): void
    {
        $nutrientsAddedThisInterval['carbs'] += $nutrients['carbs'];
        $nutrientsAddedThisInterval['fluid'] += $nutrients['fluid'];
        $nutrientsAddedThisInterval['sodium'] += $nutrients['sodium'];

        if ($nutrients['carbs'] > 0 || $nutrients['fluid'] > 0 || $nutrients['sodium'] > 0) {
            $this->consumptionHistory[] = [
                'time' => $currentTimeOffset, // End of interval when item is consumed
                'carbs' => $nutrients['carbs'],
                'fluid' => $nutrients['fluid'],
                'sodium' => $nutrients['sodium']
            ];
        }
    }

    protected function calculateCurrentNeeds(array $currentCumulativeTargets, array $overallCumulativeConsumed, array $nutrientsAddedThisInterval): array
    {
        return [
            'carbs' => max(0, $currentCumulativeTargets['carbs'] - ($overallCumulativeConsumed['carbs'] + $nutrientsAddedThisInterval['carbs'])),
            'fluid' => max(0, $currentCumulativeTargets['fluid'] - ($overallCumulativeConsumed['fluid'] + $nutrientsAddedThisInterval['fluid'])),
            'sodium' => max(0, $currentCumulativeTargets['sodium'] - ($overallCumulativeConsumed['sodium'] + $nutrientsAddedThisInterval['sodium'])),
        ];
    }





    /**
     * Scores a product based on how well it meets current needs and priorities.
     * Higher score is better.
     */
     /**
     * Scores a product candidate (Modified for Rate-Based).
     */
    protected function scoreProductCandidate(
        array $itemNutrients,        // Numeric nutrients provided by item
        array $needsWithinInterval,  // Needs for *this* interval
        array $cumulativeDeficit,    // Overall deficit
        string $priorityNeed,        // Based on cumulative deficit
        string $productType,
        bool $isSelectionMode
    ): float {
        if (array_sum($itemNutrients) <= 0 && $productType !== Product::TYPE_PLAIN_WATER) return -1.0;

        $score = 0.0;
        $priorityNutrientKey = $this->mapPriorityToNutrientKey($priorityNeed);

        // 1. Base score on meeting *Interval Needs*
        // Use the logic similar to before, but based on $needsWithinInterval
        foreach (['carbs', 'fluid', 'sodium'] as $key) {
             if (($needsWithinInterval[$key] ?? 0) > 0 && ($itemNutrients[$key] ?? 0) > 0) {
                $intervalFulfillmentRatio = min(1.0, ($itemNutrients[$key] / $needsWithinInterval[$key]));
                 // Higher weight if it's the priority need
                $weight = ($key === $priorityNutrientKey) ? 100.0 : 30.0;
                 $score += $intervalFulfillmentRatio * $weight;
            }
        }
         // Base score if no priority, but provides something for interval
         if ($priorityNutrientKey === 'none' && array_sum($itemNutrients) > 0) {
            $score += 10;
         }

        // 2. Bonus for reducing *Cumulative Deficit*
        foreach (['carbs', 'fluid', 'sodium'] as $key) {
             if (($cumulativeDeficit[$key] ?? 0) > ($this->getMinNeedConstant($key) * 2) && ($itemNutrients[$key] ?? 0) > 0) { // If there's a notable cumulative deficit
                 // Add a bonus, potentially weighted by how much it helps
                 // Simple bonus for now:
                $score += 20.0; // Add 20 points if it helps a lagging nutrient
            }
        }

        // 3. Penalties / Bonuses (mostly as before)
        // Penalty for gross overshooting of *interval need*? Less important now.
        // Keep type bonuses
         if ($priorityNeed === 'fluid' && ($productType === Product::TYPE_DRINK_MIX || $productType === Product::TYPE_PLAIN_WATER)) $score += 20;
         if ($priorityNeed === 'carbs' && in_array($productType, [Product::TYPE_GEL, Product::TYPE_ENERGY_CHEW, Product::TYPE_DRINK_MIX])) $score += 15;
         if ($priorityNeed === 'sodium' && ($productType === Product::TYPE_HYDRATION_TABLET || $productType === Product::TYPE_DRINK_MIX)) $score += 15; // Add check for HYDRATION_TABLET if needed

        // Slight penalty for solids if CUMULATIVE fluid deficit is high
         if (!$isSelectionMode && ($cumulativeDeficit['fluid'] ?? 0) > ($this->currentCumulativeTargets['fluid'] * 0.3) && !in_array($productType, [Product::TYPE_DRINK_MIX, Product::TYPE_PLAIN_WATER, Product::TYPE_GEL])) {
             $score -= 15;
         }

        // Boost for selection mode items still relevant
         if ($isSelectionMode) {
             foreach (['carbs', 'fluid', 'sodium'] as $key) {
                 if (($needsWithinInterval[$key] > 0) && ($itemNutrients[$key] ?? 0) > 0) {
                     $score += 5; // Small bump for helping interval need
                    break;
                 }
             }
         }

        return max(0.1, $score);
    }

    protected function mapPriorityToNutrientKey(string $priority): string
    {
        // Assumes priority string matches nutrient keys 'carbs', 'fluid', 'sodium'
        return $priority;
    }

    protected function getMinNeedConstant(string $nutrientKey): float
    {
        return match ($nutrientKey) {
            'carbs' => self::MIN_CARB_NEED_G,
            'fluid' => self::MIN_FLUID_NEED_ML,
            'sodium' => self::MIN_SODIUM_NEED_MG,
            default => 0,
        };
    }

    /**
     * Determines if water should be scheduled alongside a recently added solid item.
     */
    // In PlanGenerator.php

    protected function shouldScheduleWaterWithSolid(array $lastItemScheduled, int $itemsScheduledThisIntervalCount): bool
    {
        Log::debug("shouldScheduleWaterWithSolid - CALLED WITH:", [
            'lastItemScheduled_DETAILS' => $lastItemScheduled['details'] ?? 'DETAILS NOT SET',
            'lastItemScheduled_NUTRIENTS' => $lastItemScheduled['nutrients'] ?? 'NUTRIENTS NOT SET',
            'itemsScheduledThisIntervalCount' => $itemsScheduledThisIntervalCount
        ]);

        // --- Step 1: Determine the type of the last scheduled item ---
        $productType = null;
        $isConceptualWaterByNameOverride = false;

        if (isset($lastItemScheduled['details'])) {
            // Check name override first - this is strong indicator for 'Plain Water' if ID was 'WATER'
            if (($lastItemScheduled['details']['product_name_override'] ?? null) === 'Plain Water') {
                $isConceptualWaterByNameOverride = true;
                $productType = Product::TYPE_PLAIN_WATER; // Assign the type if we know it's water
            } else {
                // If not overridden as 'Plain Water', then get type from 'product_type'
                if (array_key_exists('product_type', $lastItemScheduled['details'])) {
                    $productType = $lastItemScheduled['details']['product_type'];
                } else {
                    Log::error("shouldScheduleWaterWithSolid - CRITICAL: 'product_type' is missing from lastItemScheduled['details'] and not overridden as Plain Water.", [
                        'lastItemScheduled' => $lastItemScheduled
                    ]);
                    return false; // Cannot determine if it's solid without type information
                }
            }
        } else {
            Log::error("shouldScheduleWaterWithSolid - CRITICAL: 'details' array is missing from lastItemScheduled.", [
                'lastItemScheduled' => $lastItemScheduled
            ]);
            return false; // Cannot proceed without details
        }

        // --- Step 2: Check if this item is one that shouldn't have water scheduled with it ---
        if ($isConceptualWaterByNameOverride || $productType === Product::TYPE_DRINK_MIX || $productType === Product::TYPE_PLAIN_WATER) {
            // If it's Plain Water (by override or type) or a Drink Mix, don't schedule more water *with* it.
            Log::debug("shouldScheduleWaterWithSolid: Last item is water/drink mix, no additional water needed with it.", ['type' => $productType]);
            return false;
        }

        // --- Step 3: If it's not water/drink mix, then it's considered a "solid" for this rule.
        // Now check if it provides significant fluid and if we can schedule more items.
        $providesSignificantFluid = ($lastItemScheduled['nutrients']['fluid'] ?? 0) >= 50; // e.g. an orange might provide fluid
        $canScheduleMoreItems = $itemsScheduledThisIntervalCount < self::MAX_ITEMS_PER_INTERVAL;

        $shouldSchedule = !$providesSignificantFluid && $canScheduleMoreItems;

        Log::debug("shouldScheduleWaterWithSolid - Evaluation:", [
            'determined_productType' => $productType,
            'isConceptualWaterByNameOverride' => $isConceptualWaterByNameOverride,
            'providesSignificantFluid' => $providesSignificantFluid,
            'canScheduleMoreItems' => $canScheduleMoreItems,
            'result_shouldScheduleWater' => $shouldSchedule
        ]);

        return $shouldSchedule;
    }
    /**
     * Attempts to schedule plain water if conditions are met.
     */
    protected function scheduleWaterAlongsideSolid(Collection $availableProducts, array $needs, array $consumptionForCapCheck, bool $isSelectionMode, array &$remainingQuantities): ?array
    {
        $waterProductModelInstance = $availableProducts->get('WATER'); // Get the Product model instance for water
        if (!$waterProductModelInstance) {
             Log::warning("PlanGenerator: Plain Water product model instance not found in availableProducts for 'scheduleWaterAlongsideSolid'.");
            return null;
        }
        // Check quantity if in selection mode and water was explicitly added by user with quantity
        if ($isSelectionMode && isset($remainingQuantities['WATER']) && $remainingQuantities['WATER'] <= 0) {
            return null;
        }


        $currentFluidNeedForSolid = min($needs['fluid'], self::WATER_WITH_SOLID_ML); // Need for this solid, up to standard amount
        if ($currentFluidNeedForSolid < self::MIN_FLUID_TO_SCHEDULE_ML * 0.5) return null; // Not enough specific need for water *with this solid*

        // Get potential fluid, respecting this more targeted need
        $potentialWaterNutrition = $this->calculateNutrientsForOneItem($waterProductModelInstance, ['fluid' => min($needs['fluid'], self::WATER_WITH_SOLID_ML)] + $needs, $consumptionForCapCheck, $isSelectionMode);

        if ($potentialWaterNutrition && ($potentialWaterNutrition['fluid'] ?? 0) >= self::MIN_FLUID_TO_SCHEDULE_ML * 0.5) {
            Log::info("PlanGenerator: Scheduling water alongside solid.", ['volume' => $potentialWaterNutrition['fluid']]);
            return [
                'productIdKey' => 'WATER',
                'details' => [
                    'time_offset_seconds' => 0, // Placeholder, set by caller
                    'instruction_type' => 'drink',
                    'product_id' => null,
                    'product_name' => 'Plain Water',
                    'product_name_override' => 'Plain Water',
                    'product_type' => $waterProductModelInstance->type,
                    'quantity_description' => $potentialWaterNutrition['desc'],
                    'calculated_carbs_g' => 0,
                    'calculated_fluid_ml' => round($potentialWaterNutrition['fluid']),
                    'calculated_sodium_mg' => 0,
                    'notes' => $potentialWaterNutrition['notes'],
                ],
                'nutrients' => ['carbs' => 0, 'fluid' => $potentialWaterNutrition['fluid'], 'sodium' => 0],
            ];
        }
        return null;
    }

    // --- Nutrient Calculation & Cap Logic ---

    /**
     * Calculates nutrients for ONE UNIT/SERVING of a product, respecting needs and caps.
     * Returns null if not viable. $isSelectionMode allows for more lenient cap checking.
     */
    protected function calculateNutrientsForOneItem(Product $product, array $needs, array $consumptionForCapCheck, bool $isSelectionMode): ?array
    {
        $itemCarbs = 0.0;
        $itemFluid = 0.0;
        $itemSodium = 0.0;
        $qtyDesc = $product->serving_size_description ?? "1 serving";
        $notes = "Consume {$qtyDesc} of {$product->name}";
        $instruction = 'consume'; // Default for solids

        $maxCarbsHr = $this->getMaxCarbsPerHour($this->currentUser);
        $maxFluidHr = $this->getMaxFluidPerHour(); // Could be user specific later

        // Apply multipliers if in selection mode
        $carbCapMultiplier = $isSelectionMode ? self::SELECTION_MODE_CARB_CAP_MULTIPLIER : 1.0;
        $fluidCapMultiplier = $isSelectionMode ? self::SELECTION_MODE_FLUID_CAP_MULTIPLIER : 1.0;
        $effectiveMaxCarbsHr = $maxCarbsHr * $carbCapMultiplier;
        $effectiveMaxFluidHr = $maxFluidHr * $fluidCapMultiplier;

        $minFluidToSchedule = $isSelectionMode ? self::MIN_FLUID_NEED_ML : self::MIN_FLUID_TO_SCHEDULE_ML;

        if ($product->type === Product::TYPE_DRINK_MIX) {
            $pCarbsPerStdServing = $product->carbs_g ?? 0;
            $pSodiumPerStdServing = $product->sodium_mg ?? 0;
            $pStdServingVolumeMl = $product->final_drink_volume_per_serving_ml; // This is critical

            if (!$pStdServingVolumeMl || $pStdServingVolumeMl <= 0) {
                Log::debug("calculateNutrients: Drink mix {$product->name} has invalid standard serving volume.", ['vol' => $pStdServingVolumeMl]);
                return null;
            }

            $fluidAvailableInInterval = self::MAX_FLUID_PER_INTERVAL_ML; // Max from one item in an interval
            $fluidRoomInHourCap = max(0, $effectiveMaxFluidHr - $consumptionForCapCheck['fluid']);

            // Determine volume to consume: limited by need, interval cap, and hourly cap room
            $potentialVolume = floor(min($needs['fluid'], $fluidAvailableInInterval, $fluidRoomInHourCap));

            if ($potentialVolume < $minFluidToSchedule) {
                // In selection mode, if fluid need is very small but >0, schedule a small amount
                if ($isSelectionMode && $needs['fluid'] > 0 && $needs['fluid'] < $minFluidToSchedule) {
                    $potentialVolume = floor(min(max(self::MIN_FLUID_NEED_ML, $needs['fluid']), $fluidAvailableInInterval, $fluidRoomInHourCap));
                    if ($potentialVolume < self::MIN_FLUID_NEED_ML) return null; // Still too little even for selection mode minimum
                } else {
                    return null; // Not enough need or room
                }
            }

            $itemFluid = $potentialVolume;
            $proportionOfServing = $itemFluid / $pStdServingVolumeMl;
            $itemCarbs = $pCarbsPerStdServing * $proportionOfServing;
            $itemSodium = $pSodiumPerStdServing * $proportionOfServing;

            if (($consumptionForCapCheck['carbs'] + $itemCarbs) > $effectiveMaxCarbsHr) {
                Log::debug("calculateNutrients: Drink mix {$product->name} rejected due to carb cap.", ['item_carbs' => $itemCarbs, 'cap_check' => $consumptionForCapCheck['carbs'], 'limit' => $effectiveMaxCarbsHr]);
                return null;
            }
            $instruction = 'drink';
            $qtyDesc = round($itemFluid) . "ml";
            $notes = "Prepare with approx. " . round($proportionOfServing, 1) . "x of {$product->serving_size_description} and drink {$qtyDesc}.";
        } elseif ($product->type === Product::TYPE_PLAIN_WATER || $product->id === 'WATER') { // $product->id check for conceptual water
            $fluidAvailableInInterval = self::MAX_FLUID_PER_INTERVAL_ML;
            $fluidRoomInHourCap = max(0, $effectiveMaxFluidHr - $consumptionForCapCheck['fluid']);
            $potentialVolume = floor(min($needs['fluid'], $fluidAvailableInInterval, $fluidRoomInHourCap));

            if ($potentialVolume < $minFluidToSchedule) {
                if ($isSelectionMode && $needs['fluid'] > 0 && $needs['fluid'] < $minFluidToSchedule) {
                    $potentialVolume = floor(min(max(self::MIN_FLUID_NEED_ML, $needs['fluid']), $fluidAvailableInInterval, $fluidRoomInHourCap));
                    if ($potentialVolume < self::MIN_FLUID_NEED_ML) return null;
                } else {
                    return null;
                }
            }
            $itemFluid = $potentialVolume;
            $instruction = 'drink';
            $qtyDesc = round($itemFluid) . "ml";
            $notes = "Drink {$qtyDesc} of Plain Water.";
        } else { // Gels, Bars, Chews, Real Food etc. (Treat as one discrete serving unit)
            $itemCarbs = $product->carbs_g ?? 0;
            $itemSodium = $product->sodium_mg ?? 0;
            // Solids might provide some fluid, e.g. an orange. Get from 'provided_fluid_per_serving_ml'
            $itemFluid = $product->provided_fluid_per_serving_ml ?? 0;

            if (($consumptionForCapCheck['carbs'] + $itemCarbs) > $effectiveMaxCarbsHr) {
                Log::debug("calculateNutrients: Solid {$product->name} rejected due to carb cap.");
                return null;
            }
            if (($consumptionForCapCheck['fluid'] + $itemFluid) > $effectiveMaxFluidHr) {
                Log::debug("calculateNutrients: Solid {$product->name} rejected due to fluid cap.");
                return null;
            }
            $qtyDesc = $product->serving_size_description ?: "1 " . ($product->serving_size_units ?: 'unit');
            $notes = "Consume {$qtyDesc} of {$product->name}.";
            if ($itemFluid < 50 && $product->type !== Product::TYPE_DRINK_MIX && $product->type !== Product::TYPE_PLAIN_WATER) { // Suggest water if solid provides little fluid
                $notes .= " Consider drinking ~" . self::WATER_WITH_SOLID_ML . "ml water.";
            }
        }

        // If item has no relevant nutrients after calculation (e.g. drink mix calculated to 0ml fluid), don't schedule
        if ($itemCarbs == 0 && $itemFluid == 0 && $itemSodium == 0 && $product->type !== Product::TYPE_PLAIN_WATER && $product->id !== 'WATER') {
            Log::debug("calculateNutrients: Item {$product->name} provides no net nutrients after calculation.", ['carbs' => $itemCarbs, 'fluid' => $itemFluid, 'sodium' => $itemSodium]);
            return null;
        }

        return ['carbs' => $itemCarbs, 'fluid' => $itemFluid, 'sodium' => $itemSodium, 'desc' => $qtyDesc, 'notes' => $notes, 'instruction' => $instruction];
    }

    // --- Helper Methods for Targets, Consumption, Product Info ---

    /**
     * Get the total recommended nutrient targets for the activity duration.
     */
    public function getRecommendedTotalsForDuration(array $hourlyTargets, int $durationSeconds): array
    {
        if (empty($hourlyTargets) || $durationSeconds <= 0) {
            Log::warning("PlanGenerator: getRecommendedTotalsForDuration - Invalid inputs.", ['targets_count' => count($hourlyTargets), 'duration' => $durationSeconds]);
            return ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
        }
        $recommendedTotals = $this->calculateCumulativeTargets($hourlyTargets, $durationSeconds);
        Log::debug("PlanGenerator: Calculated total recommended targets for duration {$durationSeconds}s:", $recommendedTotals);
        return $recommendedTotals;
    }


    protected function getMaxCarbsPerHour(User $user): int
    {
        // Future: return $user->profile->max_carbs_per_hour ?? self::MAX_CARBS_PER_HOUR;
        return self::MAX_CARBS_PER_HOUR;
    }

    protected function getMaxFluidPerHour(): int
    {
        // Future: user-specific sweat rate adjustment
        return self::MAX_FLUID_PER_HOUR_ML;
    }

    protected function getMaxSodiumPerHour(): int
    {
        // Future: user-specific sodium cap
        return self::MAX_SODIUM_PER_HOUR_MG;
    }

    protected function preprocessProductList(Collection $products, bool $addWater = true, bool $sort = true): Collection
    {
        $processed = $products->map(function (Product $product) {
            // Essential pre-calculated properties
            $product->final_drink_volume_per_serving_ml = 0;
            $product->provided_fluid_per_serving_ml = 0;

            if ($product->type === Product::TYPE_DRINK_MIX) {
                $product->final_drink_volume_per_serving_ml = $product->serving_size_ml ?? 500; // Default if not set
                $product->provided_fluid_per_serving_ml = $product->final_drink_volume_per_serving_ml;
            } elseif ($product->type === Product::TYPE_PLAIN_WATER || $product->id === 'WATER') { // $product->id === 'WATER' for conceptual water
                // For plain water, serving_size_ml represents a typical "unit" or "sip volume" we schedule
                $product->provided_fluid_per_serving_ml = $product->serving_size_ml ?? self::MIN_FLUID_TO_SCHEDULE_ML;
                $product->final_drink_volume_per_serving_ml = $product->provided_fluid_per_serving_ml; // It's ready to drink
            } elseif (in_array($product->type, [Product::TYPE_GEL, Product::TYPE_ENERGY_CHEW, Product::TYPE_ENERGY_BAR, Product::TYPE_REAL_FOOD])) {
                // Some real foods might have inherent fluid. This should be a DB column ideally 'inherent_fluid_ml'.
                $product->provided_fluid_per_serving_ml = $product->inherent_fluid_ml ?? 0; // Assume you add this to Product model
            }
            // else: tablets, recovery drinks - handle their fluid provision if necessary

            $product->sort_priority = match ($product->type) {
                Product::TYPE_DRINK_MIX => 10,          // High priority for fluids/broad nutrition
                Product::TYPE_HYDRATION_TABLET => 15,   // If sodium/electrolytes are primary
                Product::TYPE_GEL => 20,                // Quick carbs
                Product::TYPE_ENERGY_CHEW => 25,
                Product::TYPE_PLAIN_WATER => 30,        // Available for hydration
                Product::TYPE_ENERGY_BAR => 40,         // More substantial
                Product::TYPE_REAL_FOOD => 50,          // Usually less prioritized during high intensity
                default => 60,
            };
            return $product;
        });

        if ($addWater && !$processed->contains(fn($p) => $p->id === 'WATER')) {
            $processed->push($this->getWaterProduct());
        }
        return $sort ? $processed->sortBy('sort_priority') : $processed;
    }

    protected function getWaterProduct(): Product
    {
        // Create a new Product instance for water on-the-fly
        // Ensure this 'WATER' id is consistently used, esp. in $remainingQuantities for selection mode
        $water = new Product();
        $water->id = 'WATER'; // Special non-DB ID, used as a key
        $water->name = 'Plain Water';
        $water->type = Product::TYPE_PLAIN_WATER;
        $water->carbs_g = 0;
        $water->sodium_mg = 0;
        $water->serving_size_description = self::MIN_FLUID_TO_SCHEDULE_ML . "ml"; // How much we schedule at a time
        $water->serving_size_ml = self::MIN_FLUID_TO_SCHEDULE_ML; // Defines a "unit" of water for scheduling
        // These will be set by preprocessPantry
        // $water->final_drink_volume_per_serving_ml = self::MIN_FLUID_TO_SCHEDULE_ML;
        // $water->provided_fluid_per_serving_ml = self::MIN_FLUID_TO_SCHEDULE_ML;
        $water->sort_priority = 100; // Set a distinct sort priority if preprocessPantry doesn't run on it again
        return $water; // PreprocessPantry will further refine it if called on collection containing this
    }


    protected function calculateCumulativeTargets(array $hourlyTargets, int $currentTimeOffset): array
    {
        $totalTargets = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
        if (empty($hourlyTargets) || $currentTimeOffset <= 0) return $totalTargets;

        $elapsedHours = $currentTimeOffset / 3600.0;
        $fullHours = floor($elapsedHours);
        $partialHourFraction = $elapsedHours - $fullHours;

        for ($h = 0; $h < $fullHours; $h++) {
            $target = $hourlyTargets[$h] ?? ($hourlyTargets[count($hourlyTargets) - 1] ?? null); // Use last available if out of bounds
            if ($target) {
                $totalTargets['carbs'] += $target['carb_g'] ?? 0;
                $totalTargets['fluid'] += $target['fluid_ml'] ?? 0;
                $totalTargets['sodium'] += $target['sodium_mg'] ?? 0;
            }
        }

        if ($partialHourFraction > 0) {
            $target = $hourlyTargets[$fullHours] ?? ($hourlyTargets[count($hourlyTargets) - 1] ?? null);
            if ($target) {
                $totalTargets['carbs'] += ($target['carb_g'] ?? 0) * $partialHourFraction;
                $totalTargets['fluid'] += ($target['fluid_ml'] ?? 0) * $partialHourFraction;
                $totalTargets['sodium'] += ($target['sodium_mg'] ?? 0) * $partialHourFraction;
            }
        }
        return $totalTargets;
    }

    protected function determinePriorityNeed(array $needs, array $currentCumulativeTargets): string
    {
        if (empty($currentCumulativeTargets) || (array_sum($currentCumulativeTargets) == 0 && array_sum($needs) == 0)) return 'none';

        $priorities = [];
        // Calculate relative deficit: (need / total_target_for_this_nutrient_so_far)
        // Only consider if target is > 0 to avoid division by zero or skewed priorities.
        if (($currentCumulativeTargets['fluid'] ?? 0) > 0 && $needs['fluid'] >= self::MIN_FLUID_TO_SCHEDULE_ML) {
            $priorities['fluid'] = ($needs['fluid'] / $currentCumulativeTargets['fluid']) * ($needs['fluid'] > self::MAX_FLUID_PER_INTERVAL_ML * 0.75 ? 1.2 : 1); // Boost if urgent
        }
        if (($currentCumulativeTargets['sodium'] ?? 0) > 0 && $needs['sodium'] >= self::MIN_SODIUM_TO_SCHEDULE_MG) {
            $priorities['sodium'] = ($needs['sodium'] / $currentCumulativeTargets['sodium']) * ($needs['sodium'] > ($this->getMaxSodiumPerHour() / 4 * 0.75) ? 1.2 : 1);
        }
        if (($currentCumulativeTargets['carbs'] ?? 0) > 0 && $needs['carbs'] >= self::MIN_CARBS_TO_SCHEDULE_G) {
            $priorities['carbs'] = ($needs['carbs'] / $currentCumulativeTargets['carbs']);
        }

        if (empty($priorities)) { // If all targets are 0 or needs are below minimums
            // Fallback: if any need is above absolute minimum, prioritize it. Carbs > Fluid > Sodium default.
            if ($needs['carbs'] >= self::MIN_CARBS_TO_SCHEDULE_G) return 'carbs';
            if ($needs['fluid'] >= self::MIN_FLUID_TO_SCHEDULE_ML) return 'fluid';
            if ($needs['sodium'] >= self::MIN_SODIUM_TO_SCHEDULE_MG) return 'sodium';
            return 'none';
        }
        arsort($priorities); // Sort by highest relative deficit
        return key($priorities);
    }

   // In PlanGenerator.php

   protected function getRecentConsumption(array &$consumptionHistory, int $currentTimeOffset, int $windowSeconds): array
   {
       $windowStart = $currentTimeOffset - $windowSeconds;
       $recentTotals = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];

       // Log the history just BEFORE filtering
       Log::debug("calculateRecentConsumption - History BEFORE filter for time {$this->formatTime($currentTimeOffset)}:", [
           'history_count' => count($consumptionHistory),
           'currentTimeOffset_sec' => $currentTimeOffset,
           'windowStart_sec' => $windowStart,
           // 'history_data' => $consumptionHistory // Can be very verbose
       ]);

       $consumptionHistory = array_filter(
           $consumptionHistory,
           function ($item) use ($windowStart, $currentTimeOffset, &$recentTotals) { // <--- ADD $currentTimeOffset HERE
               if (!is_array($item) || !isset($item['time'])) {
                   Log::warning("calculateRecentConsumption - Invalid item in consumptionHistory (missing time or not array):", ['item_data' => $item]);
                   return false; // Skip invalid
               }

               // Check if the item's consumption time falls within the desired window
               // The window is (windowStart, currentTimeOffset] - i.e., after windowStart and up to and including currentTimeOffset.
               $isWithinWindow = ($item['time'] > $windowStart && $item['time'] <= $currentTimeOffset);

               if ($isWithinWindow) {
                   $recentTotals['carbs'] += $item['carbs'] ?? 0;
                   $recentTotals['fluid'] += $item['fluid'] ?? 0;
                   $recentTotals['sodium'] += $item['sodium'] ?? 0;
                   return true; // Keep item
               }
               return false; // Discard item older than window start or somehow beyond current time (should not happen)
           }
       );

       $consumptionHistory = array_values($consumptionHistory); // Re-index

       Log::debug("calculateRecentConsumption - Recent Totals AFTER filter for window ending {$this->formatTime($currentTimeOffset)}:", [
           'recent_totals' => $recentTotals,
           'history_count_after_filter' => count($consumptionHistory)
       ]);
       return $recentTotals;
   }

    protected function calculateAverageHourlyConsumption(array $cumulativeConsumed, int $durationSeconds): array
    {
        $hours = ($durationSeconds > 0) ? ($durationSeconds / 3600.0) : 1;
        return [
            'carbs' => ($cumulativeConsumed['carbs'] ?? 0) / $hours,
            'fluid' => ($cumulativeConsumed['fluid'] ?? 0) / $hours,
            'sodium' => ($cumulativeConsumed['sodium'] ?? 0) / $hours,
        ];
    }

    protected function formatTime(int $seconds): string
    {
        return CarbonInterval::seconds($seconds)->cascade()->format('%H:%I:%S');
    }

    // --- Output Formatting & Warnings for Selection Mode ---
    protected function formatSelectionResult(array $schedule, array $warnings, array $leftovers, ?array $actualTotals = null, ?array $recommendedTotals = null): array
    {
        return [
            'schedule' => $schedule,
            'warnings' => $warnings,
            'leftovers' => $leftovers,
            'actual_totals' => $actualTotals ?? ['carbs' => 0, 'fluid' => 0, 'sodium' => 0],
            'recommended_totals' => $recommendedTotals ?? ['carbs' => 0, 'fluid' => 0, 'sodium' => 0],
        ];
    }

    protected function generateDeficitWarnings(array $actualConsumed, array $recommendedTotals, bool $isSelectionMode): array
    {
        $warnings = [];
        $deficitThresholdMultiplier = $isSelectionMode ? 0.25 : 0.15; // 25% for selection, 15% for auto

        $carbDeficit = max(0, ($recommendedTotals['carbs'] ?? 0) - ($actualConsumed['carbs'] ?? 0));
        if (($recommendedTotals['carbs'] ?? 0) > 0 && $carbDeficit > ($recommendedTotals['carbs'] * $deficitThresholdMultiplier)) {
            $warnings[] = ($isSelectionMode ? "Based on your selection, " : "") . "plan may be low on carbs (~" . round($carbDeficit) . "g below target).";
        }

        $fluidDeficit = max(0, ($recommendedTotals['fluid'] ?? 0) - ($actualConsumed['fluid'] ?? 0));
        if (($recommendedTotals['fluid'] ?? 0) > 0 && $fluidDeficit > ($recommendedTotals['fluid'] * $deficitThresholdMultiplier)) {
            $warnings[] = ($isSelectionMode ? "Based on your selection, " : "") . "plan may be low on fluid (~" . round($fluidDeficit) . "ml below target).";
        }

        $sodiumDeficit = max(0, ($recommendedTotals['sodium'] ?? 0) - ($actualConsumed['sodium'] ?? 0));
        if (($recommendedTotals['sodium'] ?? 0) > 0 && $sodiumDeficit > ($recommendedTotals['sodium'] * $deficitThresholdMultiplier)) {
            $warnings[] = ($isSelectionMode ? "Based on your selection, " : "") . "plan may be low on sodium (~" . round($sodiumDeficit) . "mg below target).";
        }
        return $warnings;
    }

    protected function formatLeftoverItems(array $remainingQuantities, Collection $processedProducts): array
    {
        $leftoversFormatted = [];
        foreach ($remainingQuantities as $productIdKey => $qty) {
            if ($qty > 0 && $productIdKey !== 'WATER') {
                $product = $processedProducts->get($productIdKey); // Use get() as $processedProducts is keyed
                if ($product) {
                    $unit = $product->serving_size_units ?: 'unit';
                    $baseUnit = trim(preg_replace('/^[0-9.]+\s*/', '', (string)$unit));
                    if (empty($baseUnit)) $baseUnit = (string)$unit;
                    $pluralUnit = ($qty === 1 || empty($baseUnit)) ? $baseUnit : Str::plural($baseUnit);

                    $leftoversFormatted[] = ['name' => $product->name, 'quantity' => $qty, 'unit' => $pluralUnit];
                }
            }
        }
        return $leftoversFormatted;
    }
}
