<?php

namespace App\Livewire;

use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Support\Str;
use Carbon\CarbonInterval; // Import CarbonInterval
use Illuminate\Support\Facades\Log; // Import Log for debugging

class PlanViewer extends Component
{
    public Plan $plan; // Route model binding

    // --- Icon mapping for getItemIcon() helper ---
    // Using Heroicons v2 Outline (-o-) and Solid (-s-) names
    // Add more types as needed from your Product constants/types
    public $productTypeIcons = [
        // Keys should match the values used in Product::$type (e.g., Product::TYPE_DRINK_MIX)
        \App\Models\Product::TYPE_DRINK_MIX => 'heroicon-o-beaker',
        \App\Models\Product::TYPE_GEL => 'heroicon-o-bolt', // Lightning bolt for energy gel
        \App\Models\Product::TYPE_ENERGY_CHEW => 'heroicon-o-cube',
        \App\Models\Product::TYPE_ENERGY_BAR => 'heroicon-s-bars-3-bottom-left', // Example: Solid bar icon
        \App\Models\Product::TYPE_REAL_FOOD => 'heroicon-o-cake', // Cake or similar food icon
        \App\Models\Product::TYPE_HYDRATION_TABLET => 'heroicon-o-adjustments-horizontal', // Tablet / Settings
        \App\Models\Product::TYPE_PLAIN_WATER => 'heroicon-o-sparkles', // Placeholder - Beaker maybe better?
        // 'recovery_drink' => 'heroicon-o-arrows-pointing-in', // Example for recovery
        'default' => 'heroicon-o-clipboard-document-list', // Fallback for product type
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
         if(!$this->plan->relationLoaded('items')) {
             Log::warning('Plan items failed to eager load.', ['plan_id' => $plan->id]);
         } else {
              Log::info('Plan items loaded successfully.', ['plan_id' => $plan->id, 'item_count' => $this->plan->items->count()]);
         }
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


    public function render()
    {
        return view('livewire.plan-viewer') // Ensure this points to your Blade view file
            ->layout('layouts.app');
    }
}
