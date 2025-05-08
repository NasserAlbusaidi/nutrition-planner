<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Models\User; // Keep if User model properties are needed beyond Auth::user()
use App\Services\WeatherService; // Ensure this Service is correctly injected or available
use Illuminate\Support\Collection; // Added for type hinting clarity
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Exception; // Catch general exceptions
use Illuminate\Database\Eloquent\ModelNotFoundException; // Catch specific exception for finding plans

class Dashboard extends Component
{
    // --- Component Properties ---

    // Data for the view
    public ?Collection $recentPlans = null; // Use null initially, load in mount/helper
    public ?array $weatherData = null;      // Holds processed weather data or null if unavailable/error
    public bool $locationSet = false;       // Flag: Does the authenticated user have lat/lon saved?
    public ?string $geolocationError = null;// Holds browser geolocation error messages, if any
    // Note: Removed $browserLocationRequested as its utility seems limited if Alpine handles initial attempt logic

    // State for UI interactions
    public ?int $confirmingPlanDeletionId = null; // Tracks which plan ID deletion is being confirmed

    // Service property - Filled by boot() or Dependency Injection
    protected WeatherService $weatherService;

    // --- Lifecycle Hooks & Dependency Injection ---

    /**
     * Inject services when the component boots.
     */
    public function boot(WeatherService $weatherService): void
    {
        $this->weatherService = $weatherService;
    }

    /**
     * Runs once when the component is first mounted.
     * Loads initial data like recent plans and weather based on saved location.
     */
    public function mount(): void
    {
        // Should be protected by middleware, but double-check user is authenticated
        if (!Auth::check()) {
             // Redirect or handle unauthenticated state if necessary
             // For now, assume middleware handles this
            return;
        }

        $this->loadRecentPlans();
        $this->checkAndLoadWeather(); // Attempt to load weather using saved location first
    }

    // --- Public Actions (Called from Blade) ---

    /**
     * Sets the ID of the plan whose deletion needs confirmation display.
     * Triggered by the initial delete button.
     */
    public function confirmPlanDeletion(int $planId): void
    {
        $this->confirmingPlanDeletionId = $planId;
        Log::debug("Dashboard: Confirming deletion for plan ID: {$planId}");
    }

    /**
     * Resets the deletion confirmation state.
     * Triggered by the 'Cancel' button in the confirmation UI.
     */
    public function cancelPlanDeletion(): void
    {
        $this->reset('confirmingPlanDeletionId'); // Use Livewire's reset helper
        Log::debug("Dashboard: Plan deletion confirmation cancelled.");
    }

    /**
     * Deletes the specified plan after confirmation.
     * Triggered by the 'Confirm Delete' button.
     */
    public function deletePlan(int $planId): void
    {
        // Optional extra check: Ensure we are actually confirming this ID
        if ($this->confirmingPlanDeletionId !== $planId) {
            Log::warning("Dashboard: Attempted to delete plan ID {$planId} without confirmation state matching.");
            $this->reset('confirmingPlanDeletionId'); // Reset state anyway
            return;
        }

        $userId = Auth::id();
        Log::info("Dashboard: Attempting to delete plan ID: {$planId} for user: {$userId}");

        try {
            // FindOrFail scoped to the current user ensures authorization and existence
            $plan = Plan::where('user_id', $userId)->findOrFail($planId);

            $planName = $plan->name; // Store name for message before deleting
            $plan->delete(); // Perform deletion

            Log::info("Dashboard: Successfully deleted plan '{$planName}' (ID: {$planId})");
            session()->flash('message', "Plan '{$planName}' deleted successfully.");

        } catch (ModelNotFoundException $e) {
            Log::error("Dashboard: Plan deletion failed - Plan ID {$planId} not found or unauthorized for user {$userId}.");
            session()->flash('error', 'Plan not found or you do not have permission to delete it.');
        } catch (Exception $e) {
            Log::error("Dashboard: Error deleting plan ID: {$planId}. Error: " . $e->getMessage());
            session()->flash('error', 'An error occurred while deleting the plan.');
        } finally {
            // ALWAYS reset confirmation and reload plans regardless of success/failure
            $this->reset('confirmingPlanDeletionId');
            $this->loadRecentPlans(); // Refresh the displayed list
        }
    }

