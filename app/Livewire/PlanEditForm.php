<?php

namespace App\Livewire;

use App\Models\Plan; // Make sure Plan model is imported
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Illuminate\Support\Facades\Log; // Optional: for logging

class PlanEditForm extends Component
{
    public Plan $plan; // Route model binding automatically injects the Plan

    // Form properties - these are bound to the input fields
    public string $name;
    public string $planned_start_datetime; // Use string for datetime-local input compatibility
    public string $planned_intensity;

    // Options for the intensity dropdown - keep consistent with PlanForm
    public $intensityOptions = [
        'easy' => 'Easy (<65% FTP)',
        'endurance' => 'Endurance (Zone 2, 65-75% FTP)',
        'tempo' => 'Tempo (Zone 3, 76-90% FTP)',
        'threshold' => 'Threshold (Zone 4, 91-105% FTP)',
        'race_pace' => 'Race Pace (~Threshold)',
        'steady_group_ride' => 'Steady Group Ride (~High Z2/Low Z3)',
    ];

    /**
     * Runs when the component is first mounted.
     * Initializes form properties from the existing Plan model.
     */
    public function mount(Plan $plan)
    {
        // Authorization: Ensure the authenticated user owns this plan
        if ($plan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.'); // Prevent users editing others' plans
        }
        $this->plan = $plan;

        // Initialize form properties with current plan data
        $this->name = $plan->name;
        // Format Carbon datetime from model to the string format required by datetime-local input
        $this->planned_start_datetime = $plan->planned_start_time->format('Y-m-d\TH:i');
        $this->planned_intensity = $plan->planned_intensity;

        Log::info("PlanEditForm mounted for Plan ID: {$plan->id}"); // Optional logging
    }

    /**
     * Defines the validation rules for the form properties.
     */
    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Ensure the datetime string matches the expected format and is not in the past
            'planned_start_datetime' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:now'],
            'planned_intensity' => ['required', Rule::in(array_keys($this->intensityOptions))], // Ensure selected value is valid
        ];
    }

    /**
     * Called when the form is submitted.
     * Validates input, updates the Plan model, and redirects.
     */
    public function updatePlan()
    {
        $validatedData = $this->validate();

        // Optional: Re-check authorization before saving, belts and braces
        if ($this->plan->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            // Update the plan model attributes
            $this->plan->name = $validatedData['name'];
            // Parse the string from the input back into a Carbon object for saving
            $this->plan->planned_start_time = Carbon::parse($validatedData['planned_start_datetime']);
            $this->plan->planned_intensity = $validatedData['planned_intensity'];

            // Note: We are NOT recalculating duration, totals, or schedule items here.
            // Only basic metadata is being updated.

            $this->plan->save(); // Save the changes to the database

            Log::info("Plan ID: {$this->plan->id} updated successfully by User ID: " . Auth::id());

            // Flash success message to the session
            session()->flash('message', 'Plan updated successfully!');

            // Redirect back to the plan viewer page for the updated plan
            return redirect()->route('plans.show', $this->plan);

        } catch (\Exception $e) {
            Log::error("Error updating Plan ID: {$this->plan->id}. Error: " . $e->getMessage());
            // Set an error message for the user (optional, depends on desired UX)
            session()->flash('error', 'An error occurred while saving the plan updates.');
            // Keep user on the edit form
        }
    }

    /**
     * Renders the component's view.
     */
    public function render()
    {
        // Pass any necessary data (though public properties are available automatically)
        return view('livewire.plan-edit-form')
                ->layout('layouts.app'); // Use your standard application layout
    }
}
