<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Models\Product;
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

class PlanForm extends Component
{
    // Route parameters (received from URL)
    public string $routeId;
    public string $routeName;
    public float $routeDistanceKm;
    public float $routeElevationM;

    public string $routeSource = 'strava'; // Default source
    public ?float $routeStartLat = null;   // For GPX start coords
    public ?float $routeStartLng = null;   // For GPX start coords

    public string $fetchedPolyline = ''; // For map preview on this page

    // Form properties
    public $planned_start_datetime;
    public $planned_intensity;

    public Collection $availablePantry;   // Collection of Product models
    public array $selectedProducts = []; // Structure: [product_id => quantity_selected, ...]

    public $intensityOptions = [
        'easy' => 'Easy (<65% FTP)',
        'endurance' => 'Endurance (Zone 2, 65-75% FTP)',
        'tempo' => 'Tempo (Zone 3, 76-90% FTP)',
        'threshold' => 'Threshold (Zone 4, 91-105% FTP)',
        'race_pace' => 'Race Pace (~Threshold)',
        'steady_group_ride' => 'Steady Group Ride (~High Z2/Low Z3)',
    ];

    protected function rules()
    {
        return [
            'planned_start_datetime' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:now'], // after_or_equal:now is usually better
            'planned_intensity' => ['required', Rule::in(array_keys($this->intensityOptions))],
            'selectedProducts.*' => ['nullable', 'integer', 'min:0', 'max:99'], // Validate quantities

        ];
    }

    protected $validationAttributes = [
        'selectedProducts.*' => 'product quantity', // Friendlier validation messages
    ];

    public function getItemIconFromProduct(Product $productInstance): string // Renamed param for clarity
{
    // Make sure you have these product types defined as constants in your Product model
    $productTypeIcons = [
        Product::TYPE_DRINK_MIX => 'heroicon-o-beaker',
        Product::TYPE_GEL => 'heroicon-o-bolt',
        Product::TYPE_ENERGY_CHEW => 'heroicon-o-cube',
        Product::TYPE_ENERGY_BAR => 'heroicon-s-bars-3-bottom-left',
        Product::TYPE_REAL_FOOD => 'heroicon-o-cake',
        Product::TYPE_HYDRATION_TABLET => 'heroicon-o-adjustments-horizontal',
        Product::TYPE_PLAIN_WATER => 'heroicon-o-beaker', // Or your chosen water icon
        Product::TYPE_RECOVERY_DRINK => 'heroicon-o-arrows-pointing-in',
        'unknown' => 'heroicon-o-tag',
        'default' => 'heroicon-o-question-mark-circle',
    ];
    return $productTypeIcons[$productInstance->type] ?? $productTypeIcons['default'];
}

