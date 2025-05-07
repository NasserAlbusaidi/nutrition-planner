<?php

namespace App\Services;

use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonInterval; // For easier time formatting
use Illuminate\Support\Str; // For string manipulation

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

    protected const MIN_CARB_NEED_G = 5;         // <-- ADD THIS
    protected const MIN_FLUID_NEED_ML = 50;        // <-- ADD THIS
    protected const MIN_SODIUM_NEED_MG = 25;       // <-- ADD THIS
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
            'user_id' => $user->id,
            'duration_sec' => $durationSeconds,
            'targets_count' => count($hourlyTargets),
            'products_count' => $pantryProducts->count()
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
            Log::debug("PlanGenerator Refactor: Cumulative Targets @ end of interval:", array_map(fn($n) => round($n, 1), $cumulativeTargets));
            Log::debug("PlanGenerator Refactor: Cumulative Consumed before this interval:", array_map(fn($n) => round($n, 1), $cumulativeConsumed));


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
                if (
                    $needsForThisPass['carbs'] < self::MIN_CARBS_TO_SCHEDULE_G &&
                    $needsForThisPass['fluid'] < self::MIN_FLUID_SCHEDULE_ML &&
                    $needsForThisPass['sodium'] < 50
                ) {
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

                Log::debug("PlanGenerator Refactor: Inner loop - Current Pass Needs:", array_map(fn($n) => round($n, 1), $needsForThisPass));
                Log::debug("PlanGenerator Refactor: Inner loop - Consumption for Cap Check (Rolling Hour + This Interval):", array_map(fn($n) => round($n, 1), $consumptionForCapCheck));


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

                    $itemCarbs = 0;
                    $itemFluid = 0;
                    $itemSodium = 0;
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
                                } else {
                                    Log::debug("Drink Mix {$product->name} rejected: carb cap.");
                                }
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
                        } else {
                            Log::debug("Gel/Chew {$product->name} rejected: carb cap.");
                        }
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
                        } else {
                            Log::debug("Bar/Food {$product->name} rejected: carb cap.");
                        }
                    }
                    // Add Hydration Tablets logic if needed (primarily for sodium/fluid)
                    elseif ($product->type === Product::TYPE_HYDRATION_TABLET && $needsForThisPass['sodium'] > 50) {
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
                                $schedule[] = [
                                    'calculated_fluid_ml' => $waterToAdd
                                ];
                                $nutrientsAddedThisInterval['fluid'] += $waterToAdd;
                                // IMPORTANT: Update $consumptionHistory immediately AFTER adding water
                                $consumptionHistory[] = ['time' => $currentTimeOffset, 'carbs' => 0, 'fluid' => $waterToAdd, 'sodium' => 0];
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

            Log::info("PlanGenerator Refactor: ==== INTERVAL END ==== Time: " . $this->formatTime($currentTimeOffset) . ". Cumulatives:", array_map(fn($n) => round($n, 1), $cumulativeConsumed));
        } // --- End while ($currentTimeOffset < $durationSeconds) [Main Loop] ---

        Log::info("PlanGenerator Refactor: generateSchedule END", ['user' => $user->id, 'item_count' => count($schedule)]);
        return $schedule;
    }

    // --- METHOD 2: New Generator Using User's Selection ---

    /**
     * Generate schedule using ONLY user-selected items.
     * Tries to schedule up to MAX_ITEMS_PER_INTERVAL items within each time interval.
     * Returns schedule, warnings about deficits, and leftover items.
     *
     * @param User $user
     * @param int $durationSeconds
     * @param array $hourlyTargets // For target assessment
     * @param Collection $userCarryList // Keyed by ID: ['product' => ProductModel, 'quantity' => int]
     * @return array ['schedule' => array, 'warnings' => array, 'leftovers' => array]
     */
    public function generateScheduleFromSelection(User $user, int $durationSeconds, array $hourlyTargets, Collection $userCarryList): array
    {
        Log::info("PlanGenerator (Selection Mode): generateScheduleFromSelection START");

        $schedule = [];
        $warnings = [];
        $leftoversFormatted = []; // Initialize as empty array
        $remainingQuantities = $userCarryList->mapWithKeys(fn($itemInfo, $id) => [$id => $itemInfo['quantity']])->toArray();
        $carryListProducts = $userCarryList->map(fn($itemInfo) => $itemInfo['product']);

        $intervalSeconds = self::INTERVAL_MINUTES * 60;
        $currentTimeOffset = 0;

        $cumulativeConsumed = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];
        $consumptionHistory = [];

        $hasWater = $carryListProducts->contains(fn($p) => $p->type === Product::TYPE_PLAIN_WATER || $p->id === 'WATER');
        if (!$hasWater) {
            $waterProduct = $this->getWaterProduct();
            $carryListProducts->push($waterProduct);
            $remainingQuantities['WATER'] = 999;
            Log::info("PlanGenerator (Selection): Added Plain Water to available items for scheduling with solids.");
        }

        // Preprocess and key by ID. Ensure WATER product has ID 'WATER' if it came from getWaterProduct()
        $processedCarryList = $this->preprocessPantry($carryListProducts)->mapWithKeys(function ($product) {
            // Ensure water product from getWaterProduct always uses 'WATER' as its key
            $key = ($product->name === 'Plain Water' && $product->type === Product::TYPE_PLAIN_WATER && !isset($product->id)) ? 'WATER' : $product->id;
            return [$key => $product];
        });


        Log::info("PlanGenerator (Selection): Preprocessed carry list", ['count' => $processedCarryList->count()]);

        while ($currentTimeOffset < $durationSeconds) {
            $currentTimeOffset += $intervalSeconds;
            Log::info("PlanGenerator (Selection): ==== INTERVAL START ==== Ending @ " . $this->formatTime($currentTimeOffset));

            $currentCumulativeTargets = $this->calculateCumulativeTargets($hourlyTargets, $currentTimeOffset);
            $itemsScheduledThisIntervalCount = 0;
            $nutrientsAddedThisInterval = ['carbs' => 0.0, 'fluid' => 0.0, 'sodium' => 0.0];

            while ($itemsScheduledThisIntervalCount < self::MAX_ITEMS_PER_INTERVAL) {
                $recentConsumption = $this->calculateRecentConsumption($consumptionHistory, $currentTimeOffset, 3600); // Recalculate each pass

                $needs = [
                    'carbs' => max(0, $currentCumulativeTargets['carbs'] - ($cumulativeConsumed['carbs'] + $nutrientsAddedThisInterval['carbs'])),
                    'fluid' => max(0, $currentCumulativeTargets['fluid'] - ($cumulativeConsumed['fluid'] + $nutrientsAddedThisInterval['fluid'])),
                    'sodium' => max(0, $currentCumulativeTargets['sodium'] - ($cumulativeConsumed['sodium'] + $nutrientsAddedThisInterval['sodium'])),
                ];
                // For selection mode, be more lenient with caps.
                // We'll still check against base caps but the decision to schedule might ignore minor overages.
                $consumptionForCapCheck = ['carbs' => $recentConsumption['carbs'] + $nutrientsAddedThisInterval['carbs'], 'fluid' => $recentConsumption['fluid'] + $nutrientsAddedThisInterval['fluid']];

                if ($needs['carbs'] < self::MIN_CARB_NEED_G && $needs['fluid'] < self::MIN_FLUID_NEED_ML && $needs['sodium'] < self::MIN_SODIUM_NEED_MG) {
                    Log::debug("Inner loop (Selection): Needs minimal. Breaking.", $needs);
                    break;
                }

                $priorityNeed = $this->determinePriorityNeed($needs, $currentCumulativeTargets); // Removed $cumulativeConsumed, already factored into $needs
                Log::debug("Inner loop (Selection): Current Needs:", array_map(fn($n) => round($n, 1), $needs), " Priority:", $priorityNeed);

                $bestItemToScheduleNow = null;

                // Iterate through user's items, prioritized by their inherent sort order if desired, or just as they are
                // Consider sorting $processedCarryList by sort_priority if you want to try certain item types first
                $sortedUserItems = $processedCarryList->sortBy('sort_priority');


                foreach ($sortedUserItems as $productId => $product) { // productId will be actual product ID or 'WATER'
                    if (($remainingQuantities[$productId] ?? 0) <= 0) {
                        Log::debug("Selection Mode: Skipping {$product->name}, quantity 0.");
                        continue;
                    }

                    // Use a more lenient version of nutrient calculation for selection mode
                    $potentialNutrition = $this->calculateNutrientsForOneItem(
                        $product,
                        $needs,
                        $consumptionForCapCheck,
                        $this->getMaxCarbsPerHour($user),
                        $this->getMaxFluidPerHour(),
                        true // Add a flag: $isSelectionMode = true
                    );

                    if ($potentialNutrition) {
                        // In selection mode, if an item provides *any* of the needed nutrients and is on the user's list,
                        // it's a strong candidate.
                        // Prioritize if it meets the determined priority need.
                        $meetsPriority = false;
                        $providesAnyNeed = false;

                        if ($needs['carbs'] > self::MIN_CARB_NEED_G && $potentialNutrition['carbs'] > 0) $providesAnyNeed = true;
                        if ($needs['fluid'] > self::MIN_FLUID_NEED_ML && $potentialNutrition['fluid'] > 0) $providesAnyNeed = true;
                        if ($needs['sodium'] > self::MIN_SODIUM_NEED_MG && $potentialNutrition['sodium'] > 0) $providesAnyNeed = true;

                        if ($priorityNeed === 'carbs' && $potentialNutrition['carbs'] > 0) $meetsPriority = true;
                        elseif ($priorityNeed === 'fluid' && $potentialNutrition['fluid'] > 0) $meetsPriority = true;
                        elseif ($priorityNeed === 'sodium' && $potentialNutrition['sodium'] > 0) $meetsPriority = true;
                        elseif ($priorityNeed === 'none' && $providesAnyNeed) $meetsPriority = true; // If no specific priority, take if it helps with anything

                        if ($meetsPriority || $providesAnyNeed) { // Be more aggressive in picking user's items
                            Log::info("Selection Mode: SUITABLE user item found for pass: {$product->name} (ID: {$productId})");
                            $bestItemToScheduleNow = [
                                'product' => $product,
                                'productIdKey' => $productId, // Store the key used (could be 'WATER' or actual int ID)
                                'nutrients' => $potentialNutrition,
                                'qty_desc' => $potentialNutrition['desc'] ?? "1 serving",
                                'instruction' => $potentialNutrition['instruction'] ?? 'consume',
                                'notes' => $potentialNutrition['notes'] ?? "Consume {$product->name}"
                            ];
                            break;
                        } else {
                            Log::debug("Selection Mode: User item {$product->name} (ID: {$productId}) did not meet priority/any need this pass.", ['priority' => $priorityNeed, 'needs' => $needs, 'provides' => $potentialNutrition]);
                        }
                    } else {
                        Log::debug("Selection Mode: User item {$product->name} (ID: {$productId}) deemed not viable by calculateNutrientsForOneItem.");
                    }
                }

                if ($bestItemToScheduleNow) {
                    $productUsed = $bestItemToScheduleNow['product'];
                    $productIdForDb = ($bestItemToScheduleNow['productIdKey'] === 'WATER') ? null : $productUsed->id; // Use actual product->id for DB if not water
                    $productNameForSchedule = ($bestItemToScheduleNow['productIdKey'] === 'WATER') ? 'Plain Water' : $productUsed->name;


                    $schedule[] = [
                        'time_offset_seconds' => $currentTimeOffset,
                        'instruction_type' => $bestItemToScheduleNow['instruction'],
                        'product_id' => $productIdForDb,
                        'product_name' => $productNameForSchedule,
                        'product_name_override' => ($bestItemToScheduleNow['productIdKey'] === 'WATER') ? 'Plain Water' : null,
                        'quantity_description' => $bestItemToScheduleNow['qty_desc'],
                        'calculated_carbs_g' => round($bestItemToScheduleNow['nutrients']['carbs'], 1),
                        'calculated_fluid_ml' => round($bestItemToScheduleNow['nutrients']['fluid']),
                        'calculated_sodium_mg' => round($bestItemToScheduleNow['nutrients']['sodium']),
                        'notes' => $bestItemToScheduleNow['notes'],
                    ];

                    $cumulativeConsumed['carbs'] += $bestItemToScheduleNow['nutrients']['carbs'];
                    $cumulativeConsumed['fluid'] += $bestItemToScheduleNow['nutrients']['fluid'];
                    $cumulativeConsumed['sodium'] += $bestItemToScheduleNow['nutrients']['sodium'];

                    $consumptionHistory[] = [
                        'time' => $currentTimeOffset,
                        'carbs' => $bestItemToScheduleNow['nutrients']['carbs'],
                        'fluid' => $bestItemToScheduleNow['nutrients']['fluid'],
                        'sodium' => $bestItemToScheduleNow['nutrients']['sodium']
                    ];


                    $nutrientsAddedThisInterval['carbs'] += $bestItemToScheduleNow['nutrients']['carbs'];
                    $nutrientsAddedThisInterval['fluid'] += $bestItemToScheduleNow['nutrients']['fluid'];
                    $nutrientsAddedThisInterval['sodium'] += $bestItemToScheduleNow['nutrients']['sodium'];

                    $remainingQuantities[$bestItemToScheduleNow['productIdKey']]--;
                    $itemsScheduledThisIntervalCount++;

                    Log::info("Selection Mode: ADDED {$productUsed->name} @ " . $this->formatTime($currentTimeOffset));
                } else {
                    Log::debug("Selection Mode: No suitable product found in CARRY LIST for current needs/caps this pass.");
                    break;
                }
            }
            Log::info("PlanGenerator (Selection): ==== INTERVAL END ==== Time: " . $this->formatTime($currentTimeOffset) . ". Items this interval: {$itemsScheduledThisIntervalCount}");
        }

        // --- Final Assessment & Warnings ---
        $finalTargets = $this->calculateCumulativeTargets($hourlyTargets, $durationSeconds);
        $carbDeficit = max(0, $finalTargets['carbs'] - $cumulativeConsumed['carbs']);
        $fluidDeficit = max(0, $finalTargets['fluid'] - $cumulativeConsumed['fluid']);
        $sodiumDeficit = max(0, $finalTargets['sodium'] - $cumulativeConsumed['sodium']);

        if ($carbDeficit > max(20, $finalTargets['carbs'] * 0.25)) { // Increased tolerance to 25%
            $warnings[] = "Based on your selection, plan may be low on carbs (~" . round($carbDeficit) . "g below target).";
        }
        if ($fluidDeficit > max(250, $finalTargets['fluid'] * 0.25)) {
            $warnings[] = "Based on your selection, plan may be low on fluid (~" . round($fluidDeficit) . "ml below target).";
        }
        if ($sodiumDeficit > max(100, $finalTargets['sodium'] * 0.25)) {
            $warnings[] = "Based on your selection, plan may be low on sodium (~" . round($sodiumDeficit) . "mg below target).";
        }

        // Check for over-consumption based on user's selection (more of an FYI)
        $activityHours = ($durationSeconds > 0) ? $durationSeconds / 3600 : 1;
        $avgHourlyCarbsConsumed = $cumulativeConsumed['carbs'] / $activityHours;
        if ($avgHourlyCarbsConsumed > $this->getMaxCarbsPerHour($user) * 1.1) { // 10% tolerance
            $warnings[] = "Your selection results in an average of " . round($avgHourlyCarbsConsumed) . "g carbs/hr. This may exceed your typical tolerance ({$this->getMaxCarbsPerHour($user)}g/hr).";
        }


        foreach ($remainingQuantities as $productIdKey => $qty) { // productIdKey can be int ID or 'WATER'
            if ($qty > 0 && $productIdKey !== 'WATER') {
                $productInstance = $processedCarryList->get($productIdKey); // $processedCarryList is keyed by ID or 'WATER'

                if ($productInstance) {
                    $productName = $productInstance->name;
                    $unit = $productInstance->serving_size_units ?? 'serving';
                    $baseUnit = trim(preg_replace('/^[0-9.]+\s*/', '', (string)$unit));
                    if (empty($baseUnit)) $baseUnit = (string)$unit;
                    $pluralUnit = ($qty === 1 || empty($baseUnit)) ? $baseUnit : Str::plural($baseUnit);

                    $leftoversFormatted[] = [ // Create a numerically indexed array for Blade
                        'quantity' => $qty,
                        'name' => $productName,
                        'unit' => $pluralUnit
                    ];
                } else {
                    Log::warning("Selection Mode: Could not find product instance for leftover ID: {$productIdKey}");
                }
            }
        }

        Log::info("PlanGenerator (Selection Mode): END", ['items' => count($schedule), 'warnings' => count($warnings), 'leftovers' => count($leftoversFormatted), 'leftover_details' => $leftoversFormatted]);
        return [
            'schedule' => $schedule,
            'warnings' => $warnings,
            'leftovers' => $leftoversFormatted,
            'actual_totals' => $cumulativeConsumed,    // Actual nutrients from the generated schedule
            'recommended_totals' => $finalTargets // Recommended nutrients for the full activity duration
        ];
    }



    /**
     * Calculates nutrients for scheduling ONE UNIT of a product.
     * For SelectionMode, it's more lenient with caps.
     */
    protected function calculateNutrientsForOneItem(Product $product, array $needs, array $consumptionForCapCheck, int $maxCarbsHr, int $maxFluidHr, bool $isSelectionMode = false): ?array
    {
        $itemCarbs = 0;
        $itemFluid = 0;
        $itemSodium = 0;
        $qtyDesc = $product->serving_size_description ?? "1 serving";
        $notes = "Consume {$qtyDesc} of {$product->name}";
        $instruction = 'consume';

        // Define cap multipliers for selection mode (e.g., allow 10-20% overage if user selected it)
        $carbCapMultiplier = $isSelectionMode ? 1.20 : 1.0;
        $fluidCapMultiplier = $isSelectionMode ? 1.10 : 1.0;

        $effectiveMaxCarbsHr = $maxCarbsHr * $carbCapMultiplier;
        $effectiveMaxFluidHr = $maxFluidHr * $fluidCapMultiplier;


        if ($product->type === Product::TYPE_DRINK_MIX) {
            $pCarbsPerStd = $product->carbs_g ?? 0;
            $pSodiumPerStd = $product->sodium_mg ?? 0;
            $pStdVolMl = $product->final_drink_volume_per_serving_ml ?? $product->serving_size_ml; // Use serving_size_ml as fallback

            if (!$pStdVolMl || $pStdVolMl <= 0) {
                Log::debug("Drink mix {$product->name} has no pStdVolMl.");
                return null;
            }

            $fluidRoomThisInterval = self::MAX_FLUID_PER_INTERVAL_ML; // This is a hard cap per interval usually
            $fluidRoomHourly = $effectiveMaxFluidHr - $consumptionForCapCheck['fluid'];

            $potentialVolToConsume = floor(min($needs['fluid'], $fluidRoomThisInterval, $fluidRoomHourly));

            // In selection mode, if user picked it, allow scheduling even if need is slightly less than min,
            // as long as it's > 0 and they picked it.
            if ($potentialVolToConsume < ($isSelectionMode ? 50 : self::MIN_FLUID_SCHEDULE_ML) && $needs['fluid'] > 0) {
                if ($isSelectionMode && $needs['fluid'] > 0) {
                    $potentialVolToConsume = max(50, $needs['fluid']); // Try to take at least 50ml or what's needed if less
                    $potentialVolToConsume = floor(min($potentialVolToConsume, $fluidRoomThisInterval, $fluidRoomHourly)); // Re-check caps
                } else {
                    Log::debug("Drink mix {$product->name} not enough fluid need/room: {$potentialVolToConsume}ml");
                    return null;
                }
            }
            if ($potentialVolToConsume <= 0) {
                Log::debug("Drink mix {$product->name} potential vol is <=0");
                return null;
            }


            $itemFluid = $potentialVolToConsume;
            $proportion = $itemFluid / $pStdVolMl;
            $itemCarbs = $pCarbsPerStd * $proportion;
            $itemSodium = $pSodiumPerStd * $proportion;

            if (($consumptionForCapCheck['carbs'] + $itemCarbs) > $effectiveMaxCarbsHr) {
                Log::debug("Rejecting drink mix {$product->name}: adds {$itemCarbs} carbs, exceeds cap {$effectiveMaxCarbsHr}.", ['recent' => $consumptionForCapCheck['carbs']]);
                return null;
            }

            $instruction = 'drink';
            $qtyDesc = round($itemFluid) . "ml";
            $notes = "Mix {$product->name} (approx. " . round($proportion, 1) . " of {$product->serving_size_description}) and drink {$itemFluid}ml.";
        } else if ($product->type === Product::TYPE_PLAIN_WATER || $product->id === 'WATER') {
            $itemCarbs = 0;
            $itemSodium = 0;
            $fluidRoomThisInterval = self::MAX_FLUID_PER_INTERVAL_ML;
            $fluidRoomHourly = $effectiveMaxFluidHr - $consumptionForCapCheck['fluid'];
            $potentialVolToConsume = floor(min($needs['fluid'], $fluidRoomThisInterval, $fluidRoomHourly));

            if ($potentialVolToConsume < ($isSelectionMode ? 50 : self::MIN_FLUID_SCHEDULE_ML) && $needs['fluid'] > 0) {
                if ($isSelectionMode && $needs['fluid'] > 0) {
                    $potentialVolToConsume = max(50, $needs['fluid']);
                    $potentialVolToConsume = floor(min($potentialVolToConsume, $fluidRoomThisInterval, $fluidRoomHourly));
                } else {
                    Log::debug("Plain water not enough fluid need/room: {$potentialVolToConsume}ml");
                    return null;
                }
            }
            if ($potentialVolToConsume <= 0) {
                Log::debug("Plain water potential vol is <=0");
                return null;
            }


            $itemFluid = $potentialVolToConsume;
            $instruction = 'drink';
            $qtyDesc = round($itemFluid) . "ml";
            $notes = "Drink {$qtyDesc} Plain Water.";
        } else { // Gels, Bars, Chews, Real Food etc. (Treat as one discrete serving unit)
            $itemCarbs = $product->carbs_g ?? 0;
            $itemSodium = $product->sodium_mg ?? 0;
            $itemFluid = $product->provided_fluid_per_serving_ml ?? 0;

            if (($consumptionForCapCheck['carbs'] + $itemCarbs) > $effectiveMaxCarbsHr) {
                Log::debug("Rejecting solid {$product->name}: adds {$itemCarbs} carbs, exceeds cap {$effectiveMaxCarbsHr}.", ['recent' => $consumptionForCapCheck['carbs']]);
                return null;
            }
            if (($consumptionForCapCheck['fluid'] + $itemFluid) > $effectiveMaxFluidHr) { // Less likely for solids
                Log::debug("Rejecting solid {$product->name}: adds {$itemFluid} fluid, exceeds cap {$effectiveMaxFluidHr}.", ['recent' => $consumptionForCapCheck['fluid']]);
                return null;
            }

            // In selection mode, if user picked this item, and there's *any* carb need, schedule it.
            // Or if it's the only thing that provides sodium and sodium is needed.
            if ($isSelectionMode) {
                $providesSomethingNeeded = (
                    ($needs['carbs'] >= self::MIN_CARB_NEED_G && $itemCarbs > 0) ||
                    ($needs['sodium'] >= self::MIN_SODIUM_NEED_MG && $itemSodium > 0) ||
                    ($needs['fluid'] >= self::MIN_FLUID_NEED_ML && $itemFluid > 0)
                );
                if (!$providesSomethingNeeded && ($itemCarbs > 0 || $itemSodium > 0 || $itemFluid > 0)) {
                    // If it doesn't meet a *significant current need* but user picked it,
                    // and it's not totally devoid of nutrients, consider allowing it IF no other better choice from user list.
                    // This part is tricky. For now, if it doesn't meet a current significant need, we might still skip it,
                    // relying on the outer loop to pick it if it becomes a priority.
                    // Let's keep it simple: if it passes caps, and user selected it, the outer loop will decide if it's the "best" of the user's choices.
                }
            }


            $instruction = 'consume';
            $qtyDesc = "1 " . ($product->serving_size_units ?: 'unit'); // e.g. "1 bar", "1 packet"
            $notes = "Consume {$qtyDesc} of {$product->name}.";
            if ($itemFluid == 0 && $product->type !== Product::TYPE_DRINK_MIX && $product->type !== Product::TYPE_PLAIN_WATER) {
                $notes .= " Consider drinking ~" . self::WATER_PER_SOLID_ML . "ml water with this.";
            }
        }

        // If item provides effectively zero nutrients, don't schedule it, unless it's water and fluid is needed.
        if ($itemCarbs == 0 && $itemFluid == 0 && $itemSodium == 0 && !($product->type === Product::TYPE_PLAIN_WATER || $product->id === 'WATER')) {
            Log::debug("Item {$product->name} provides no nutrients after calculation.");
            return null;
        }


        return [
            'carbs' => $itemCarbs,
            'fluid' => $itemFluid,
            'sodium' => $itemSodium,
            'desc' => $qtyDesc,
            'notes' => $notes,
            'instruction' => $instruction
        ];
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
            if ($priority === 'none' || $relativeSodiumDeficit > $maxRelativeDeficit) {
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

        // Log the history just BEFORE filtering
        Log::debug("calculateRecentConsumption - History BEFORE filter for time {$this->formatTime($currentTimeOffset)}:", $consumptionHistory);

        $consumptionHistory = array_filter($consumptionHistory, function ($item) use ($windowStart, &$recentTotals) {
            // VERY IMPORTANT: Check if $item is an array and has the 'time' key before accessing it
            if (!is_array($item) || !isset($item['time'])) {
                Log::warning("calculateRecentConsumption - Invalid item in consumptionHistory:", ['item_data' => $item]);
                return false; // Discard invalid item
            }

            // Log each item being considered for filtering
            Log::debug("calculateRecentConsumption - Filtering item:", [
                'item_time' => $item['time'],
                'window_start' => $windowStart,
                'is_within_window' => ($item['time'] > $windowStart)
            ]);

            if ($item['time'] > $windowStart) { // The check that can cause error if $item['time'] is not set
                $recentTotals['carbs'] += $item['carbs'] ?? 0; // Add null coalescing for safety
                $recentTotals['fluid'] += $item['fluid'] ?? 0;
                $recentTotals['sodium'] += $item['sodium'] ?? 0;
                return true; // Keep item
            }
            return false; // Discard item
        });

        $consumptionHistory = array_values($consumptionHistory); // Re-index array after filtering
        Log::debug("calculateRecentConsumption - History AFTER filter:", $consumptionHistory);
        Log::debug("calculateRecentConsumption - Recent Totals:", $recentTotals);

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
            'id' => 'WATER',
            'name' => 'Plain Water',
            'type' => Product::TYPE_PLAIN_WATER, // Use constant
            'carbs_g' => 0,
            'sodium_mg' => 0,
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
