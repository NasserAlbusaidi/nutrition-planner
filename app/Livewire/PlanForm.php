<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\NutritionCalculator;
use App\Services\PlanGenerator;
use App\Services\StravaService;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Livewire\Component;
use phpGPX\phpGPX;
use Illuminate\Validation\ValidationException; // For form validation errors
use Exception; // For general exceptions

class PlanForm extends Component
{
    // --- Component Properties ---
    // Route parameters
    public string $routeId;
    public string $routeName;
    public float $routeDistanceKm;
    public float $routeElevationM;
    public string $routeSource = 'strava';
    public ?float $routeStartLat = null;
    public ?float $routeStartLng = null;
    public string $fetchedPolyline = '';

    // Form properties
    public $planned_start_datetime;
    public $planned_intensity;
    public ?Collection $availablePantry = null; // Initialize as null, load on demand
    public array $selectedProducts = []; // [product_id => quantity]

    // Service instances (optional, can be injected directly in methods)
    protected StravaService $stravaService;
    protected WeatherService $weatherService;
    protected NutritionCalculator $calculator;
    protected PlanGenerator $generator;

    // UI Data
    public array $intensityOptions = [
        'easy' => 'Easy (<65% FTP)',
        'endurance' => 'Endurance (Zone 2, 65-75% FTP)',
        'tempo' => 'Tempo (Zone 3, 76-90% FTP)',
        'threshold' => 'Threshold (Zone 4, 91-105% FTP)',
        'race_pace' => 'Race Pace (~Threshold)',
        'steady_group_ride' => 'Steady Group Ride (~High Z2/Low Z3)',
    ];

    // --- Validation ---
    protected function rules(): array
    {
        return [
            'planned_start_datetime' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:now'],
            'planned_intensity' => ['required', Rule::in(array_keys($this->intensityOptions))],
            'selectedProducts.*' => ['nullable', 'integer', 'min:0', 'max:99'],
        ];
    }

    protected $validationAttributes = [
        'selectedProducts.*' => 'product quantity',
    ];

    // --- Lifecycle Hooks & Service Injection ---
    public function boot(
        StravaService $stravaService,
        WeatherService $weatherService,
        NutritionCalculator $calculator,
        PlanGenerator $generator
    ) {
        // Inject services once
        $this->stravaService = $stravaService;
        $this->weatherService = $weatherService;
        $this->calculator = $calculator;
        $this->generator = $generator;
    }

    public function mount(string $routeId, string $routeName, float $distance, float $elevation, ?string $source = null, ?float $startLat = null, ?float $startLng = null)
    {
        $this->routeId = $routeId;
        $this->routeName = urldecode($routeName);
        $this->routeDistanceKm = $distance;
        $this->routeElevationM = $elevation;
        $this->planned_start_datetime = Carbon::now()->addDay()->setHour(8)->setMinute(0)->setSecond(0)->format('Y-m-d\TH:i');
        $this->routeSource = $source ?? 'strava';
        $this->routeStartLat = $startLat;
        $this->routeStartLng = $startLng;

        Log::info('PlanForm Mounted:', $this->getRouteContext());

        $this->_fetchPreviewPolyline(); // Extracted logic
        $this->loadAvailablePantry(); // Load pantry on mount
    }

    // --- Core Plan Generation Orchestration ---

