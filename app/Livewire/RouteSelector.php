<?php

namespace App\Livewire;

use App\Services\StravaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RouteSelector extends Component
{
    public $routes = [];
    public $isLoading = true;
    public $errorMessage = null;
    // Removed: public $selectedRouteForPreview = null;

    public function mount(StravaService $stravaService)
    {
        $this->loadRoutes($stravaService);
    }

    public function loadRoutes(StravaService $stravaService)
    {
        $this->isLoading = true;
        $this->errorMessage = null;
        // Removed: $this->selectedRouteForPreview = null;
        $user = Auth::user();

        if (!$user->strava_user_id || !$user->strava_access_token) {
            $this->errorMessage = 'Strava account not connected. Please connect it via your profile.';
            $this->isLoading = false;
            $this->routes = [];
            $this->dispatch('routes-loaded'); // Dispatch even on error to potentially clear old maps
            return;
        }

        // Refresh user data in case tokens were updated
        $user = Auth::user()->fresh(); // Use fresh() to get latest data
        $fetchedRoutes = $stravaService->getUserRoutes($user); // Ensure this returns polyline

        if ($fetchedRoutes === null || (is_object($fetchedRoutes) && $fetchedRoutes->isEmpty())) {
             if ($fetchedRoutes === null) {
                $this->errorMessage = 'Could not fetch routes from Strava. Check connection or try again.';
            } else {
                 // Empty collection is not an error, just no routes
                 $this->errorMessage = null; // Clear potential previous error
            }
            $this->routes = [];
        } elseif (is_array($fetchedRoutes) && empty($fetchedRoutes)) {
             $this->errorMessage = null; // Clear potential previous error
             $this->routes = [];
        } else {
            // Ensure routes is an array/collection for consistent handling
            $this->routes = is_array($fetchedRoutes) ? $fetchedRoutes : $fetchedRoutes->all();
            // Log::info("Routes Loaded:", $this->routes); // Debugging
        }

        $this->isLoading = false;
        // Dispatch event *after* routes are loaded and component state is updated
        $this->dispatch('routes-loaded');
        Log::info("Dispatched 'routes-loaded' event.");
    }

    // Removed: public function previewRoute(int $routeIndex)

    /**
     * Triggered by the "Select" button within a table row.
     */
    public function confirmSelection(string $routeId)
    {
        Log::info("Attempting to confirm selection for Route ID: " . $routeId);
        $selectedRoute = collect($this->routes)->firstWhere('id', $routeId);

        if ($selectedRoute) {
            Log::info("Route found:", $selectedRoute);

            // *** Get the polyline (handle if it's missing) ***
            $polyline = $selectedRoute['summary_polyline'] ?? '';
            // Optional: Basic check if polyline looks valid - adjust if needed
            if (empty($polyline) || strlen($polyline) < 5) {
                 Log::warning("Route ID {$routeId} has missing or invalid polyline. Sending empty string.");
                 $polyline = ''; // Send empty string instead of null or invalid data
            }

            if ($selectedRoute) {
                $routeParams = [
                    'routeId' => $selectedRoute['id'],
                    'routeName' => urlencode($selectedRoute['name']),
                    'distance' => $selectedRoute['distance'] ?? 0,
                    'elevation' => $selectedRoute['elevation_gain'] ?? 0,
                    // *** REMOVE polyline from route params ***
                    // 'polyline' => $polyline,
                ];

                Log::info("Redirecting to plans.create.form with params:", $routeParams);
                return redirect()->route('plans.create.form', $routeParams);

        }

        Log::error("Route ID {$routeId} NOT FOUND in current routes list during confirmation.");
        session()->flash('error', 'Selected route could not be found. Please refresh and try again.');
    }

    }
    public function render()
    {
        return view('livewire.route-selector')
                ->layout('layouts.app');
    }
}