    public function mount(
        string $routeId,
        string $routeName,
        float $distance,    // This should be in KM as prepared by RouteSelector
        float $elevation,
        StravaService $stravaService, // Keep for Strava source
        ?string $source = null,       // Default to null, handle below
        ?float $startLat = null,
        ?float $startLng = null
    ) {
        $this->routeId = $routeId;
        $this->routeName = urldecode($routeName);
        $this->routeDistanceKm = $distance; // Ensure this is correctly passed in KM
        $this->routeElevationM = $elevation;
        $this->planned_start_datetime = Carbon::now()->addDay()->setHour(8)->setMinute(0)->setSecond(0)->format('Y-m-d\TH:i'); // Default to tomorrow 8 AM

        $this->routeSource = $source ?? 'strava'; // If source isn't passed, assume strava
        $this->routeStartLat = $startLat;
        $this->routeStartLng = $startLng;

        Log::info('PlanForm Mounted:', [
            'routeId' => $this->routeId,
            'name' => $this->routeName,
            'distanceKm' => $this->routeDistanceKm,
            'elevationM' => $this->routeElevationM,
            'source' => $this->routeSource,
            'startLat' => $this->routeStartLat,
            'startLng' => $this->routeStartLng,
        ]);

        // Fetch polyline for map preview on *this PlanForm page* (only for Strava routes)
        if ($this->routeSource === 'strava') {
            Log::info("PlanForm (Strava Source): Fetching polyline for map preview for Strava Route ID {$this->routeId}");
            $user = Auth::user();
            if ($user) {
                $allRoutes = $stravaService->getUserRoutes($user);
                $foundPolyline = null;
                if ($allRoutes !== null) {
                    // Attempt to match Strava ID (which can be numeric or string)
                    $routeDetails = collect($allRoutes)->first(function ($value) {
                        return (string)($value['id_str'] ?? $value['id']) === (string)$this->routeId;
                    });

                    if ($routeDetails) {
                        $foundPolyline = $routeDetails['map']['summary_polyline'] ?? $routeDetails['summary_polyline'] ?? null;
                    }
                }
                if ($foundPolyline) {
                    $this->fetchedPolyline = $foundPolyline;
                    Log::info("PlanForm (Strava Source): Successfully fetched polyline for map preview.", ['length' => strlen($this->fetchedPolyline)]);
                } else {
                    Log::warning("PlanForm (Strava Source): Could not fetch polyline for Strava Route ID {$this->routeId} for map preview.");
                    $this->fetchedPolyline = '';
                    // Optionally flash a message if map preview is critical
                    // session()->flash('warning', 'Map preview for this Strava route is unavailable.');
                }
            } else {
                Log::error("PlanForm (Strava Source): User not authenticated in mount. Cannot fetch Strava polyline.");
                $this->fetchedPolyline = '';
            }
        } elseif ($this->routeSource === 'gpx') {
            Log::info("PlanForm (GPX Source): Route is from GPX. Map preview on this page currently not supported (requires passing/generating polyline from GPX).");
            $this->fetchedPolyline = ''; // No Strava-style polyline readily available here for the map div.
        } else {
            Log::info("PlanForm (Unknown Source): Map preview will not be shown.");
            $this->fetchedPolyline = '';
        }

        $this->loadAvailablePantry();
    }

    // ... estimateDurationSeconds method ...
    protected function estimateDurationSeconds(float $distanceKm, float $elevationM, string $intensityKey): int
    {
        $baseSpeedKph = match ($intensityKey) {
            'easy' => 22,
            'endurance' => 26,
            'tempo' => 30,
            'threshold' => 33,
            'race_pace' => 33,
            'steady_group_ride' => 28,
            default => 25,
        };
        $elevationFactor = ($distanceKm > 0) ? ($elevationM / ($distanceKm * 1000)) * 100 : 0; // Slope percentage approximation (m / m * 100)
        // More typical way: ($elevationM / $distanceKm) / 10 for factor
        $speedReductionFactor = $elevationM / ($distanceKm * 10); // Higher number for more hills, e.g. 1000m over 100km = 1
        $adjustedSpeedKph = max(5, $baseSpeedKph * (1 - ($speedReductionFactor / 50))); // Reduce by up to 2% per "factor unit"

        Log::debug("Duration Estimation:", compact('distanceKm', 'elevationM', 'intensityKey', 'baseSpeedKph', 'speedReductionFactor', 'adjustedSpeedKph'));
        if ($adjustedSpeedKph <= 0) return 3600 * 2; // Default to 2 hours if something is very wrong
        $durationHours = $distanceKm / $adjustedSpeedKph;
        return (int) round($durationHours * 3600);
    }


    // ... getStartCoordinatesFromGpx method (this will be used if source is 'strava') ...
    protected function getStartCoordinatesFromGpx(string $gpxContent): ?array
    {
        try {
            $gpx = new phpGPX();
            $file = $gpx->parse($gpxContent);
            if (!empty($file->tracks) && !empty($file->tracks[0]->segments) && !empty($file->tracks[0]->segments[0]->points)) {
                $firstPoint = $file->tracks[0]->segments[0]->points[0];
                return ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
            } elseif (!empty($file->waypoints) && !empty($file->waypoints[0])) {
                $firstPoint = $file->waypoints[0];
                return ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
            } elseif (!empty($file->routes) && !empty($file->routes[0]->points)) {
                $firstPoint = $file->routes[0]->points[0];
                return ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
            }
            Log::warning("Could not find starting point in GPX data for route ID: {$this->routeId}");
            return null;
        } catch (\Exception $e) {
            Log::error("Error parsing GPX for route ID {$this->routeId}: " . $e->getMessage());
            return null;
        }
    }

