<?php

namespace App\Livewire;

use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Support\Str;
use Carbon\CarbonInterval; // Import CarbonInterval
use Illuminate\Support\Facades\Log; // Import Log for debugging
use Illuminate\Support\Collection; // Import Collection for type hinting
use App\Models\Product; // Import Product model for type hinting

class PlanViewer extends Component
{
    public Plan $plan; // Route model binding
    public $preparationSummary = []; // Summary of preparation items

    // --- Icon mapping for getItemIcon() helper ---
    // Using Heroicons v2 Outline (-o-) and Solid (-s-) names
    // Add more types as needed from your Product constants/types
    public $productTypeIcons = [
        Product::TYPE_DRINK_MIX => 'heroicon-o-beaker',
        Product::TYPE_GEL => 'heroicon-o-bolt',
        Product::TYPE_ENERGY_CHEW => 'heroicon-o-cube',
        Product::TYPE_ENERGY_BAR => 'heroicon-s-bars-3-bottom-left',
        Product::TYPE_REAL_FOOD => 'heroicon-o-cake',
        Product::TYPE_HYDRATION_TABLET => 'heroicon-o-adjustments-horizontal',
        Product::TYPE_PLAIN_WATER => 'heroicon-o-beaker', // Reusing beaker for plain water? Or 'heroicon-o-sparkles'
        Product::TYPE_RECOVERY_DRINK => 'heroicon-o-arrows-pointing-in',
        'unknown' => 'heroicon-o-tag', // For items where type couldn't be determined
        'default' => 'heroicon-o-question-mark-circle',
    ];

    // Icons based purely on instruction_type (less specific, used as fallback)
    public $instructionIcons = [
        'drink' => 'heroicon-o-beaker',
        'consume' => 'heroicon-o-chevron-double-right',
        'eat' => 'heroicon-o-chevron-double-right',
        'mix_drink' => 'heroicon-o-arrow-path-rounded-square', // Icon for mixing
    ];
    // --- End Icon Mapping ---


