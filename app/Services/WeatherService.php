<?php // Ensure <?php is at the very top

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class WeatherService
{
    protected string $baseUrl = 'https://api.open-meteo.com/v1/forecast';

    /**
     * Get hourly forecast for a specific activity window.
     * (Existing Method - Keep as is)
     * @param float $latitude
     * @param float $longitude
     * @param Carbon $startTime
     * @param int $durationSeconds
     * @return array|null
     */
    public function getHourlyForecast(float $latitude, float $longitude, Carbon $startTime, int $durationSeconds): ?array
    {
        $endTime = $startTime->copy()->addSeconds($durationSeconds);
        $startDateFormatted = $startTime->format('Y-m-d');
        $endDateFormatted = $endTime->format('Y-m-d');

        try {
            $response = Http::get($this->baseUrl, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'hourly' => 'temperature_2m,relative_humidity_2m,precipitation_probability,weather_code', // Added precipitation, weather_code
                'timezone' => 'auto',
                'start_date' => $startDateFormatted,
                'end_date' => $endDateFormatted,
            ]);

            // ... (rest of existing parsing logic - maybe adapt to use weather_code) ...
             if ($response->successful()) {
                $data = $response->json();
                $forecasts = [];

                if (isset($data['hourly']['time'], $data['hourly']['temperature_2m'], $data['hourly']['relative_humidity_2m'], $data['hourly']['precipitation_probability'], $data['hourly']['weather_code'])) { // Check new fields
                    $times = $data['hourly']['time'];
                    $temps = $data['hourly']['temperature_2m'];
                    $humidities = $data['hourly']['relative_humidity_2m'];
                    $precipProbs = $data['hourly']['precipitation_probability'];
                    $weatherCodes = $data['hourly']['weather_code']; // WMO Weather interpretation codes

                    if (count($times) === count($temps) && count($times) === count($humidities) && count($times) === count($precipProbs) && count($times) === count($weatherCodes)) {
                        foreach ($times as $index => $timeString) {
                            $forecastTime = Carbon::parse($timeString);
                            if ($forecastTime->betweenIncluded($startTime, $endTime)) {
                                $forecasts[] = [
                                    'time' => $forecastTime,
                                    'temp_c' => $temps[$index] ?? null,
                                    'humidity' => $humidities[$index] ?? null,
                                    'precip_probability' => $precipProbs[$index] ?? null, // Add precip
                                    'weather_code' => $weatherCodes[$index] ?? null, // Add code
                                ];
                            }
                        }
                        return $forecasts; // Return even if empty if successful
                    } else {
                        Log::error("Open-Meteo API response hourly data arrays have mismatched lengths.", ['response_summary' => $response->json('error', 'Unknown error')]);
                        return null;
                    }
                } else {
                     Log::error("Open-Meteo API response missing required hourly data keys.", ['response_summary' => $response->json('error', 'Unknown error')]);
                     return null;
                }
            } else {
                Log::error("Failed to fetch weather data from Open-Meteo (getHourlyForecast). Status: " . $response->status(), ['body' => $response->body()]);
                return null;
            }
        } catch (Exception $e) {
            Log::error("Exception fetching weather data (getHourlyForecast): " . $e->getMessage());
            return null;
        }
    }


    /**
     * Get current weather and hourly forecast for the next N hours.
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $forecastHours Number of hours to forecast beyond the current hour.
     * @return array|null ['current' => [...], 'hourly' => [...]] or null on failure.
     */
    public function getCurrentAndHourlyForecast(float $latitude, float $longitude, int $forecastHours = 6): ?array
    {
        // We still need a date range for the API query
        $queryStartDate = Carbon::now()->format('Y-m-d');
        $queryEndDate = Carbon::now()->addHours($forecastHours + 2)->format('Y-m-d'); // Fetch a bit extra just in case

        try {
            $response = Http::get($this->baseUrl, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code',
                'hourly' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation_probability,weather_code',
                'timezone' => 'auto', // IMPORTANT: Let API handle local time
                'start_date' => $queryStartDate,
                'end_date' => $queryEndDate,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $result = ['current' => null, 'hourly' => []];
                $apiTimezone = $data['timezone'] ?? 'UTC'; // Get the timezone detected by Open-Meteo, default to UTC

                // *** Get current time IN THE API's Timezone ***
                $nowInApiTz = Carbon::now($apiTimezone);
                Log::debug("WeatherService: Current time in API timezone ({$apiTimezone}): " . $nowInApiTz->toDateTimeString());

                // Process Current Weather (using the timezone)
                if (isset($data['current'], $data['current']['time'])) {
                    $result['current'] = [
                        // Parse time explicitly using the API's timezone
                        'time' => Carbon::parse($data['current']['time'], $apiTimezone),
                        'temp_c' => $data['current']['temperature_2m'] ?? null,
                        'humidity' => $data['current']['relative_humidity_2m'] ?? null,
                        'apparent_temp_c' => $data['current']['apparent_temperature'] ?? null,
                        'is_day' => $data['current']['is_day'] ?? 1,
                        'precipitation_mm' => $data['current']['precipitation'] ?? 0,
                        'weather_code' => $data['current']['weather_code'] ?? null,
                    ];
                } else {
                     Log::warning("Open-Meteo: Current weather data block missing or invalid.", ['response_summary' => $data['error'] ?? 'Unknown Error']);
                }

                // Process Hourly Forecast
                if (isset($data['hourly']['time'])) {
                    // ... (variable assignments remain the same) ...
                    $times = $data['hourly']['time'];
                    $temps = $data['hourly']['temperature_2m'];
                    $humidities = $data['hourly']['relative_humidity_2m'];
                    $apparentTemps = $data['hourly']['apparent_temperature'];
                    $precipProbs = $data['hourly']['precipitation_probability'];
                    $weatherCodes = $data['hourly']['weather_code'];


                    if (count($times) === count($temps) /* && add other counts */) {
                        // *** Calculate comparison points using the API timezone ***
                        $filterStartTime = $nowInApiTz->copy()->startOfHour();
                        $filterEndTime = $nowInApiTz->copy()->addHours($forecastHours); // Use 'now' in API timezone

                        Log::debug("WeatherService: Filtering hourly from " . $filterStartTime->toDateTimeString() . " to " . $filterEndTime->toDateTimeString());

                        foreach ($times as $index => $timeString) {
                             // *** Parse forecast time explicitly using the API's timezone ***
                            $forecastTime = Carbon::parse($timeString, $apiTimezone);

                            // *** Compare using times in the SAME timezone ***
                            if ($forecastTime->gte($filterStartTime) && $forecastTime->lte($filterEndTime)) {
                                $result['hourly'][] = [
                                    'time' => $forecastTime, // Already a Carbon object in the correct TZ
                                    'temp_c' => $temps[$index] ?? null,
                                    'humidity' => $humidities[$index] ?? null,
                                    'apparent_temp_c' => $apparentTemps[$index] ?? null,
                                    'precip_probability' => $precipProbs[$index] ?? null,
                                    'weather_code' => $weatherCodes[$index] ?? null,
                                ];
                            }
                        }
                        Log::debug("WeatherService: Found " . count($result['hourly']) . " relevant hourly forecasts.");
                    } else {
                         Log::error("Open-Meteo API response hourly data arrays have mismatched lengths (getCurrent).", ['response_summary' => $data['error'] ?? 'Unknown Error']);
                    }
                } else {
                     Log::warning("Open-Meteo: Hourly weather data block missing or invalid.", ['response_summary' => $data['error'] ?? 'Unknown Error']);
                }

                if ($result['current'] === null && empty($result['hourly'])) {
                     Log::error("Open-Meteo: Failed to retrieve both current and hourly forecast data.");
                     return null;
                }

                return $result;

            } else {
                Log::error("Failed to fetch weather data from Open-Meteo (getCurrent). Status: " . $response->status(), ['body' => $response->body()]);
                return null;
            }
        } catch (Exception $e) {
            Log::error("Exception fetching weather data (getCurrent): " . $e->getMessage(), ['trace' => $e->getTraceAsString()]); // Add trace for debugging
            return null;
        }
    }
}