    public function generatePlan()
    {
        $user = Auth::user();
        if (!$user) {
            return $this->_handleError('Authentication required to generate a plan.');
        }

        try {
            // 1. Validate Input
            $validatedData = $this->validate();
            $startTime = Carbon::parse($validatedData['planned_start_datetime']);
            $intensity = $validatedData['planned_intensity'];

            // 2. Prepare Context (gather all needed info)
            $planContext = $this->_preparePlanContext($user, $startTime, $intensity);
            if (!$planContext['coordinates'] && !in_array($this->routeSource, ['gpx', 'manual'])) {
                 return $this->_handleError('Could not determine starting location for weather data.');
            }

            // 3. Generate Hourly Targets
            $hourlyTargets = $this->_calculateNutritionTargets($user, $intensity, $planContext['hourlyForecast'], $planContext['durationSeconds']);

            // 4. Prepare Generator Input (Decide mode, get products)
            $generatorInput = $this->_prepareGenerationInput($user);

            // 5. Run Plan Generation
            $generationResult = $this->_runPlanGeneration(
                $user,
                $planContext['durationSeconds'],
                $hourlyTargets,
                $generatorInput['mode'],
                $generatorInput['products'] // Pass pantry or user selection
            );

            // 6. Calculate Final Totals for Saving
            $actualTotals = $generationResult['actual_totals'];
            $recommendedTotals = $this->generator->getRecommendedTotalsForDuration($hourlyTargets, $planContext['durationSeconds']);

            // 7. Save Plan & Items (Pass context, results, and totals)
            $newPlan = $this->_savePlanAndItems(
                $user,
                $planContext, // Contains start time, duration, coords, weather summary etc.
                $validatedData, // Contains intensity
                $generationResult, // Contains schedule items, notes, warnings
                $actualTotals,
                $recommendedTotals,
                $hourlyTargets // Pass calculated hourly targets to save them
            );

            // 8. Redirect on Success
            session()->flash('message', 'Nutrition plan generated successfully!');
            if (!empty($generationResult['warnings'])) {
                session()->flash('plan_warning', implode('; ', $generationResult['warnings']));
            }
             if (!empty($generationResult['leftovers'])) {
                 session()->flash('plan_info', 'Note: Some selected items were not fully scheduled.');
             }
            return redirect()->route('plans.show', $newPlan->id);

        } catch (ValidationException $e) {
            // Validation errors are handled automatically by Livewire, but we catch to prevent general catch block
            throw $e;
        } catch (\Exception $e) {
            // Catch specific custom exceptions or general ones
             Log::error("PlanForm: CRITICAL ERROR during plan generation", [
                 'userId' => $user->id ?? 'N/A',
                 'exception_class' => get_class($e),
                 'message' => $e->getMessage(),
                 'file' => $e->getFile(),
                 'line' => $e->getLine(),
                 'trace' => $e->getTraceAsString() // More detail for debugging
             ]);
             return $this->_handleError('A critical server error occurred during plan generation: ' . $e->getMessage() . ' Please try again or contact support.');
        }
    }

    // --- Private Helper Methods for generatePlan ---

    /**
     * Gathers and calculates initial context data needed for the plan.
     */
    private function _preparePlanContext(User $user, Carbon $startTime, string $intensity): array
    {
        $coordinates = $this->_getCoordinatesForWeather($user);
        $durationSeconds = $this->_estimateDurationSeconds(); // Use component props

        list($hourlyForecast, $weatherSummary) = $this->_fetchWeatherForecast($coordinates, $startTime, $durationSeconds);

        return [
            'startTime' => $startTime,
            'coordinates' => $coordinates,
            'durationSeconds' => $durationSeconds,
            'hourlyForecast' => $hourlyForecast,
            'weatherSummary' => $weatherSummary,
        ];
    }

     /**
     * Determines starting coordinates for weather based on route source.
     * @throws \Exception if Strava GPX fetch fails.
     */
    private function _getCoordinatesForWeather(User $user): ?array
    {
        $coordinates = null;
        if ($this->routeSource === 'strava') {
            Log::info("PlanForm: Getting Strava GPX for weather. Route: {$this->routeId}");
            $gpxContent = $this->stravaService->getRouteGpx($user, $this->routeId);
            if (!$gpxContent) {
                // Throwing an exception is better than session flash here
                throw new \Exception('Could not retrieve Strava route data (GPX) for weather.');
            }
            $coordinates = $this->_parseCoordinatesFromGpx($gpxContent);
        } elseif ($this->routeSource === 'gpx' && $this->routeStartLat && $this->routeStartLng) {
            $coordinates = ['latitude' => $this->routeStartLat, 'longitude' => $this->routeStartLng];
            Log::info("PlanForm: Using provided GPX coordinates for weather.", $coordinates);
        } else {
            Log::warning("PlanForm: Cannot determine coordinates for weather.", ['source' => $this->routeSource]);
        }
        return $coordinates;
    }

    /**
     * Parses GPX content to find the first point's coordinates.
     */
    private function _parseCoordinatesFromGpx(string $gpxContent): ?array
    {
        try {
            $gpx = new phpGPX();
            $file = $gpx->parse($gpxContent);
            // Simplify point finding logic
            $firstPoint = $file->tracks[0]->segments[0]->points[0]
                ?? $file->routes[0]->points[0]
                ?? $file->waypoints[0]
                ?? null;

            if ($firstPoint) {
                return ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
            }
            Log::warning("Could not find starting point in GPX.", ['routeId' => $this->routeId]);
            return null;
        } catch (\Exception $e) {
            Log::error("Error parsing GPX.", ['routeId' => $this->routeId, 'message' => $e->getMessage()]);
            return null;
        }
    }

