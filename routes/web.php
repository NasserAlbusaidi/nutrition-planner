<?php

// use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Livewire\UserProfile;
use App\Livewire\PantryManager;
use App\Livewire\RouteSelector;
use App\Livewire\PlanForm;
use App\Livewire\Dashboard;
use App\Livewire\PlanViewer;
use App\Livewire\PlanEditForm; // <-- Import the Edit Form component

use App\Http\Controllers\StravaController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', Dashboard::class) // Use the Livewire component
     ->middleware(['auth', 'verified'])
     ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', UserProfile::class)->name('profile.edit'); // Standard name is often profile.edit
    Route::get('/pantry', PantryManager::class)->name('pantry.index');

    // --- Planning Flow ---
    Route::get('/plans/create/select-route', RouteSelector::class)->name('plans.create.select-route');
    Route::get('/plans/create/form/{routeId}/{routeName}/{distance}/{elevation}', PlanForm::class)
         ->name('plans.create.form');


    // --- Plan Management ---
    Route::get('/plans/{plan}', PlanViewer::class)->name('plans.show'); // View Plan
    // *** ADD THIS ROUTE ***
    Route::get('/plans/{plan}/edit', PlanEditForm::class)->name('plans.edit'); // Edit Plan Form

    // --- Strava Integration ---
    Route::get('/strava/redirect', [StravaController::class, 'redirectToStrava'])->name('strava.redirect');
    Route::get('/strava/callback', [StravaController::class, 'handleStravaCallback'])->name('strava.callback');
    Route::post('/strava/disconnect', [StravaController::class, 'disconnectStrava'])->name('strava.disconnect');

});

require __DIR__ . '/auth.php';
