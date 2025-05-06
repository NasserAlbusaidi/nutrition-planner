<?php

namespace App\Http\Controllers; // Or App\Livewire if it's a Livewire component

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Plan; // Assuming Plan model is used for recent plans
use App\Services\WeatherService; // Import WeatherService
use Illuminate\Support\Facades\Log; // Import Log

// If using a controller:
use Illuminate\Routing\Controller; // Base controller
// If using Livewire component:
// use Livewire\Component;

class DashboardController extends Controller // Or extends Component for Livewire
{
    /**
     * Display the user's dashboard.
     *
     * @param WeatherService $weatherService Injected via service container
     * @return \Illuminate\View\View
     */
    public function index(WeatherService $weatherService) // Method name might be __invoke or render
    {
        $user = Auth::user();

        // --- Fetch Recent Plans ---
        $recentPlans = $user->plans()
                            ->orderBy('created_at', 'desc')
                            ->take(5) // Limit to 5 recent plans
                            ->get();

        // --- Fetch Weather ---
        $weatherData = null;
        $userLatitude = $user->latitude; // ASSUMING these exist on the User model
        $userLongitude = $user->longitude;
        $locationSet = $userLatitude && $userLongitude;

        if ($locationSet) {
            Log::info("Dashboard: Fetching weather for user {$user->id} at {$userLatitude},{$userLongitude}");
            $weatherData = $weatherService->getCurrentAndHourlyForecast($userLatitude, $userLongitude, 6); // Get current + 6 hours
             if ($weatherData === null) {
                 Log::warning("Dashboard: Failed to retrieve weather data for user {$user->id}");
             }
        } else {
            Log::info("Dashboard: User {$user->id} has not set their location for weather forecast.");
        }

        // --- Pass Data to View ---
        return view('dashboard', [
            'recentPlans' => $recentPlans,
            'weatherData' => $weatherData, // Pass the whole structure or null
            'locationSet' => $locationSet, // Pass flag to view
        ]);
    }
}