     /**
     * Fetches the weather forecast.
     */
    private function _fetchWeatherForecast(?array $coordinates, Carbon $startTime, int $durationSeconds): array
    {
        $hourlyForecast = null;
        $weatherSummary = "Weather data unavailable."; // Default

        if ($coordinates) {
            Log::info("Fetching weather forecast.", $coordinates);
            $hourlyForecast = $this->weatherService->getHourlyForecast(
                $coordinates['latitude'],
                $coordinates['longitude'],
                $startTime,
                $durationSeconds
            );
            if (!empty($hourlyForecast)) {
                 $avgTemp = round(collect($hourlyForecast)->avg('temp_c') ?? 0, 1);
                 $avgHumidity = round(collect($hourlyForecast)->avg('humidity') ?? 0);
                 $weatherSummary = "Avg Temp: {$avgTemp}°C, Avg Humidity: {$avgHumidity}%";
                 Log::info("Weather fetched: {$weatherSummary}");
            } else {
                 Log::warning("Weather forecast fetch failed or returned empty.");
                 $hourlyForecast = null; // Ensure it's null if empty
            }
        } else {
             Log::warning("Skipping weather fetch (no coordinates).");
        }
        return [$hourlyForecast, $weatherSummary];
    }

     /**
     * Calculates hourly nutrition targets.
     * @throws \Exception if calculation fails.
     */
    private function _calculateNutritionTargets(User $user, string $intensity, ?array $hourlyForecast, int $durationSeconds): array
    {
        if ($durationSeconds <= 0) {
            throw new \Exception('Invalid duration provided for target calculation.');
        }
        $hourlyTargets = $this->calculator->calculateHourlyTargets($user, $intensity, $hourlyForecast, $durationSeconds);
        if (empty($hourlyTargets)) {
            throw new \Exception('Could not calculate base nutrition targets. Check profile settings.');
        }
        // Ensure targets are 0-indexed (calculator should ideally guarantee this)
         if (!isset($hourlyTargets[0])) {
             Log::warning("Re-indexing hourly targets as they were not 0-indexed.");
             $hourlyTargets = array_values($hourlyTargets);
         }

        Log::info("Nutrition targets calculated.", ['hours' => count($hourlyTargets)]);
        return $hourlyTargets;
    }

    /**
     * Prepares input for the PlanGenerator based on user selections.
     * @throws \Exception if pantry cannot be loaded or selection mapping fails.
     */
    private function _prepareGenerationInput(User $user): array
    {
        $userCarryListInput = collect($this->selectedProducts ?? [])->filter(fn($qty) => is_numeric($qty) && $qty > 0);

        // Ensure pantry is loaded
        if ($this->availablePantry === null) {
            $this->loadAvailablePantry();
        }
         if ($this->availablePantry === null || $this->availablePantry->isEmpty()) {
             throw new \Exception('Cannot access pantry products for plan generation.');
         }


        if ($userCarryListInput->isNotEmpty()) {
             $userCarryCollection = $userCarryListInput->mapWithKeys(
                 fn($qty, $id) => ($product = $this->availablePantry->firstWhere('id', $id)) ? [$id => ['product' => $product, 'quantity' => (int)$qty]] : []
             )->filter();

            if ($userCarryCollection->isEmpty()) {
                // This implies selected IDs didn't match loaded pantry - data inconsistency
                throw new \Exception('Selected products could not be matched to pantry. Please refresh.');
            }
            Log::info("PlanForm: Preparing 'User Selection' input.");
            return ['mode' => 'selection', 'products' => $userCarryCollection];
        } else {
            Log::info("PlanForm: Preparing 'Automatic' input.");
             return ['mode' => 'automatic', 'products' => $this->availablePantry];
        }
    }

