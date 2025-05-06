<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NutritionCalculator
{
    // --- Configuration Constants (Adjust based on research/preference) ---

    // Intensity Zone %FTP ranges (Approximate midpoints for calculation)
    protected const INTENSITY_FACTORS = [
        'easy' => 0.60, // ~60% FTP
        'endurance' => 0.70, // ~70% FTP
        'tempo' => 0.83, // ~83% FTP
        'threshold' => 0.98, // ~98% FTP
        'race_pace' => 0.98, // Example: Treat race pace like threshold
        'steady_group_ride' => 0.78, // Example: High Z2 / Low Z3
    ];

    // Carb intake targets (g/hr) based on intensity
    protected const CARB_TARGETS_G_PER_HR = [
        'easy' => 40,
        'endurance' => 60,
        'tempo' => 75,
        'threshold' => 90,
        'race_pace' => 90,
        'steady_group_ride' => 70,
    ];

    // Baseline fluid intake (ml/hr) - adjust based on sweat level
    // These are starting points before weather adjustment
    protected const BASE_FLUID_ML_PER_HR = [
        'light' => 500,
        'average' => 750,
        'heavy' => 1000,
    ];

    // Baseline sodium intake (mg/hr) - adjust based on salt loss level
    // These are starting points before weather adjustment
    protected const BASE_SODIUM_MG_PER_HR = [
        'low' => 400,
        'average' => 700,
        'high' => 1000,
    ];

    // Weather Adjustment Factors (Example - needs refinement based on sports science)
    protected const TEMP_BASELINE_C = 20; // Temperature baseline
    protected const FLUID_INCREASE_PER_DEGREE_ABOVE_BASELINE = 0.05; // 5% increase per degree C above baseline
    protected const HUMIDITY_THRESHOLD_HIGH = 70; // % Humidity threshold for extra increase
    protected const FLUID_INCREASE_HIGH_HUMIDITY_FACTOR = 1.1; // Additional 10% increase for high humidity
    protected const SODIUM_INCREASE_PER_DEGREE_ABOVE_BASELINE = 0.07; // 7% increase per degree C
    protected const SODIUM_INCREASE_HIGH_HUMIDITY_FACTOR = 1.15; // Additional 15% increase for high humidity


    /**
     * Estimate average power output based on user FTP and planned intensity.
     *
     * @param User $user
     * @param string $intensityKey Key from INTENSITY_FACTORS
     * @return int|null Average power in watts, or null if FTP or intensity is invalid.
     */
    public function estimateAveragePower(User $user, string $intensityKey): ?int
    {
        if (!$user->ftp_watts || !isset(self::INTENSITY_FACTORS[$intensityKey])) {
            Log::warning("Cannot estimate power. Missing FTP or invalid intensity.", ['user_id' => $user->id, 'intensity' => $intensityKey]);
            return null;
        }
        return (int) round($user->ftp_watts * self::INTENSITY_FACTORS[$intensityKey]);
    }

    /**
     * Calculate estimated energy expenditure.
     * Note: Uses the approximation kJ ~= kCal for cycling.
     *
     * @param int $averagePowerWatts
     * @param int $durationSeconds
     * @return int Estimated kCal burned.
     */
    public function calculateEnergyExpenditure(int $averagePowerWatts, int $durationSeconds): int
    {
        if ($durationSeconds <= 0) return 0;
        $totalWorkKj = ($averagePowerWatts * $durationSeconds) / 1000;
        return (int) round($totalWorkKj); // Approx kCal = kJ
    }

    /**
     * Calculate hourly nutrition and hydration targets, adjusted for weather.
     *
     * @param User $user
     * @param string $intensityKey
     * @param array $hourlyForecast // Array from WeatherService: [['time' => Carbon, 'temp_c' => float, 'humidity' => int], ...]
     * @param int $durationSeconds
     * @return array An array of hourly targets: [ ['hour' => int, 'carb_g' => int, 'fluid_ml' => int, 'sodium_mg' => int, 'temp_c' => float, 'humidity' => int], ... ]
     */
    public function calculateHourlyTargets(User $user, string $intensityKey, array $hourlyForecast, int $durationSeconds): array
    {
        $targets = [];
        $totalHours = ceil($durationSeconds / 3600);
        $now = Carbon::now(); // To avoid calculating on past forecasts if API returns them

        // Get baseline rates based on profile
        $baseCarbRate = self::CARB_TARGETS_G_PER_HR[$intensityKey] ?? 60; // g/hr
        $baseFluidRate = self::BASE_FLUID_ML_PER_HR[$user->sweat_level ?? 'average'] ?? 750; // ml/hr
        $baseSodiumRate = self::BASE_SODIUM_MG_PER_HR[$user->salt_loss_level ?? 'average'] ?? 700; // mg/hr

        Log::debug("Calculating targets", [
            'user_id' => $user->id,
            'intensity' => $intensityKey,
            'duration_sec' => $durationSeconds,
            'total_hours' => $totalHours,
            'base_carb' => $baseCarbRate,
            'base_fluid' => $baseFluidRate,
            'base_sodium' => $baseSodiumRate,
            'forecast_count' => count($hourlyForecast)
        ]);


        for ($hour = 1; $hour <= $totalHours; $hour++) {
            // Find the relevant forecast for this hour
            // For simplicity, find the forecast closest to the middle of the hour
            $midHourTime = $now->copy()->addHours($hour - 0.5); // Approximate middle of the current hour
            $relevantForecast = $this->findClosestForecast($midHourTime, $hourlyForecast);

            $currentTemp = $relevantForecast['temp_c'] ?? self::TEMP_BASELINE_C; // Default to baseline if no forecast
            $currentHumidity = $relevantForecast['humidity'] ?? 50; // Default humidity if no forecast

            // --- Calculate Adjustments ---
            $tempDiff = max(0, $currentTemp - self::TEMP_BASELINE_C); // Degrees above baseline

            // Fluid adjustment
            $fluidMultiplier = 1.0 + ($tempDiff * self::FLUID_INCREASE_PER_DEGREE_ABOVE_BASELINE);
            if ($currentHumidity >= self::HUMIDITY_THRESHOLD_HIGH) {
                $fluidMultiplier *= self::FLUID_INCREASE_HIGH_HUMIDITY_FACTOR;
            }
            $adjustedFluidRate = (int) round($baseFluidRate * $fluidMultiplier);

            // Sodium adjustment
            $sodiumMultiplier = 1.0 + ($tempDiff * self::SODIUM_INCREASE_PER_DEGREE_ABOVE_BASELINE);
            if ($currentHumidity >= self::HUMIDITY_THRESHOLD_HIGH) {
                $sodiumMultiplier *= self::SODIUM_INCREASE_HIGH_HUMIDITY_FACTOR;
            }
            $adjustedSodiumRate = (int) round($baseSodiumRate * $sodiumMultiplier);

            // Store targets for this hour
            $targets[] = [
                'hour' => $hour, // 1-based hour index
                'carb_g' => $baseCarbRate, // Carb rate usually depends on intensity, not weather
                'fluid_ml' => $adjustedFluidRate,
                'sodium_mg' => $adjustedSodiumRate,
                'temp_c' => round($currentTemp, 1), // Store weather used
                'humidity' => (int) $currentHumidity, // Store weather used
            ];

            Log::debug("Hour {$hour} Targets:", $targets[count($targets) - 1]);
        }

        return $targets;
    }

    /**
     * Helper to find the forecast entry closest to a target time.
     *
     * @param Carbon $targetTime
     * @param array $forecasts Array from WeatherService
     * @return array|null The closest forecast entry or null if forecasts are empty.
     */
    protected function findClosestForecast(Carbon $targetTime, array $forecasts): ?array
    {
        if (empty($forecasts)) {
            return null;
        }

        $closestForecast = null;
        $minDiff = PHP_INT_MAX;

        foreach ($forecasts as $forecast) {
            // Ensure 'time' is a Carbon instance if not already done in WeatherService
            $forecastTime = ($forecast['time'] instanceof Carbon) ? $forecast['time'] : Carbon::parse($forecast['time']);
            $diff = abs($targetTime->diffInSeconds($forecastTime));

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closestForecast = $forecast;
            }
        }
        return $closestForecast;
    }
}

// **Notes:**
//         * **Configuration:** The constants at the top (`INTENSITY_FACTORS`, `CARB_TARGETS_G_PER_HR`, etc.) are crucial starting points. You should research standard sports nutrition guidelines (e.g., from ACSM, reputable coaches/scientists) and adjust these based on your findings and preferences. The weather adjustment factors are illustrative examples and need validation/refinement.
//         * **Weather Adjustment Logic:** The example provides a *basic* way to adjust fluid/sodium based on temperature and humidity. Real-world needs are complex. This is a good MVP starting point, but could be refined with more sophisticated models if desired later.
//         * **Hourly Simplification:** The `calculateHourlyTargets` method calculates a target rate *for each hour*. The `PlanGenerator` (next step) will need to break this down into smaller intervals (e.g., 15-min) and select products.
//         * **Error Handling:** Basic checks are included, but more robust handling might be needed depending on how accurate the inputs (like user profile) are expected to be.
//         * **Dependencies:** This service doesn't directly depend on others but expects the `hourlyForecast` array in the
