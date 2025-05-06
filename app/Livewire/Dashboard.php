<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Plan;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Log;

class Dashboard extends Component
{
    public $recentPlans;
    public $weatherData = null;
    public bool $locationSet = false;
    public bool $browserLocationRequested = false;
    public ?string $geolocationError = null;

    // *** NEW: Property to track which plan deletion is being confirmed ***
    public ?int $confirmingPlanDeletionId = null;

    protected WeatherService $weatherService;

    // ... (boot, mount, loadRecentPlans, checkAndLoadWeather, fetchWeatherForBrowserLocation, handleLocationError methods remain the same) ...
    public function boot(WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
    }

    public function mount()
    {
        $this->loadRecentPlans(); // Load plans initially
        $this->checkAndLoadWeather(); // Load weather initially
    }

    // Helper to load plans, can be called after delete
    public function loadRecentPlans()
    {
         $this->recentPlans = Auth::user()->plans()
                                  ->orderBy('created_at', 'desc')
                                  ->take(5)
                                  ->get();
    }

    // Helper for weather logic
    public function checkAndLoadWeather()
    {
         $user = Auth::user();
         $userLatitude = $user->latitude;
         $userLongitude = $user->longitude;
         $this->locationSet = $userLatitude && $userLongitude;

         if ($this->locationSet) {
            Log::info("Dashboard Livewire: Fetching weather using SAVED location for user {$user->id}");
            $this->weatherData = $this->weatherService->getCurrentAndHourlyForecast($userLatitude, $userLongitude, 6);
             if ($this->weatherData === null) {
                 Log::warning("Dashboard Livewire: Failed to retrieve weather data using saved location for user {$user->id}");
             }
         } else {
              Log::info("Dashboard Livewire: User {$user->id} has no SAVED location.");
         }
    }


    public function fetchWeatherForBrowserLocation(float $latitude, float $longitude)
    {
        Log::info("Dashboard Livewire: Fetching weather using BROWSER location: {$latitude}, {$longitude}");
        $this->geolocationError = null;
        $this->weatherData = $this->weatherService->getCurrentAndHourlyForecast($latitude, $longitude, 6);
        $this->browserLocationRequested = true;

        if ($this->weatherData === null) {
            Log::warning("Dashboard Livewire: Failed to retrieve weather data using browser location.");
             $this->geolocationError = "Could not fetch weather data for your current location.";
        }
    }

    public function handleLocationError(string $errorMessage)
    {
        Log::warning("Dashboard Livewire: Browser location error - " . $errorMessage);
        $this->geolocationError = "Could not get location: " . $errorMessage;
        $this->weatherData = null;
        $this->browserLocationRequested = true;
    }


    // *** NEW: Method to START the confirmation process ***
    public function confirmPlanDeletion($planId)
    {
        $this->confirmingPlanDeletionId = $planId;
    }

    // *** NEW: Method to CANCEL the confirmation process ***
    public function cancelPlanDeletion()
    {
        $this->confirmingPlanDeletionId = null;
    }

    // *** Existing deletePlan method (modified slightly) ***
    public function deletePlan($planId)
    {
        // Ensure we are actually confirming this one (extra check)
        if ($this->confirmingPlanDeletionId !== $planId) {
            return; // Or handle error
        }

        Log::info("Attempting to delete plan ID: {$planId} for user: " . Auth::id());
        try {
            // FindOrFail will throw exception if not found
            $plan = Plan::where('user_id', Auth::id())->findOrFail($planId); // Scope to user for security

            $planName = $plan->name;
            $plan->delete(); // Delete the plan

            Log::info("Successfully deleted plan '{$planName}' (ID: {$planId})");
            session()->flash('message', "Plan '{$planName}' deleted successfully.");

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
             Log::error("Attempted to delete non-existent or unauthorized plan ID: {$planId} for user " . Auth::id());
             session()->flash('error', 'Plan not found or you are not authorized to delete it.');
        } catch (\Exception $e) {
            Log::error("Error deleting plan ID: {$planId}. Error: " . $e->getMessage());
            session()->flash('error', 'An error occurred while deleting the plan.');
        } finally {
             // *** Reset confirmation state regardless of success/failure ***
             $this->confirmingPlanDeletionId = null;
             // Refresh the list of recent plans displayed on the dashboard
             $this->loadRecentPlans();
        }
    }


    public function render()
    {
        return view('livewire.dashboard')
               ->layout('layouts.app');
    }
}