     /**
     * Calls the appropriate PlanGenerator method.
     * @throws \Exception if generator returns an error state.
     */
    private function _runPlanGeneration(User $user, int $durationSeconds, array $hourlyTargets, string $mode, Collection $products): array
    {
        Log::info("PlanForm: Running generator.", ['mode' => $mode]);
        if ($mode === 'selection') {
             $generatorResult = $this->generator->generateScheduleFromSelection($user, $durationSeconds, $hourlyTargets, $products);
             // Result includes 'schedule', 'warnings', 'leftovers', 'actual_totals', 'recommended_totals'
             if (empty($generatorResult['schedule']) && empty($generatorResult['warnings'])) {
                throw new \Exception('Could not generate schedule using ONLY the selected products. Try different items/quantities or use automatic generation.');
            }
            return $generatorResult;
        } else {
             // Automatic mode expects schedule or error
            $scheduleItems = $this->generator->generateSchedule($user, $durationSeconds, $hourlyTargets, $products);
             if (isset($scheduleItems[0]['error'])) {
                throw new \Exception('Automatic schedule generation error: ' . $scheduleItems[0]['error']);
            }
             if (empty($scheduleItems)) {
                 throw new \Exception('Could not automatically generate a schedule with available products.');
             }
            // For automatic, we need to calculate actual totals separately
             $actualTotals = [
                'carbs' => collect($scheduleItems)->sum('calculated_carbs_g'),
                'fluid' => collect($scheduleItems)->sum('calculated_fluid_ml'),
                'sodium' => collect($scheduleItems)->sum('calculated_sodium_mg'),
            ];
             return [
                 'schedule' => $scheduleItems,
                 'warnings' => [], // Auto mode doesn't currently generate same warnings array
                 'leftovers' => [], // No concept of leftovers here
                 'actual_totals' => $actualTotals,
                // 'recommended_totals' is calculated later/outside
             ];
        }
    }

     /**
     * Saves the plan and its items within a database transaction.
     * @throws \Exception on failure.
     */
    private function _savePlanAndItems(
        User $user,
        array $planContext,
        array $validatedData,
        array $generationResult,
        array $actualTotals,
        array $recommendedTotals,
        array $hourlyTargets
    ): Plan
    {
        $newPlan = null;
        $planTitle = 'Plan for ' . $this->routeName . ' on ' . $planContext['startTime']->format('M j, Y');
        $planNotes = implode("\n\n", $generationResult['warnings'] ?? []); // Assuming warnings are primary notes now

        try {
            DB::transaction(function () use (
                $user, $planContext, $validatedData, $generationResult, $actualTotals, $recommendedTotals, $hourlyTargets, $planTitle, $planNotes, &$newPlan
            ) {
                $estimatedPower = $this->calculator->estimateAveragePower($user, $validatedData['planned_intensity']);

                $planData = [
                    'name' => $planTitle,
                    'user_id' => $user->id,
                    'planned_start_time' => $planContext['startTime'],
                    'planned_intensity' => $validatedData['planned_intensity'],
                    'estimated_duration_seconds' => $planContext['durationSeconds'],
                    'estimated_avg_power_watts' => $estimatedPower ? round($estimatedPower) : null,
                    'estimated_total_carbs_g' => round($actualTotals['carbs'] ?? 0),
                    'estimated_total_fluid_ml' => round($actualTotals['fluid'] ?? 0),
                    'estimated_total_sodium_mg' => round($actualTotals['sodium'] ?? 0),
                    'recommended_total_carbs_g' => round($recommendedTotals['carbs'] ?? 0),
                    'recommended_total_fluid_ml' => round($recommendedTotals['fluid'] ?? 0),
                    'recommended_total_sodium_mg' => round($recommendedTotals['sodium'] ?? 0),
                    'hourly_targets_data' => $hourlyTargets,
                    'weather_summary' => $planContext['weatherSummary'],
                    'source' => $this->routeSource,
                    'plan_notes' => empty(trim($planNotes)) ? null : $planNotes, // Store null if empty after trimming
                    // --- Add Route/Coord Info ---
                    'strava_route_id' => ($this->routeSource === 'strava') ? $this->routeId : null,
                    'strava_route_name' => ($this->routeSource === 'strava') ? $this->routeName : null,
                    'start_latitude' => $planContext['coordinates']['latitude'] ?? null,
                    'start_longitude' => $planContext['coordinates']['longitude'] ?? null,
                    'estimated_distance_km' => $this->routeDistanceKm,
                    'estimated_elevation_m' => $this->routeElevationM,
                ];

                Log::debug("PlanForm: Data for Plan::create", $planData);
                $newPlan = Plan::create($planData);
                if (!$newPlan) throw new \Exception("Plan model creation failed.");

                // Save Plan Items
                $scheduleItemsData = $generationResult['schedule'] ?? [];
                if (!empty($scheduleItemsData)) {
                    $itemsToSave = [];
                    foreach ($scheduleItemsData as $item) {
                        if (!is_array($item) || !isset($item['time_offset_seconds'])) {
                             Log::error("PlanForm: Invalid schedule item skipped during save.", ['item_data' => $item]);
                             continue;
                        }
                         $itemsToSave[] = [
                             // 'plan_id' => $newPlan->id, // Set automatically by createMany relationship
                             'time_offset_seconds' => (int)$item['time_offset_seconds'],
                             'instruction_type' => $item['instruction_type'] ?? 'consume',
                             'product_id' => $item['product_id'] ?? null,
                             'product_name' => $item['product_name'] ?? ($item['product_name_override'] ?? null),
                             'product_name_override' => $item['product_name_override'] ?? null,
                             'quantity_description' => $item['quantity_description'] ?? 'N/A',
                             'calculated_carbs_g' => $item['calculated_carbs_g'] ?? 0,
                             'calculated_fluid_ml' => $item['calculated_fluid_ml'] ?? 0,
                             'calculated_sodium_mg' => $item['calculated_sodium_mg'] ?? 0,
                             'notes' => $item['notes'] ?? null,
                             'created_at' => now(), // Optional: handled by Eloquent timestamps
                             'updated_at' => now(), // Optional: handled by Eloquent timestamps
                         ];
                    }
                    if (!empty($itemsToSave)) {
                         $newPlan->items()->createMany($itemsToSave);
                         Log::info("PlanForm: Saved " . count($itemsToSave) . " items for plan ID: {$newPlan->id}");
                    }
                }
            }); // End Transaction
        } catch (\Throwable $e) {
            // Rethrow if transaction failed, allowing the main catch block in generatePlan to handle it
             Log::error("PlanForm: Database transaction failed.", ['message' => $e->getMessage()]);
            throw new \Exception("Failed to save plan due to a database error.", 0, $e); // Chain exception
        }


        if (!$newPlan || !$newPlan->exists) {
             Log::error("PlanForm: Plan variable null or not saved after transaction attempt.", ['userId' => $user->id]);
            throw new \Exception('Failed to finalize plan saving after transaction.');
        }
        return $newPlan;
    }