    /**
     * Fetches weather data using coordinates provided by browser geolocation (via AlpineJS).
     */
    public function fetchWeatherForBrowserLocation(float $latitude, float $longitude): void
    {
        Log::info("Dashboard: Fetching weather using BROWSER location: {$latitude}, {$longitude}");
        $this->geolocationError = null; // Reset any previous geo error
        $this->_fetchAndSetWeatherData($latitude, $longitude);
        // $this->browserLocationRequested = true; // This flag is potentially redundant if Alpine 'attempted' handles the UI state
    }

    /**
     * Sets the component state when browser geolocation fails.
     */
    public function handleLocationError(string $errorMessage): void
    {
        Log::warning("Dashboard: Browser location error - " . $errorMessage);
        $this->geolocationError = "Could not get location: " . $errorMessage; // Display specific error
        $this->weatherData = null; // Clear any old weather data
        // $this->browserLocationRequested = true; // Redundant flag?
    }


    // --- Protected/Private Helper Methods ---

    /**
     * Fetches the user's most recent plans from the database.
     */
    protected function loadRecentPlans(): void
    {
        if (!Auth::check()) return; // Don't try if not logged in

        try {
             // Use relationship for cleaner query (assuming User model has `plans()` relationship)
            $this->recentPlans = Auth::user()->plans()
                                      ->orderBy('created_at', 'desc')
                                      ->take(5) // Sensible limit for dashboard
                                      ->get();
             Log::debug('Dashboard: Recent plans loaded.', ['count' => $this->recentPlans?->count()]);
        } catch (Exception $e) {
            Log::error("Dashboard: Failed to load recent plans.", ['userId' => Auth::id(), 'message' => $e->getMessage()]);
            $this->recentPlans = collect(); // Ensure it's an empty collection on error
            session()->flash('error', 'Could not load recent plans.');
        }
    }

    /**
     * Checks for saved location and attempts to load weather if found.
     * Sets the $locationSet property.
     */
    protected function checkAndLoadWeather(): void
    {
        if (!Auth::check()) return;

        $user = Auth::user();
        // Ensure lat/lon are not just empty strings or non-numeric
        $latitude = filter_var($user->latitude, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($user->longitude, FILTER_VALIDATE_FLOAT);

        $this->locationSet = ($latitude !== false && $longitude !== false);
        Log::debug('Dashboard: Saved location check', ['isSet' => $this->locationSet, 'lat'=>$user->latitude, 'lon'=>$user->longitude]); // Log raw values

        if ($this->locationSet) {
            Log::info("Dashboard: Fetching weather using SAVED location for user {$user->id}");
            $this->_fetchAndSetWeatherData($latitude, $longitude);
        } else {
            Log::info("Dashboard: User {$user->id} has no SAVED/valid location.");
        }
    }

    /**
     * Internal helper to call the WeatherService and update component state.
     */
    private function _fetchAndSetWeatherData(float $latitude, float $longitude): void
    {
        try {
            // Fetch current weather and a few hours forecast
            $this->weatherData = $this->weatherService->getCurrentAndHourlyForecast($latitude, $longitude, 6); // Fetch 6 hours ahead

            if (empty($this->weatherData) || !isset($this->weatherData['current']) || empty($this->weatherData['hourly'])) {
                Log::warning("Dashboard: WeatherService returned incomplete data.", ['lat' => $latitude, 'lon' => $longitude, 'data' => $this->weatherData]);
                $this->weatherData = null; // Reset on incomplete data
                $this->geolocationError = "Could not retrieve complete weather data."; // Set generic error for user
            } else {
                Log::info("Dashboard: Weather data processed successfully.", ['lat' => $latitude, 'lon' => $longitude]);
            }

        } catch (Exception $e) {
            Log::error("Dashboard: Failed to fetch weather data via WeatherService: " . $e->getMessage(), [
                'lat' => $latitude, 'lon' => $longitude, 'trace' => $e->getTraceAsString() // Add trace for debugging service issues
            ]);
            $this->weatherData = null; // Reset weather data on error
            // Display specific error only if it wasn't a geolocation browser error already set
            if ($this->geolocationError === null) {
                $this->geolocationError = "Weather service is currently unavailable. Please try again later.";
            }
        }
    }


    // --- Rendering ---

    /**
     * Renders the component view.
     */
    public function render()
    {
        // Data is prepared in mount and actions, simply return the view
        return view('livewire.dashboard')
               ->layout('layouts.app'); // Standard layout
    }
}