    public function mount(Plan $plan)
    {
        // Authorization check
        if ($plan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Eager load items AND the product relationship for each item.
        // Ensure items are ordered by time_offset_seconds for correct display processing.
        $this->plan = $plan->load(['items' => function ($query) {
            $query->with('product')->orderBy('time_offset_seconds');
        }]);

        // Check if plan loaded correctly (optional debugging)
        if (!$this->plan->relationLoaded('items')) {
            Log::warning('Plan items failed to eager load.', ['plan_id' => $plan->id]);
        } else {
            Log::info('Plan items loaded successfully.', ['plan_id' => $plan->id, 'item_count' => $this->plan->items->count()]);
        }
        $this->preparationSummary = $this->calculatePreparationSummary($this->plan->items);
    }

    /**
     * Format duration in seconds to H:i:s or MM:SS depending on length.
     */
    public function formatDuration($seconds): string
    {
        if (!is_numeric($seconds) || $seconds < 0) {
            return 'N/A';
        }
        // Use CarbonInterval for robust formatting
        // format specs: https://carbon.nesbot.com/docs/#api-intervalformat
        return CarbonInterval::seconds($seconds)->cascade()->format('%H:%I:%S');
    }

    /**
     * Format item time offset seconds to HH:MM:SS relative to start.
     */
    public function formatTimeOffset($seconds): string
    {
        if (!is_numeric($seconds) || $seconds < 0) return '00:00:00'; // Default to start
        return $this->formatDuration($seconds); // Use the same H:i:s formatting for consistency
    }

    /**
     * Calculate Estimated Average Speed.
     * Needs $plan->estimated_distance_km and $plan->estimated_duration_seconds.
     */
    public function getEstimatedAverageSpeedProperty(): ?string // Using Livewire computed property
    {


        if ($this->plan->estimated_distance_km > 0 && $this->plan->estimated_duration_seconds > 0) {
            $durationInHours = $this->plan->estimated_duration_seconds / 3600;
            $speed = $this->plan->estimated_distance_km / $durationInHours;
            return number_format($speed, 1) . ' km/h';
        }
        return 'N/A';
    }

    /**
     * Calculate Estimated Time of Arrival.
     */
    public function getEstimatedTimeOfArrivalProperty(): ?string // Using Livewire computed property
    {
        if ($this->plan->planned_start_time && $this->plan->estimated_duration_seconds > 0) {
            return $this->plan->planned_start_time
                ->copy()
                ->addSeconds($this->plan->estimated_duration_seconds)
                ->format('Y-m-d H:i'); // Or your preferred format
        }
        return 'N/A';
    }

    /**
     * Get an appropriate Heroicon name based on the plan item.
     */
    public function getItemIcon(object $item): string // $item is PlanItem model instance
    {
        // 1. Specific Check for Plain Water (using override name)
        if ($item->product_id === null && Str::contains($item->product_name_override ?? '', 'Water', true)) { // Case-insensitive check
            return $this->productTypeIcons[\App\Models\Product::TYPE_PLAIN_WATER] ?? 'heroicon-o-beaker'; // Water icon
        }

        // 2. Check Product Type via Relationship
        if ($item->relationLoaded('product') && $item->product && isset($this->productTypeIcons[$item->product->type])) {
            return $this->productTypeIcons[$item->product->type];
        }

        // 3. Fallback to Instruction Type
        if (isset($this->instructionIcons[$item->instruction_type])) {
            return $this->instructionIcons[$item->instruction_type];
        }

        // 4. Default Fallback
        return $this->productTypeIcons['default'] ?? 'heroicon-o-question-mark-circle';
    }

    /**
     * Helper function to get weather summary string for a specific hour FROM STORED DATA.
     * Assumes $plan->hourly_targets_data is populated and 0-indexed for hour 1, 2, etc.
     */
    public function getWeatherForHour(int $hourIndex): string // Removed $previousHourIndex
    {
        // Access the stored hourly target data for the specified hour index
        $currentWeather = $this->plan->hourly_targets_data[$hourIndex] ?? null;
        // Note: hourly_targets_data should contain ['hour', 'carb_g', 'fluid_ml', 'sodium_mg', 'temp_c', 'humidity']

        if (!$currentWeather || !isset($currentWeather['temp_c']) || !isset($currentWeather['humidity'])) {
            return "Weather data not recorded for this hour."; // Fallback message
        }

        $summary = sprintf(
            "%s°C, %s%% Hum.",
            number_format($currentWeather['temp_c'], 1), // Already rounded potentially by calculator
            number_format($currentWeather['humidity'])
        );


        $previousWeather = $this->plan->hourly_targets_data[$hourIndex - 1] ?? null;
        if ($previousWeather) {
            $tempDiff = $currentWeather['temp_c'] - $previousWeather['temp_c'];
            $humidityDiff = $currentWeather['humidity'] - $previousWeather['humidity'];

            // Format the differences
            $summary .= sprintf(
                " (Δ: %s°C, %s%%)",
                number_format($tempDiff, 1),
                number_format($humidityDiff)
            );
        }

        return $summary;
    }

    /**
     * Calculates totals needed for preparation based on plan items, grouped by TYPE.
     */
    protected function calculatePreparationSummary(Collection $items): array
    {
        $summary = ['bottles_needed' => 0, 'products' => [], 'total_items' => $items->count()];
        $bottleSizeMl = 750;
        $totalFluidMl = 0;
        $productQuantities = [];

        foreach ($items as $item) {
            $totalFluidMl += $item->calculated_fluid_ml ?? 0;

            $productId = $item->product_id
                ?? ($item->product_name_override === 'Plain Water' ? 'WATER' : null)
                ?? $item->product_name_override
                ?? 'unknown_' . ($item->instruction_type ?? Str::random(5));

            if (!isset($productQuantities[$productId])) {
                $resolvedName = $item->product_name;
                if (empty($resolvedName)) {
                    $resolvedName = $item->product_name_override;
                }
                if (empty($resolvedName) && $item->relationLoaded('product') && $item->product && !empty($item->product->name)) {
                    $resolvedName = $item->product->name;
                }

                $productType = 'unknown';
                $isWaterByIdKey = ($productId === 'WATER');

                if ($isWaterByIdKey) {
                    $productType = Product::TYPE_PLAIN_WATER;
                    if (empty($resolvedName)) {
                        $resolvedName = 'Plain Water';
                    }
                } elseif ($item->relationLoaded('product') && $item->product) {
                    $productType = $item->product->type;
                    if ($productType === Product::TYPE_PLAIN_WATER && empty($resolvedName)) {
                        $resolvedName = 'Plain Water';
                    }
                }

                $finalName = !empty($resolvedName) ? $resolvedName : 'Unknown Item';

                $unit = 'item';
                $servingGrams = null;
                $servingMixMl = null;

                if ($isWaterByIdKey) {
                    $unit = 'ml';
                } elseif ($item->relationLoaded('product') && $item->product) {
                    $unit = $item->product->serving_size_units ?? 'item';
                    $servingGrams = $item->product->serving_size_g;
                    $servingMixMl = $item->product->serving_size_ml;
                }

                $productQuantities[$productId] = [
                    'product_id' => $item->product_id,
                    'name' => $finalName,
                    'type' => $productType,
                    'unit_description' => $unit,
                    'count' => 0,
                    'total_carbs' => 0.0,
                    'total_fluid' => 0.0,
                    'total_sodium' => 0.0,
                    'total_grams_powder' => 0.0,
                    'std_serving_grams_powder' => $servingGrams,
                    'std_serving_mix_volume_ml' => $servingMixMl,
                ];
            }

            $productQuantities[$productId]['count']++;
            $productQuantities[$productId]['total_carbs'] += $item->calculated_carbs_g ?? 0;
            $productQuantities[$productId]['total_fluid'] += $item->calculated_fluid_ml ?? 0;
            $productQuantities[$productId]['total_sodium'] += $item->calculated_sodium_mg ?? 0;

            if ($productQuantities[$productId]['type'] === Product::TYPE_DRINK_MIX) {
                $baseServMl = $productQuantities[$productId]['std_serving_mix_volume_ml'];
                $baseServGrams = $productQuantities[$productId]['std_serving_grams_powder'];
                $consumedMl = $item->calculated_fluid_ml ?? 0;

                if ($baseServMl && $baseServGrams && $consumedMl > 0 && $baseServMl > 0) { // Added $baseServMl > 0 for safety
                    $proportion = $consumedMl / $baseServMl;
                    $productQuantities[$productId]['total_grams_powder'] += ($baseServGrams * $proportion);
                }
            }
        }

        // --- Format the display string for each product ---
        foreach ($productQuantities as $id => &$prodInfo) {
            // Initialize keys to ensure they always exist
            $prodInfo['total_qty_desc'] = 'N/A'; // Default value if something goes wrong
            $prodInfo['notes'] = '';         // Default value

            // Defensively get type and unit, defaulting if somehow not set
            $productType = $prodInfo['type'] ?? 'unknown';
            $unit = $prodInfo['unit_description'] ?? 'item';

            try {
                if ($productType === Product::TYPE_DRINK_MIX) {
                    $totalGrams = round($prodInfo['total_grams_powder'] ?? 0);
                    $servingsEstimate = '?';
                    $stdServingGrams = $prodInfo['std_serving_grams_powder'] ?? 0;
                    if ($stdServingGrams > 0) {
                        $servingsEstimate = round($totalGrams / $stdServingGrams, 1);
                    }
                    $prodInfo['total_qty_desc'] = "{$totalGrams}g total powder (~{$servingsEstimate} {$unit}s)";
                    $prodInfo['notes'] = sprintf(
                        "Provides approx. %dg C, %dml F, %dmg S",
                        round($prodInfo['total_carbs'] ?? 0),
                        round($prodInfo['total_fluid'] ?? 0),
                        round($prodInfo['total_sodium'] ?? 0)
                    );
                } elseif ($productType === Product::TYPE_PLAIN_WATER) {
                    $prodInfo['total_qty_desc'] = round($prodInfo['total_fluid'] ?? 0) . "ml total";
                    // notes remains ""
                } else { // Gels, Bars, 'unknown' type etc.
                    $count = $prodInfo['count'] ?? 0;
                    $baseUnit = trim(preg_replace('/^[0-9.]+\s*/', '', (string)$unit));
                    if (empty($baseUnit)) $baseUnit = 'item';
                    // Pluralize for 0, and counts > 1. For 1, use singular.
                    $pluralUnit = ($count === 1) ? $baseUnit : Str::plural($baseUnit);
                    $prodInfo['total_qty_desc'] = $count . " " . $pluralUnit;

                    if ($count > 0) {
                        $avgCarbs = round(($prodInfo['total_carbs'] ?? 0) / $count);
                        $avgSodium = round(($prodInfo['total_sodium'] ?? 0) / $count);
                        $prodInfo['notes'] = "Avg: {$avgCarbs}g C, {$avgSodium}mg S per {$baseUnit}.";
                    } else {
                        // notes remains ""
                    }
                }
            } catch (\Throwable $e) {
                // Log the error if you have a logging mechanism, e.g., \Log::error(...)
                // For UI, it will show 'N/A' or you can set a specific error message
                $prodInfo['total_qty_desc'] = 'Error processing item details';
                // Optionally set notes to the error message for debugging, but be cautious with exposing raw errors to UI
                // $prodInfo['notes'] = 'Error: ' . $e->getMessage();
            }
        }
        unset($prodInfo); // Important when using references in foreach

        $summary['bottles_needed'] = ($bottleSizeMl > 0 && $totalFluidMl > 0) ? ceil($totalFluidMl / $bottleSizeMl) : 0;
        $summary['products'] = array_values($productQuantities);

        return $summary;
    }

    /** Helper to get the appropriate icon based on Product Type Constants */
    public function getPrepItemIcon(string $type): string
    {
        return $this->productTypeIcons[$type] ?? ($this->productTypeIcons['default'] ?? 'heroicon-o-question-mark-circle');
    }


    public function render()
    {
        return view('livewire.plan-viewer') // Ensure this points to your Blade view file
            ->layout('layouts.app');
    }
}
