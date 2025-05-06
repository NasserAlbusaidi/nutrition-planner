<?php

namespace App\Livewire;

use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Support\Str; // Import Str facade

class PlanViewer extends Component
{
    public Plan $plan; // Use route model binding

    // Map product types to Heroicons (outline style)
    // You might need to adjust these based on actual product types in your DB
    public $productTypeIcons = [
        'drink_mix' => 'heroicon-o-beaker',
        'gel' => 'heroicon-o-bolt', // Or another icon representing energy
        'chews' => 'heroicon-o-cube',
        'bar' => 'heroicon-o-rectangle-stack', // Or stop?
        'real_food' => 'heroicon-o-archive-box', // Placeholder
        'water' => 'heroicon-o-underline', // Corrected icon name
        'other' => 'heroicon-o-question-mark-circle',
    ];

    // Map instruction types to icons
    public $instructionIcons = [
        'drink' => 'heroicon-o-beaker', // Consistent with drink mix
        'consume' => 'heroicon-o-clipboard-document-check', // General consumption
    ];


    public function mount(Plan $plan)
    {
        if ($plan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        // Eager load items and their products (product might be null for water)
        $this->plan = $plan->load(['items' => function ($query) {
            $query->with('product')->orderBy('time_offset_seconds'); // Order items here
        }]);
    }

    public function formatDuration($seconds)
    {
        if ($seconds < 0) return 'N/A';
        return gmdate("H:i:s", $seconds);
    }

    public function formatTimeOffset($seconds)
    {
        if ($seconds < 0) return 'N/A';
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return sprintf('%02d:%02d', $minutes, $remainingSeconds); // Format as MM:SS
    }

     // Helper to get the appropriate icon for a schedule item
     public function getItemIcon(object $item): string
     {
         // Prioritize product type if available and mapped
         if ($item->product_id === null && Str::contains($item->product_name_override ?? '', 'Water')) {
             return $this->productTypeIcons['water']; // Specific water icon
         } elseif ($item->product && isset($this->productTypeIcons[$item->product->type])) {
             return $this->productTypeIcons[$item->product->type];
         }
         // Fallback to instruction type
         elseif (isset($this->instructionIcons[$item->instruction_type])) {
             return $this->instructionIcons[$item->instruction_type];
         }
         // Default fallback
         return 'heroicon-o-clipboard-document-list';
     }

    public function render()
    {
        return view('livewire.plan-viewer')
            ->layout('layouts.app');
    }
}