     /**
     * Handles error reporting to the user.
     */
    private function _handleError(string $message): void
    {
        session()->flash('error', $message);
        // Optionally log the user-facing error message as well
        // Log::warning("PlanForm: User Error - {$message}");
    }


    // --- Utility & Helper Methods ---

    /**
     * Fetches the polyline for the map preview.
     */
    private function _fetchPreviewPolyline(): void
    {
        if ($this->routeSource !== 'strava' || !Auth::check()) {
            $this->fetchedPolyline = '';
            return;
        }

        Log::info("PlanForm: Fetching preview polyline for Strava Route ID {$this->routeId}");
        try {
            $allRoutes = $this->stravaService->getUserRoutes(Auth::user());
            $foundPolyline = null;
            if ($allRoutes !== null) {
                 $routeDetails = collect($allRoutes)->first(function ($value) {
                    return (string)($value['id_str'] ?? $value['id']) === (string)$this->routeId;
                 });
                if ($routeDetails) {
                     $foundPolyline = $routeDetails['map']['summary_polyline'] ?? $routeDetails['summary_polyline'] ?? null;
                }
            }
            $this->fetchedPolyline = $foundPolyline ?? '';
            if (empty($this->fetchedPolyline)) {
                 Log::warning("PlanForm: Could not find polyline for Strava Route ID {$this->routeId} for map preview.");
            } else {
                Log::info("PlanForm: Preview polyline fetched.", ['length' => strlen($this->fetchedPolyline)]);
            }
        } catch (\Exception $e) {
             Log::error("PlanForm: Error fetching preview polyline", ['message' => $e->getMessage()]);
             $this->fetchedPolyline = '';
        }

    }

    /**
     * Loads user's pantry + global products into the component state.
     */
    public function loadAvailablePantry(): void
    {
        if (!Auth::check()) {
             $this->availablePantry = collect();
             $this->selectedProducts = [];
             return;
        }
        $user = Auth::user();
        try {
            $this->availablePantry = Product::where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
             })->where('is_active', true)->get();