    public function loadAvailablePantry()
    {
        if (Auth::check()) { // Only load if user is authenticated
            $user = Auth::user();
            $this->availablePantry = Product::where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            })
                ->where('is_active', true)
                // ->orderBy('type')->orderBy('name') // Order later maybe? Or in Blade?
                ->get();

            // Initialize selectedProducts only if pantry loaded
            if ($this->availablePantry) {
                $this->selectedProducts = $this->availablePantry->mapWithKeys(fn($p) => [$p->id => 0])->toArray();
            } else {
                $this->availablePantry = collect(); // Ensure it's a collection even if empty/error
                $this->selectedProducts = [];
            }
        } else {
            // Handle case where component might load for non-auth user (shouldn't happen if middleware applied)
            $this->availablePantry = collect();
            $this->selectedProducts = [];
        }
    }


    public function generatePlan(
        StravaService $stravaService,
        WeatherService $weatherService,
        NutritionCalculator $calculator,
        PlanGenerator $generator
    ) {
        $validatedData = $this->validate();
        $user = Auth::user();
        if (!$user) {
            session()->flash('error', 'Authentication required to generate a plan.');
            Log::warning("Unauthenticated user tried to generate plan.");
            return; // Stop execution
        }
        $startTime = Carbon::parse($validatedData['planned_start_datetime']);
        $coordinates = null;

        // 2. Determine Coordinates for Weather
        if ($this->routeSource === 'strava') {
            Log::info("GeneratePlan (Strava): Fetching GPX for weather coordinates. Route ID: {$this->routeId}");
            $gpxContent = $stravaService->getRouteGpx($user, $this->routeId);
            if (!$gpxContent) {
                session()->flash('error', 'Could not retrieve Strava route data (GPX) for weather. Please try again.');
                return; // Stop execution
            }
            $coordinates = $this->getStartCoordinatesFromGpx($gpxContent);
        } elseif ($this->routeSource === 'gpx') {
            if ($this->routeStartLat && $this->routeStartLng) {
                $coordinates = ['latitude' => $this->routeStartLat, 'longitude' => $this->routeStartLng];
                Log::info("GeneratePlan (GPX): Using provided start coordinates for weather.", $coordinates);
            } else {
                Log::warning("GeneratePlan (GPX): Start coordinates not available from uploaded GPX file. Weather will use defaults.");
                // Allow to proceed, weather will be skipped or use defaults below
            }
        } else {
            Log::warning("GeneratePlan: Unknown route source '{$this->routeSource}'. Cannot determine coordinates for weather.");
            // Allow to proceed, weather will be skipped or use defaults below
        }

        // Check if coordinates are strictly required (depends on your tolerance for no weather data)
        if (!$coordinates && !in_array($this->routeSource, ['gpx', 'manual'])) { // Allow GPX/manual without coords
            session()->flash('error', 'Could not determine starting location of the route for weather data.');
            return; // Stop execution if needed
        }

        // 3. Estimate Duration
        $distanceKm = $this->routeDistanceKm;
        $elevationM = $this->routeElevationM;
        $durationSeconds = $this->estimateDurationSeconds($distanceKm, $elevationM, $validatedData['planned_intensity']);
        if ($durationSeconds <= 0) {
            session()->flash('error', 'Invalid estimated activity duration (must be positive).');
            Log::error("PlanForm: Estimated duration invalid <= 0", ['duration' => $durationSeconds]);
            return; // Stop execution
        }
        Log::info("PlanForm: Estimated duration {$durationSeconds}s for {$this->routeSource} route.");

        // 4. Fetch Weather
        $hourlyForecast = null;
        $weatherSummary = "Weather data unavailable (no start location provided or fetch error).";
        if ($coordinates) {
            Log::info("Fetching weather forecast for coordinates: ", $coordinates);
            $hourlyForecast = $weatherService->getHourlyForecast($coordinates['latitude'], $coordinates['longitude'], $startTime, $durationSeconds);
            if ($hourlyForecast !== null && !empty($hourlyForecast)) {
                $avgTemp = round(collect($hourlyForecast)->avg('temp_c') ?? 0, 1);
                $avgHumidity = round(collect($hourlyForecast)->avg('humidity') ?? 0);
                $weatherSummary = "Avg Temp: {$avgTemp}°C, Avg Humidity: {$avgHumidity}%";
                Log::info("Weather fetched: {$weatherSummary}");
            } else {
                Log::warning("Weather forecast fetch failed or empty. Plan uses default conditions.");
            }
        } else {
            Log::warning("Skipping weather fetch due to missing coordinates. Plan uses default conditions.");
        }

        // 5. Calculate Base Hourly Targets
        $hourlyTargets = $calculator->calculateHourlyTargets($user, $validatedData['planned_intensity'], $hourlyForecast, $durationSeconds);
        if (empty($hourlyTargets)) {
            session()->flash('error', 'Could not calculate base nutrition targets. Check profile settings.');
            Log::error("PlanForm: NutritionCalculator returned empty targets.", ['user' => $user->id]);
            return; // Stop execution
        }
        Log::info("Base nutrition targets calculated: " . count($hourlyTargets) . " hours.");

        // 6. Generate Schedule Items based on Mode
        $userCarryListInput = collect($this->selectedProducts ?? [])->filter(fn($qty) => is_numeric($qty) && $qty > 0);
        $scheduleItemsData = [];
        $planWarnings = [];
        $leftoverItems = [];
        $planNotes = null;
        $generationMode = 'Automatic';

        if ($userCarryListInput->isNotEmpty()) {
            $generationMode = 'User Selection';
            Log::info("PlanForm: Using {$generationMode} generation.");

            if ($this->availablePantry === null) $this->loadAvailablePantry(); // Defensive load if mount didn't run/work
            if ($this->availablePantry === null || $this->availablePantry->isEmpty()) {
                session()->flash('error', 'Error accessing pantry products for plan generation.');
                Log::error("PlanForm: availablePantry empty/null during Selection mode generation.", ['user' => $user->id]);
                return; // Stop execution
            }

            $userCarryCollection = $userCarryListInput->mapWithKeys(
                fn($qty, $id) => ($product = $this->availablePantry->firstWhere('id', $id)) ? [$id => ['product' => $product, 'quantity' => (int)$qty]] : []
            )->filter();

            if ($userCarryCollection->isEmpty() && $userCarryListInput->isNotEmpty()) {
                // User selected quantities, but none matched products in the pantry (shouldn't happen if UI built from availablePantry)
                session()->flash('error', 'Could not match selected products to pantry. Please refresh.');
                Log::error("PlanForm: User selection present but no matching products found in availablePantry.", ['selection' => $userCarryListInput->toArray()]);
                return; // Stop execution
            }

            // Call generator method for selections
            $generatorResult = $generator->generateScheduleFromSelection($user, $durationSeconds, $hourlyTargets, $userCarryCollection);
            $scheduleItemsData = $generatorResult['schedule'] ?? [];
            $planWarnings = $generatorResult['warnings'] ?? [];
            $leftoverItems = $generatorResult['leftovers'] ?? [];

            if (empty($scheduleItemsData) && empty($planWarnings)) {
                session()->flash('error', 'Could not generate schedule using ONLY the selected products. Try different items/quantities or use automatic generation.');
                Log::warning("PlanForm: generateScheduleFromSelection returned empty schedule and no warnings.", ['user' => $user->id]);
                return; // Stop execution
            }

            // Assemble notes specific to Selection Mode
            if (!empty($planWarnings) || !empty($leftoverItems)) {
                $notes = [];
                if (!empty($planWarnings)) $notes[] = "Warnings:\n- " . implode("\n- ", $planWarnings);
                if (!empty($leftoverItems)) $notes[] = "Items Not Fully Used:\n" . collect($leftoverItems)->map(fn($i, $id) => "- {$i['quantity']} x {$i['name']} (unscheduled)")->implode("\n");
                $planNotes = implode("\n\n", $notes);
            }
        } else {
            // Automatic Mode
            $generationMode = 'Automatic';
            Log::info("PlanForm: Using {$generationMode} generation from full pantry.");
            if ($this->availablePantry === null) $this->loadAvailablePantry();
            if ($this->availablePantry === null || $this->availablePantry->isEmpty()) {
                session()->flash('error', 'No pantry products available for automatic generation.');
                Log::error("PlanForm: availablePantry empty/null during Automatic mode generation.", ['user' => $user->id]);
                return; // Stop execution
            }

            // Call original generator method
            $scheduleItemsData = $generator->generateSchedule($user, $durationSeconds, $hourlyTargets, $this->availablePantry);
            if (isset($scheduleItemsData[0]['error'])) {
                session()->flash('error', 'Automatic schedule error: ' . $scheduleItemsData[0]['error']);
                Log::warning("PlanForm: generateSchedule returned error.", ['error' => $scheduleItemsData[0]['error']]);
                return; // Stop execution
            }
            if (empty($scheduleItemsData)) {
                session()->flash('error', 'Could not automatically generate a schedule with available products.');
                Log::warning("PlanForm: generateSchedule returned empty schedule.", ['user' => $user->id]);
                return; // Stop execution
            }
        } // End conditional generation

        Log::info("PlanForm: Schedule generation step completed.", ['mode' => $generationMode, 'item_count' => count($scheduleItemsData)]);

        // 7. Save Plan & Items
        $newPlan = null;
        try {
            DB::transaction(function () use (
                $user,
                $startTime,
                $validatedData,
                $durationSeconds,
                $calculator,
                $scheduleItemsData,
                $weatherSummary,
                $hourlyTargets,
                $planNotes,
                $coordinates,
                &$newPlan
            ) {
                // ... (calculate scheduled totals: totalCarbs, totalFluid, totalSodium, estimatedPower) ...
                $totalCarbs = collect($scheduleItemsData)->sum('calculated_carbs_g');
                $totalFluid = collect($scheduleItemsData)->sum('calculated_fluid_ml');
                $totalSodium = collect($scheduleItemsData)->sum('calculated_sodium_mg');
                $estimatedPower = $calculator->estimateAveragePower($user, $validatedData['planned_intensity']);

                $planData = [
                    'name' => 'Plan for ' . $this->routeName . ' on ' . $startTime->format('M j, Y'),
                    'user_id' => $user->id,
                    'planned_start_time' => $startTime,
                    'planned_intensity' => $validatedData['planned_intensity'],
                    'estimated_duration_seconds' => $durationSeconds,
                    'estimated_avg_power_watts' => $estimatedPower ? round($estimatedPower) : null,
                    'estimated_total_carbs_g' => round($totalCarbs),
                    'estimated_total_fluid_ml' => round($totalFluid),
                    'estimated_total_sodium_mg' => round($totalSodium),
                    'recommended_total_carbs_g' => $generatorResult['recommended_totals']['carbs'] ?? 0,
                    'recommended_total_fluid_ml' => $generatorResult['recommended_totals']['fluid'] ?? 0,
                    'recommended_total_sodium_mg' => $generatorResult['recommended_totals']['sodium'] ?? 0,
                    'hourly_targets_data' => $hourlyTargets, // Save targets used/for reference
                    'weather_summary' => $weatherSummary,
                    'source' => $this->routeSource,
                    'plan_notes' => $planNotes, // Save generated warnings/leftover info
                ];

                // Add conditional fields
                if ($this->routeSource === 'strava') {
                    $planData['strava_route_id'] = $this->routeId;
                    $planData['strava_route_name'] = $this->routeName;
                }
                if ($coordinates !== null && isset($coordinates['latitude']) && isset($coordinates['longitude'])) {
                    $planData['start_latitude'] = $coordinates['latitude'];
                    $planData['start_longitude'] = $coordinates['longitude'];
                }
                if (Schema::hasColumn('plans', 'estimated_distance_km')) $planData['estimated_distance_km'] = $this->routeDistanceKm;
                if (Schema::hasColumn('plans', 'estimated_elevation_m')) $planData['estimated_elevation_m'] = $this->routeElevationM;

                Log::debug("PlanForm: Data for Plan::create", $planData);
                $newPlan = Plan::create($planData);
                if (!$newPlan) throw new \Exception("Plan model creation failed.");
                Log::info("PlanForm: Plan record created, ID: {$newPlan->id}");

                // Save items (ensure $scheduleItemsData keys are correct)
                if (!empty($scheduleItemsData)) {
                    if (!empty($scheduleItemsData)) {
                        $itemsToSave = []; // Initialize
                        foreach ($scheduleItemsData as $item) {
                            // Ensure $item is an array (should always be if generator is correct)
                            if (!is_array($item)) {
                                Log::error("PlanForm: Non-array item found in scheduleItemsData during mapping", ['item_data' => $item]);
                                continue; // Skip this invalid item
                            }

                            $productName = $item['product_name'] ?? $item['product_name_override'] ?? null;

                            // Explicitly check for time_offset_seconds
                            if (!isset($item['time_offset_seconds'])) {
                                Log::error("PlanForm: Item missing 'time_offset_seconds' from PlanGenerator!", ['item_data' => $item]);
                                // Decide how to handle:
                                // Option A: Skip this item
                                // continue;
                                // Option B: Default it (might cause issues if other items depend on specific timing)
                                // $item['time_offset_seconds'] = 0; // Or another appropriate default based on context
                                // For now, let's make it error out clearly or skip.
                                // Throwing an exception might be better if this key is truly mandatory
                                 throw new \InvalidArgumentException("A schedule item is missing 'time_offset_seconds'.");
                            }


                            $itemsToSave[] = [
                                'plan_id' => $newPlan->id,
                                'time_offset_seconds' => (int)$item['time_offset_seconds'], // Cast to int
                                'instruction_type' => $item['instruction_type'] ?? 'consume',
                                'product_id' => $item['product_id'] ?? null,
                                'product_name' => $productName,
                                'quantity_description' => $item['quantity_description'] ?? 'N/A',
                                'calculated_carbs_g' => $item['calculated_carbs_g'] ?? 0,
                                'calculated_fluid_ml' => $item['calculated_fluid_ml'] ?? 0,
                                'calculated_sodium_mg' => $item['calculated_sodium_mg'] ?? 0,
                                'notes' => $item['notes'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                        // $itemsToSave = array_filter($itemsToSave); // Filter out nulls if you used 'continue'

                        if (!empty($itemsToSave)) {
                            $newPlan->items()->createMany($itemsToSave);
                            // Log::info("PlanForm: Saved " . count($itemsToSave) . " items for plan ID: {$newPlan->id}");
                        }
                    }
            }
             }); // End Transaction


            // Redirect or handle final error
            if ($newPlan && $newPlan->exists) {
                session()->flash('message', 'Nutrition plan generated successfully!');
                if (!empty($planWarnings)) session()->flash('plan_warning', implode('; ', $planWarnings));
                if (!empty($leftoverItems)) session()->flash('plan_info', 'Note: Some selected items were not fully scheduled.');
                return redirect()->route('plans.show', $newPlan->id);
            } else {
                Log::error("PlanForm: Plan variable null or not saved after transaction.", ['userId' => $user->id]);
                session()->flash('error', 'Failed to finalize plan saving.');
                // Do NOT return; Allow outer catch to handle
            }
        } catch (\Throwable $e) { // Catch all exceptions/errors
            Log::error("PlanForm: CRITICAL ERROR during plan generation/saving", [
                'userId' => $user->id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(), /* 'trace' => $e->getTraceAsString() // Optional: Can be very long */
            ]);
            session()->flash('error', 'A critical server error occurred during plan generation: ' . $e->getMessage() . ' Please try again or contact support.');
            // No return needed, function ends here
        }
    } // End generatePlan

    public function render()
    {
        return view('livewire.plan-form')->layout('layouts.app');
    }
}
