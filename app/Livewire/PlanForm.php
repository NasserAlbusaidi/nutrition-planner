<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Services\NutritionCalculator;
use App\Services\PlanGenerator;
use App\Models\Product;
use App\Services\StravaService; // Ensure StravaService is imported
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

    // Property to store the polyline fetched in mount
    public string $fetchedPolyline = '';

    // Form properties
    public $planned_start_datetime;
    public $planned_intensity;

    // *** REMOVED redundant property ***
    // public string $routePolyline;


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
            'planned_start_datetime' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'planned_intensity' => ['required', Rule::in(array_keys($this->intensityOptions))],
        ];
    }

    public function mount(
        string $routeId,
        string $routeName,
        float $distance,
        float $elevation,
        StravaService $stravaService // Inject StravaService
    ) {
        $this->routeId = $routeId;
        $this->routeName = urldecode($routeName);
        $this->routeDistanceKm = $distance;
        $this->routeElevationM = $elevation;
        $this->planned_start_datetime = Carbon::tomorrow()->addHours(8)->format('Y-m-d\TH:i');

        Log::info('PlanForm Mounted for Route:', [
            'routeId' => $this->routeId,
            'name' => $this->routeName,
            'distance' => $this->routeDistanceKm,
            'elevation' => $this->routeElevationM,
        ]);

         // Fetch route details
         Log::info("PlanForm: Fetching route details from Strava for ID {$this->routeId}");
         $user = Auth::user();
         $allRoutes = $stravaService->getUserRoutes($user);
         $foundPolyline = null; // Temporary variable

         if ($allRoutes !== null) {
             $routeDetails = collect($allRoutes)->firstWhere('id', $this->routeId);
             // Get polyline if found, otherwise null
             $foundPolyline = $routeDetails['summary_polyline'] ?? null;
         } else {
             Log::error("PlanForm: Failed to fetch routes from StravaService in mount.");
             session()->flash('error', 'Could not retrieve route details for map display.');
         }

         Log::debug("PlanForm Mount: Before assigning polyline", [
            'allRoutes_type' => gettype($allRoutes),
            'routeDetails_found' => !empty($routeDetails),
            'foundPolyline_value' => $foundPolyline,
            'foundPolyline_type' => gettype($foundPolyline)
        ]);

        $this->fetchedPolyline = $foundPolyline ?? '';
        Log::debug("PlanForm Mount: AFTER assigning polyline", [
            'fetchedPolyline_value' => $this->fetchedPolyline,
            'fetchedPolyline_type' => gettype($this->fetchedPolyline),
        ]);
         if ($this->fetchedPolyline !== '') {
             Log::info("PlanForm: Successfully fetched polyline.", ['length' => strlen($this->fetchedPolyline)]);
         } else {
             Log::warning("PlanForm: Could not fetch polyline for route ID {$this->routeId}. Property set to empty string.");
         }
     }
    /**
     * Estimate duration in seconds.
     */
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
        $elevationFactor = ($distanceKm > 0) ? ($elevationM / $distanceKm) / 10 : 0;
        $adjustedSpeedKph = max(10, $baseSpeedKph - ($elevationFactor * 10));
        Log::debug("Duration Estimation:", compact('distanceKm', 'elevationM', 'intensityKey', 'baseSpeedKph', 'adjustedSpeedKph'));
        if ($adjustedSpeedKph <= 0) return 3600;
        $durationHours = $distanceKm / $adjustedSpeedKph;
        return (int) round($durationHours * 3600);
    }

    /**
     * Get start coordinates from GPX content.
     */
    protected function getStartCoordinatesFromGpx(string $gpxContent): ?array
    {
        try {
            $gpx = new phpGPX();
            $file = $gpx->parse($gpxContent);
            if (!empty($file->tracks) && !empty($file->tracks[0]->segments) && !empty($file->tracks[0]->segments[0]->points)) {
                $firstPoint = $file->tracks[0]->segments[0]->points[0];
                return ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
            } elseif (!empty($file->routes) && !empty($file->routes[0]->points)) {
                $firstPoint = $file->routes[0]->points[0];
                return ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
            }
            Log::warning("Could not find starting point in GPX data for route ID: {$this->routeId}");
            return null;
        } catch (\Exception $e) {
            Log::error("Error parsing GPX for route ID: {$this->routeId}. Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate and save the nutrition plan.
     */
    public function generatePlan(
        StravaService $stravaService,
        WeatherService $weatherService,
        NutritionCalculator $calculator,
        PlanGenerator $generator
    ) {
        $validatedData = $this->validate();
        $user = Auth::user();
        $startTime = Carbon::parse($validatedData['planned_start_datetime']);

        // 1. Fetch GPX & Coords
        Log::info("Generating plan: Fetching GPX for route {$this->routeId}");
        $gpxContent = $stravaService->getRouteGpx($user, $this->routeId);
        if (!$gpxContent) { /* ... error handling ... */ return; }
        $coordinates = $this->getStartCoordinatesFromGpx($gpxContent);
        if (!$coordinates) { /* ... error handling ... */ return; }
        Log::info("Generating plan: Found coordinates ({$coordinates['latitude']}, {$coordinates['longitude']})");

        // 2. Estimate Duration
        $distanceKm = $this->routeDistanceKm;
        $elevationM = $this->routeElevationM;
        $durationSeconds = $this->estimateDurationSeconds($distanceKm, $elevationM, $validatedData['planned_intensity']);
        if ($durationSeconds <= 0) { /* ... error handling ... */ return; }
        Log::info("Generating plan: Estimated duration {$durationSeconds}s for {$distanceKm}km / {$elevationM}m at intensity {$validatedData['planned_intensity']}");

        // 3. Fetch Weather
        Log::info("Generating plan: Fetching weather forecast");
        $hourlyForecast = $weatherService->getHourlyForecast(
            $coordinates['latitude'], $coordinates['longitude'], $startTime, $durationSeconds
        );
        if ($hourlyForecast === null) { /* ... error handling ... */ return; }
        $weatherSummary = "Avg Temp: " . round(collect($hourlyForecast)->avg('temp_c') ?? 0, 1) . "°C, "
            . "Avg Humidity: " . round(collect($hourlyForecast)->avg('humidity') ?? 0) . "%";
        Log::info("Generating plan: Weather fetched. Summary: {$weatherSummary}");

        // 4. Calculate Targets
        Log::info("Generating plan: Calculating hourly targets");
        $hourlyTargets = $calculator->calculateHourlyTargets(
            $user, $validatedData['planned_intensity'], $hourlyForecast, $durationSeconds
        );
        Log::info("Generating plan: Hourly targets calculated.", ['count' => count($hourlyTargets)]);

        // 5. Fetch Pantry & Generate Schedule
        Log::info("Generating plan: Fetching pantry products");
        $pantryProducts = Product::whereNull('user_id')->orWhere('user_id', $user->id)->get();
        if ($pantryProducts->isEmpty()) { /* ... error handling ... */ return; }
        Log::info("Generating plan: Found {$pantryProducts->count()} products in combined pantry.");

        Log::info("Generating plan: Generating nutrition schedule");
        $scheduleItemsData = $generator->generateSchedule(
            $user, $durationSeconds, $hourlyTargets, $pantryProducts
        );
        if (isset($scheduleItemsData[0]['error'])) { /* ... error handling ... */ return; }
        Log::info("Generating plan: Schedule generated.", ['item_count' => count($scheduleItemsData)]);

        // 6. Save Plan & Items
        Log::info("Generating plan: Saving plan to database");
        try {
            $newPlan = null;
            DB::transaction(function () use ( $user, $startTime, $validatedData, $durationSeconds, $distanceKm, $elevationM,
            $calculator, $scheduleItemsData, $weatherSummary, &$newPlan) {


                $estimatedPower = $calculator->estimateAveragePower($user, $validatedData['planned_intensity']);
                $totalCarbs = collect($scheduleItemsData)->sum('calculated_carbs_g');
                $totalFluid = collect($scheduleItemsData)->sum('calculated_fluid_ml');
                $totalSodium = collect($scheduleItemsData)->sum('calculated_sodium_mg');
                $newPlan = $user->plans()->create([
                    'name' => 'Plan for ' . $this->routeName . ' on ' . $startTime->format('Y-m-d'),
                    'strava_route_id' => $this->routeId,
                    'strava_route_name' => $this->routeName,
                    'planned_start_time' => $startTime,
                    'planned_intensity' => $validatedData['planned_intensity'],
                    'estimated_duration_seconds' => $durationSeconds,
                    // 'estimated_distance_km' => round($distanceKm, 2), // REMOVE THIS LINE
                    // 'estimated_elevation_m' => round($elevationM),   // REMOVE THIS LINE
                    'estimated_avg_power_watts' => $estimatedPower ? round($estimatedPower) : null,
                    'estimated_total_carbs_g' => round($totalCarbs),
                    'estimated_total_fluid_ml' => round($totalFluid),
                    'estimated_total_sodium_mg' => round($totalSodium),
                    'weather_summary' => $weatherSummary,
                    // 'user_id' will be automatically handled by the relationship
                ]);

                if (!empty($scheduleItemsData)) {
                    $itemsToSave = array_map(function ($item) use ($newPlan) {
                         // Make sure product_name is populated correctly here if using that strategy
                         // Simplified version assuming generator returns needed keys:
                        $productName = $item['product_name'] ?? null; // Get name if provided
                         if (!$productName && isset($item['product_id'])) {
                             // Fetch if absolutely necessary (less efficient)
                             $product = Product::find($item['product_id']);
                             $productName = $product?->name;
                         }

                         return [
                            'plan_id' => $newPlan->id,
                            'time_offset_seconds' => $item['time_offset_seconds'],
                            'instruction_type' => $item['instruction_type'],
                            'product_id' => $item['product_id'] ?? null,
                             'product_name' => $productName, // Save the name
                            'quantity_description' => $item['quantity_description'],
                            'calculated_carbs_g' => $item['calculated_carbs_g'],
                            'calculated_fluid_ml' => $item['calculated_fluid_ml'],
                            'calculated_sodium_mg' => $item['calculated_sodium_mg'],
                            'notes' => $item['notes'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }, $scheduleItemsData);
                    $newPlan->items()->createMany($itemsToSave);
                }
                Log::info("Generating plan: Plan and items saved successfully.", ['plan_id' => $newPlan->id]);
            });

            // 7. Redirect
            if ($newPlan) {
                session()->flash('message', 'Nutrition plan generated successfully!');
                return redirect()->route('plans.show', $newPlan);
            } else {
                throw new \Exception("Plan creation failed but transaction reported success.");
            }
        } catch (\Exception $e) {
            Log::error("Error saving plan for user {$user->id}: " . $e->getMessage(), ['exception' => $e]);
            session()->flash('error', 'An error occurred while saving the plan. Please try again.');
        }
    }


    public function render()
    {
        // TEMPORARY DEBUGGING - DO NOT KEEP THIS
        if ($this->fetchedPolyline === null || $this->fetchedPolyline === '') {
             Log::warning("PlanForm Render: fetchedPolyline is empty/null before re-fetch attempt.");
             $user = Auth::user();
             $stravaService = app(StravaService::class); // Resolve service here
             $allRoutes = $stravaService->getUserRoutes($user);
             if ($allRoutes !== null) {
                 $routeDetails = collect($allRoutes)->firstWhere('id', $this->routeId);
                 $this->fetchedPolyline = $routeDetails['summary_polyline'] ?? ''; // Default to empty string
                 Log::info("PlanForm Render: Re-fetched polyline.", ['length' => strlen($this->fetchedPolyline)]);
             }
        }
        // END TEMPORARY DEBUGGING

        return view('livewire.plan-form')
                ->layout('layouts.app');
    }
}
