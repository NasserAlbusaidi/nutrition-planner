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
        ];
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
    }

    // ... estimateDurationSeconds method ...
    protected function estimateDurationSeconds(float $distanceKm, float $elevationM, string $intensityKey): int
    {
        $baseSpeedKph = match ($intensityKey) {
             'easy' => 22, 'endurance' => 26, 'tempo' => 30,
             'threshold' => 33, 'race_pace' => 33, 'steady_group_ride' => 28,
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
            $gpx = new phpGPX(); $file = $gpx->parse($gpxContent);
            if (!empty($file->tracks) && !empty($file->tracks[0]->segments) && !empty($file->tracks[0]->segments[0]->points)) {
                $firstPoint = $file->tracks[0]->segments[0]->points[0];
                return ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
            } elseif (!empty($file->waypoints) && !empty($file->waypoints[0])) {
                $firstPoint = $file->waypoints[0]; return ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
            } elseif (!empty($file->routes) && !empty($file->routes[0]->points)) {
                $firstPoint = $file->routes[0]->points[0]; return ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
            }
            Log::warning("Could not find starting point in GPX data for route ID: {$this->routeId}"); return null;
        } catch (\Exception $e) { Log::error("Error parsing GPX for route ID {$this->routeId}: " . $e->getMessage()); return null; }
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
            return;
        }
        $startTime = Carbon::parse($validatedData['planned_start_datetime']);
        $coordinates = null;

        // 1. Determine Coordinates for Weather
        if ($this->routeSource === 'strava') {
            Log::info("GeneratePlan (Strava): Fetching GPX for weather coordinates. Route ID: {$this->routeId}");
            $gpxContent = $stravaService->getRouteGpx($user, $this->routeId);
            if (!$gpxContent) {
                session()->flash('error', 'Could not retrieve Strava route data (GPX) for weather. Please try again.');
                return;
            }
            $coordinates = $this->getStartCoordinatesFromGpx($gpxContent);
        } elseif ($this->routeSource === 'gpx') {
            if ($this->routeStartLat && $this->routeStartLng) {
                $coordinates = ['latitude' => $this->routeStartLat, 'longitude' => $this->routeStartLng];
                Log::info("GeneratePlan (GPX): Using provided start coordinates for weather.", $coordinates);
            } else {
                Log::warning("GeneratePlan (GPX): Start coordinates not available from uploaded GPX file. Weather will use defaults.");
                // Don't return; allow plan generation but NutritionCalculator must handle null forecast
            }
        } else {
            Log.warning("GeneratePlan: Unknown route source '{$this->routeSource}'. Cannot determine coordinates for weather.");
            // Potentially allow proceeding without weather
        }

        if (!$coordinates && $this->routeSource !== 'gpx') { // For GPX, we might proceed without coords and use default weather
            session()->flash('error', 'Could not determine starting location of the route for weather data.');
            return;
        }

        // 2. Estimate Duration (uses $this->routeDistanceKm, $this->routeElevationM from mount)
        $distanceKm = $this->routeDistanceKm;
        $elevationM = $this->routeElevationM;
        $durationSeconds = $this->estimateDurationSeconds($distanceKm, $elevationM, $validatedData['planned_intensity']);
        if ($durationSeconds <= 0) { session()->flash('error', 'Invalid estimated duration.'); return; }
        Log::info("Estimated duration {$durationSeconds}s for {$this->routeSource} route.");


        // 3. Fetch Weather (pass null $coordinates if not found for GPX)
        $hourlyForecast = null; // Initialize
        $weatherSummary = "Weather data unavailable."; // Default summary

        if ($coordinates) {
            Log::info("Fetching weather forecast for coordinates: ", $coordinates);
            $hourlyForecast = $weatherService->getHourlyForecast(
                $coordinates['latitude'], $coordinates['longitude'], $startTime, $durationSeconds
            );
            if ($hourlyForecast !== null && !empty($hourlyForecast)) {
                 $avgTemp = round(collect($hourlyForecast)->avg('temp_c') ?? 0, 1);
                 $avgHumidity = round(collect($hourlyForecast)->avg('humidity') ?? 0);
                 $weatherSummary = "Avg Temp: {$avgTemp}°C, Avg Humidity: {$avgHumidity}%";
                 Log::info("Weather fetched: {$weatherSummary}");
            } else {
                 Log::warning("Failed to fetch weather forecast or forecast was empty. Plan will use default conditions.");
                 // Keep $hourlyForecast as null
            }
        } else {
            Log::warning("Skipping weather forecast due to missing coordinates for {$this->routeSource} route. Plan will use default conditions.");
        }


        // 4. Calculate Targets (NutritionCalculator MUST handle $hourlyForecast being potentially null)
        $hourlyTargets = $calculator->calculateHourlyTargets($user, $validatedData['planned_intensity'], $hourlyForecast, $durationSeconds);
        if (empty($hourlyTargets)) { session()->flash('error', 'Could not calculate nutrition targets.'); return; }
        Log::info("Hourly targets calculated: " . count($hourlyTargets) . " target sets.");

        // 5. Fetch Pantry & Generate Schedule
        $pantryProducts = Product::where(fn($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))->get();
        if ($pantryProducts->isEmpty()) { session()->flash('error', 'No active products in pantry. Please add products.'); return; }
        Log::info("Found {$pantryProducts->count()} active pantry products.");

        $scheduleItemsData = $generator->generateSchedule($user, $durationSeconds, $hourlyTargets, $pantryProducts);
        if (empty($scheduleItemsData)) { session()->flash('error', 'Could not generate a nutrition schedule (e.g., short duration or product mismatch).'); return; }
        if (isset($scheduleItemsData[0]['error'])) { session()->flash('error', 'Schedule generation error: ' . $scheduleItemsData[0]['error']); return; }
        Log::info("Nutrition schedule generated with " . count($scheduleItemsData) . " items.");

        // 6. Save Plan & Items
        $newPlan = null;
        try {
            DB::transaction(function () use (
                $user, $startTime, $validatedData, $durationSeconds, $calculator,
                $scheduleItemsData, $weatherSummary, $hourlyTargets, &$newPlan // Pass by reference
            ) {
                $estimatedPower = $calculator->estimateAveragePower($user, $validatedData['planned_intensity']);
                $totalCarbs = collect($scheduleItemsData)->sum('calculated_carbs_g');
                $totalFluid = collect($scheduleItemsData)->sum('calculated_fluid_ml');
                $totalSodium = collect($scheduleItemsData)->sum('calculated_sodium_mg');

                $planData = [
                    'name' => 'Plan for ' . $this->routeName . ' on ' . $startTime->format('M j, Y'),
                    'user_id' => $user->id, // Explicitly set, though relationship method also does it
                    'planned_start_time' => $startTime,
                    'planned_intensity' => $validatedData['planned_intensity'],
                    'estimated_duration_seconds' => $durationSeconds,
                    'estimated_avg_power_watts' => $estimatedPower ? round($estimatedPower) : null,
                    'estimated_total_carbs_g' => round($totalCarbs),
                    'estimated_total_fluid_ml' => round($totalFluid),
                    'estimated_total_sodium_mg' => round($totalSodium),
                    'hourly_targets_data' => $hourlyTargets, // Store as JSON
                    'weather_summary' => $weatherSummary,
                    'source' => $this->routeSource, // Save the source
                ];

                // Add route-specific info only if it's a Strava route for now (or adapt for GPX display later)
                if ($this->routeSource === 'strava') {
                    $planData['strava_route_id'] = $this->routeId;
                    $planData['strava_route_name'] = $this->routeName; // Already available from mount
                }
                 // For database schema that DOES NOT have estimated_distance_km and estimated_elevation_m on plans table
                 // If they ARE in your schema, uncomment these:
                // $planData['estimated_distance_km'] = $this->routeDistanceKm;
                // $planData['estimated_elevation_m'] = $this->routeElevationM;
                Log::critical("PLAN DATA TO BE SAVED:", $planData); // Use critical to make it stand out
                $newPlan = Plan::create($planData); // Use Plan::create for clarity if not using relationship

                if (!$newPlan) { throw new \Exception("Plan model creation failed inside transaction."); }
                Log::info("Plan record created in transaction, ID: {$newPlan->id}");

                if (!empty($scheduleItemsData)) {
                    $itemsToSave = array_map(function ($item) use ($newPlan) {
                        // ... (item mapping - ensure product_name is handled) ...
                        $productName = $item['product_name'] ?? null;
                        if (!$productName && isset($item['product_id'])) {
                            $product = Product::find($item['product_id']); $productName = $product?->name;
                        }
                        return ['plan_id' => $newPlan->id, /* ... other item fields ... */ 'product_name' => $productName, 'created_at' => now(), 'updated_at' => now(), 'calculated_carbs_g' => $item['calculated_carbs_g'] ?? 0, 'calculated_fluid_ml' => $item['calculated_fluid_ml'] ?? 0, 'calculated_sodium_mg' => $item['calculated_sodium_mg'] ?? 0, 'notes' => $item['notes'] ?? null, 'quantity_description' => $item['quantity_description'], 'instruction_type' => $item['instruction_type'], 'time_offset_seconds' => $item['time_offset_seconds'], 'product_id' => $item['product_id'] ?? null, ];
                    }, $scheduleItemsData);
                    $newPlan->items()->createMany($itemsToSave);
                }
                Log::info("Plan transaction part succeeded for plan ID: {$newPlan->id}");
            }); // End DB Transaction

            if ($newPlan && $newPlan->exists) {
                session()->flash('message', 'Nutrition plan generated successfully!');
                return redirect()->route('plans.show', $newPlan->id); // Pass ID or model instance
            } else {
                Log::error("Plan was not set or not persisted after transaction.", ['userId' => $user->id, 'newPlanId' => $newPlan?->id]);
                session()->flash('error', 'Failed to finalize the plan after database operations. Please check logs.');
            }

        } catch (\Throwable $e) {
            Log::error("CRITICAL ERROR during plan generation/saving for user {$user->id}: " . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'A critical error occurred: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.plan-form')->layout('layouts.app');
    }
}
