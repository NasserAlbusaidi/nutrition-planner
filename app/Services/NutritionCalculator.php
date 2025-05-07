<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class NutritionCalculator
{
    // --- Configuration Constants ---
    protected const INTENSITY_FACTORS = [
        'easy' => 0.60, 'endurance' => 0.70, 'tempo' => 0.83,
        'threshold' => 0.98, 'race_pace' => 0.98, 'steady_group_ride' => 0.78,
    ];
    protected const CARB_TARGETS_G_PER_HR = [
        'easy' => 40, 'endurance' => 60, 'tempo' => 75,
        'threshold' => 90, 'race_pace' => 90, 'steady_group_ride' => 70,
        'default' => 60, // Fallback for unknown intensity
    ];
    protected const BASE_FLUID_ML_PER_HR = [
        'light' => 500, 'average' => 750, 'heavy' => 1000,
        'default' => 750, // Fallback
    ];
    protected const BASE_SODIUM_MG_PER_HR = [
        'low' => 400, 'average' => 700, 'high' => 1000,
        'default' => 700, // Fallback
    ];

    protected const TEMP_BASELINE_C = 20;
    protected const FLUID_INCREASE_PER_DEGREE_ABOVE_BASELINE = 0.05;
    protected const HUMIDITY_THRESHOLD_HIGH = 70;
    protected const FLUID_INCREASE_HIGH_HUMIDITY_FACTOR = 1.10;
    protected const SODIUM_INCREASE_PER_DEGREE_ABOVE_BASELINE = 0.07;
    protected const SODIUM_INCREASE_HIGH_HUMIDITY_FACTOR = 1.15;

    protected const DEFAULT_TEMP_C = 20;
    protected const DEFAULT_HUMIDITY_PERCENT = 50;

    protected const MIN_FLUID_ML_PER_HR = 250;
    protected const MIN_SODIUM_MG_PER_HR = 200;

    public function estimateAveragePower(User $user, string $intensityKey): ?int
    {
        if (empty($user->ftp_watts) || !isset(self::INTENSITY_FACTORS[$intensityKey])) {
            Log::warning("Cannot estimate power. Missing FTP or invalid intensity.", ['user_id' => $user->id, 'intensity' => $intensityKey, 'ftp' => $user->ftp_watts]);
            return null;
        }
        return (int) round($user->ftp_watts * self::INTENSITY_FACTORS[$intensityKey]);
    }

    public function calculateEnergyExpenditure(int $averagePowerWatts, int $durationSeconds): int
    {
        if ($durationSeconds <= 0 || $averagePowerWatts <= 0) return 0;
        return (int) round(($averagePowerWatts * $durationSeconds) / 1000);
    }

    public function calculateHourlyTargets(User $user, string $intensityKey, ?array $hourlyForecast, int $durationSeconds): array
    {
        $targets = [];
        $totalHours = (int) ceil($durationSeconds / 3600);

        if ($totalHours <= 0) {
            Log::warning("NutritionCalculator: Total hours for calculation is zero or less.", ['duration_sec' => $durationSeconds]);
            return [];
        }

        $isForecastAvailable = ($hourlyForecast !== null && !empty($hourlyForecast));

        $baseCarbRate = self::CARB_TARGETS_G_PER_HR[$intensityKey] ?? self::CARB_TARGETS_G_PER_HR['default'];
        $userSweatLevel = $user->sweat_level ?? 'default'; // Use 'default' key if user profile data is null
        $userSaltLossLevel = $user->salt_loss_level ?? 'default'; // Use 'default' key

        $baseFluidRate = self::BASE_FLUID_ML_PER_HR[$userSweatLevel] ?? self::BASE_FLUID_ML_PER_HR['default'];
        $baseSodiumRate = self::BASE_SODIUM_MG_PER_HR[$userSaltLossLevel] ?? self::BASE_SODIUM_MG_PER_HR['default'];

        Log::debug("NutritionCalculator: Starting hourly target calculation.", [ /* ... logging data ... */ ]);

        for ($hour = 1; $hour <= $totalHours; $hour++) {
            $currentTemp = self::DEFAULT_TEMP_C;
            $currentHumidity = self::DEFAULT_HUMIDITY_PERCENT;

            if ($isForecastAvailable) {
                if (isset($hourlyForecast[$hour - 1])) {
                    $forecastForThisHour = $hourlyForecast[$hour - 1];
                    $currentTemp = $forecastForThisHour['temp_c'] ?? self::DEFAULT_TEMP_C;
                    $currentHumidity = $forecastForThisHour['humidity'] ?? self::DEFAULT_HUMIDITY_PERCENT;
                } else {
                    Log::warning("NutritionCalculator: Forecast data missing for hour {$hour}. Using default conditions for this hour.");
                }
            }

            $tempDiff = max(0, $currentTemp - self::TEMP_BASELINE_C);
            $fluidMultiplier = 1.0 + ($tempDiff * self::FLUID_INCREASE_PER_DEGREE_ABOVE_BASELINE);
            if ($currentHumidity >= self::HUMIDITY_THRESHOLD_HIGH) $fluidMultiplier *= self::FLUID_INCREASE_HIGH_HUMIDITY_FACTOR;
            $adjustedFluidRate = (int) round($baseFluidRate * $fluidMultiplier);

            $sodiumMultiplier = 1.0 + ($tempDiff * self::SODIUM_INCREASE_PER_DEGREE_ABOVE_BASELINE);
            if ($currentHumidity >= self::HUMIDITY_THRESHOLD_HIGH) $sodiumMultiplier *= self::SODIUM_INCREASE_HIGH_HUMIDITY_FACTOR;
            $adjustedSodiumRate = (int) round($baseSodiumRate * $sodiumMultiplier);

            $targets[] = [
                'hour' => $hour, 'carb_g' => $baseCarbRate,
                'fluid_ml' => max(self::MIN_FLUID_ML_PER_HR, $adjustedFluidRate),
                'sodium_mg' => max(self::MIN_SODIUM_MG_PER_HR, $adjustedSodiumRate),
                'temp_c' => round($currentTemp, 1), 'humidity' => (int) $currentHumidity,
            ];
            Log::debug("NutritionCalculator: Hour {$hour} Targets Calculated:", $targets[count($targets) - 1]);
        }
        return $targets;
    }
}


// **Notes:**
//         * **Configuration:** The constants at the top (`INTENSITY_FACTORS`, `CARB_TARGETS_G_PER_HR`, etc.) are crucial starting points. You should research standard sports nutrition guidelines (e.g., from ACSM, reputable coaches/scientists) and adjust these based on your findings and preferences. The weather adjustment factors are illustrative examples and need validation/refinement.
//         * **Weather Adjustment Logic:** The example provides a *basic* way to adjust fluid/sodium based on temperature and humidity. Real-world needs are complex. This is a good MVP starting point, but could be refined with more sophisticated models if desired later.
//         * **Hourly Simplification:** The `calculateHourlyTargets` method calculates a target rate *for each hour*. The `PlanGenerator` (next step) will need to break this down into smaller intervals (e.g., 15-min) and select products.
//         * **Error Handling:** Basic checks are included, but more robust handling might be needed depending on how accurate the inputs (like user profile) are expected to be.
//         * **Dependencies:** This service doesn't directly depend on others but expects the `hourlyForecast` array in the