            if ($this->availablePantry->isNotEmpty()) {
                 $this->selectedProducts = $this->availablePantry->mapWithKeys(fn($p) => [$p->id => 0])->toArray();
            } else {
                $this->availablePantry = collect();
                $this->selectedProducts = [];
                // Optional: Flash warning if pantry is unexpectedly empty
                // session()->flash('info', 'Your product pantry appears empty. Add products for better planning.');
            }
        } catch (\Exception $e) {
             Log::error("PlanForm: Failed to load pantry", ['userId' => $user->id, 'message' => $e->getMessage()]);
             $this->availablePantry = collect();
             $this->selectedProducts = [];
             session()->flash('error', 'Could not load product pantry.');
        }

    }

    /**
     * Get icon based on Product type.
     */
    public function getItemIconFromProduct(Product $productInstance): string
    {
        $productTypeIcons = [
             Product::TYPE_DRINK_MIX => 'heroicon-o-beaker',
             Product::TYPE_GEL => 'heroicon-o-bolt',
             Product::TYPE_ENERGY_CHEW => 'heroicon-o-cube',
             Product::TYPE_ENERGY_BAR => 'heroicon-s-bars-3-bottom-left',
             Product::TYPE_REAL_FOOD => 'heroicon-o-cake',
             Product::TYPE_HYDRATION_TABLET => 'heroicon-o-adjustments-horizontal',
             Product::TYPE_PLAIN_WATER => 'heroicon-o-beaker', // Match drink mix?
             Product::TYPE_RECOVERY_DRINK => 'heroicon-o-arrows-pointing-in',
            'unknown' => 'heroicon-o-tag',
            'default' => 'heroicon-o-question-mark-circle',
        ];
         return $productTypeIcons[$productInstance->type] ?? $productTypeIcons['default'];
    }

     /**
     * Returns initial route data for logging context.
     */
    private function getRouteContext(): array
    {
        return [
            'routeId' => $this->routeId,
            'name' => $this->routeName,
            'distanceKm' => $this->routeDistanceKm,
            'elevationM' => $this->routeElevationM,
            'source' => $this->routeSource,
            'startLat' => $this->routeStartLat,
            'startLng' => $this->routeStartLng,
        ];
    }

    /**
     * Uses component properties to estimate duration.
     * @throws \Exception if distance is zero or negative.
     */
    private function _estimateDurationSeconds(): int
    {
        if ($this->routeDistanceKm <= 0) {
            // Distance comes from route selector, should always be positive. If not, it's an error.
            throw new \Exception("Invalid route distance ({$this->routeDistanceKm} km) for duration calculation.");
        }

        // Ensure planned_intensity is validated before calling this, or pass it in.
        // Assuming it's called *after* $this->validate() in the main flow.
        $intensityKey = $this->planned_intensity;

        $baseSpeedKph = match ($intensityKey) {
             'easy' => 22, 'endurance' => 26, 'tempo' => 30, 'threshold' | 'race_pace' => 33, 'steady_group_ride' => 28, default => 25,
        };

        // Refined elevation impact - reduce speed by % based on gradient (m per 10km)
        $climbingFactor = $this->routeElevationM / ($this->routeDistanceKm * 10); // e.g., 100m / (10km * 10) = 1 factor unit per 10km
        $speedReduction = 1.0 - min(0.4, $climbingFactor / 50); // Max 40% reduction, ~0.8% per factor unit
        $adjustedSpeedKph = max(10, $baseSpeedKph * $speedReduction); // Min speed 10kph

        $durationHours = $this->routeDistanceKm / $adjustedSpeedKph;
        $durationSeconds = (int) round($durationHours * 3600);

        Log::debug("Duration Estimation:", [
            'distanceKm' => $this->routeDistanceKm,
            'elevationM' => $this->routeElevationM,
            'intensityKey' => $intensityKey,
            'baseSpeedKph' => $baseSpeedKph,
            'climbingFactor' => $climbingFactor,
             'speedReduction' => $speedReduction,
             'adjustedSpeedKph' => $adjustedSpeedKph,
             'durationSeconds' => $durationSeconds
        ]);

        return $durationSeconds;
    }


    // --- Rendering ---
    public function render()
    {
        // Eager load pantry if not already loaded for the view
        if ($this->availablePantry === null && Auth::check()) {
            $this->loadAvailablePantry();
        }
        return view('livewire.plan-form')->layout('layouts.app');
    }
}
