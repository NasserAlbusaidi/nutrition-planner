<div> {{-- Livewire components need a single root element --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Flash Messages --}}
            @if (session()->has('message'))
                <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-900 dark:text-green-300 border border-green-300 dark:border-green-700"
                    role="alert">
                    {{ session('message') }}
                </div>
            @endif
            @if (session()->has('error'))
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-900 dark:text-red-300 border border-red-300 dark:border-red-700"
                    role="alert">
                    {{ session('error') }}
                </div>
            @endif


            {{-- 1. Personalized Welcome Message --}}
            <div class="px-4 sm:px-0">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 leading-tight">
                    Welcome back, {{ Auth::user()->name }}!
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ready to plan your next performance?</p>
            </div>

            {{-- 2. Grid Layout Container --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Column 1: Main Actions --}}
                <div class="lg:col-span-2 space-y-6">
                    {{-- 3. Create Plan Card --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                        <div
                            class="p-6 text-gray-900 dark:text-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex-grow mb-4 sm:mb-0 sm:mr-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Start Planning</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Create a detailed nutrition plan for your next endurance activity.
                                </p>
                            </div>
                            <a href="{{ route('plans.create.select-route') }}"
                                class="flex-shrink-0 inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 whitespace-nowrap">
                                <x-heroicon-o-plus-circle class="w-5 h-5 mr-2 -ml-1" />
                                Create New Plan
                            </a>
                        </div>
                    </div>

                    {{-- 4. Recent Plans Card --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Nutrition
                                    Plans</h3>
                            </div>

                            @if (isset($recentPlans) && $recentPlans->count() > 0)
                                <ul class="space-y-4">
                                    @foreach ($recentPlans as $plan)
                                        {{-- Use wire:key for efficient DOM diffing in loops --}}
                                        <li wire:key="plan-{{ $plan->id }}"
                                            class="border border-gray-200 dark:border-gray-700 rounded-lg transition duration-150 ease-in-out @if ($confirmingPlanDeletionId !== $plan->id) hover:bg-gray-50 dark:hover:bg-gray-700/50 @endif">
                                            {{-- Top section with details and buttons --}}
                                            <div
                                                class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4">
                                                <div class="flex-grow mb-3 sm:mb-0 sm:pr-4">
                                                    {{-- Plan Name Link --}}
                                                    <a href="{{ route('plans.show', $plan) }}"
                                                        class="text-base font-semibold text-indigo-700 dark:text-indigo-400 hover:underline block truncate">
                                                        {{ $plan->name }}
                                                    </a>
                                                    {{-- Metadata --}}
                                                    <p
                                                        class="text-sm text-gray-500 dark:text-gray-400 mt-1 flex items-center flex-wrap">
                                                        <x-heroicon-s-calendar
                                                            class="h-4 w-4 mr-1 text-gray-400 dark:text-gray-500 inline-block flex-shrink-0" />
                                                        <span class="mr-2">Created:
                                                            {{ $plan->created_at->format('M d, Y') }}</span>
                                                        @if ($plan->strava_route_name)
                                                            <span
                                                                class="mx-1 text-gray-300 dark:text-gray-600 hidden sm:inline-block">|</span>
                                                            <x-heroicon-s-map-pin
                                                                class="h-4 w-4 mr-1 text-gray-400 dark:text-gray-500 inline-block sm:ml-0 ml-2 mt-1 sm:mt-0 flex-shrink-0" />
                                                            <span class="mt-1 sm:mt-0 inline-block">For:
                                                                {{ Str::limit($plan->strava_route_name, 30) }}</span>
                                                        @endif
                                                    </p>
                                                </div>

                                                {{-- Action Buttons Group - Conditionally Visible --}}
                                                <div class="flex-shrink-0 self-start sm:self-center flex items-center space-x-2"
                                                    @if ($confirmingPlanDeletionId === $plan->id) style="display: none;" @endif>
                                                    {{-- Hide if confirming delete --}}

                                                    {{-- View Plan Button --}}
                                                    <a href="{{ route('plans.show', $plan) }}" title="View Plan"
                                                        class="inline-flex items-center justify-center p-2 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 whitespace-nowrap">
                                                        <x-heroicon-s-eye class="h-4 w-4" />
                                                    </a>

                                                    {{-- Edit Button --}}
                                                    <a href="{{ route('plans.edit', $plan) }}" title="Edit Plan"
                                                        class="inline-flex items-center justify-center p-2 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 whitespace-nowrap">
                                                        <x-heroicon-s-pencil-square class="h-4 w-4" />
                                                    </a>

                                                    {{-- Initial Delete Button (starts confirmation) --}}
                                                    <button type="button" title="Delete Plan"
                                                        wire:click="confirmPlanDeletion({{ $plan->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="confirmPlanDeletion({{ $plan->id }})"
                                                        class="inline-flex items-center justify-center p-2 border border-transparent shadow-sm text-xs font-medium rounded-md text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 whitespace-nowrap disabled:opacity-50">
                                                        <span wire:loading.remove
                                                            wire:target="confirmPlanDeletion({{ $plan->id }})">
                                                            <x-heroicon-s-trash class="h-4 w-4" />
                                                        </span>
                                                        <span wire:loading
                                                            wire:target="confirmPlanDeletion({{ $plan->id }})">
                                                            <svg class="animate-spin h-4 w-4 text-gray-600 dark:text-gray-400"
                                                                xmlns="http://www.w3.org/2000/svg" fill="none"
                                                                viewBox="0 0 24 24">...</svg> {{-- Shortened SVG --}}
                                                        </span>
                                                    </button>
                                                </div>
                                            </div> {{-- End Top Section Flex --}}

                                            {{-- Confirmation Section - Conditionally Visible --}}
                                            @if ($confirmingPlanDeletionId === $plan->id)
                                                <div
                                                    class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 border-t border-red-200 dark:border-red-800/50">
                                                    {{-- Subtle top border --}}
                                                    <p
                                                        class="text-sm font-medium text-red-800 dark:text-red-300 text-center sm:text-left">
                                                        Are you sure you want to delete this plan? This action cannot be
                                                        undone.
                                                    </p>
                                                    <div
                                                        class="mt-2 flex flex-col space-y-2 sm:space-y-0 sm:flex-row sm:justify-end sm:space-x-3">
                                                        {{-- Cancel Button --}}
                                                        <button type="button" wire:click="cancelPlanDeletion"
                                                            wire:loading.attr="disabled"
                                                            wire:target="deletePlan({{ $plan->id }})"
                                                            class="w-full sm:w-auto justify-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                                            Cancel
                                                        </button>
                                                        {{-- Confirm Delete Button --}}
                                                        <button type="button"
                                                            wire:click="deletePlan({{ $plan->id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="deletePlan({{ $plan->id }})"
                                                            class="w-full sm:w-auto inline-flex items-center justify-center px-3 py-1.5 border border-transparent shadow-sm text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50">
                                                            <svg wire:loading
                                                                wire:target="deletePlan({{ $plan->id }})"
                                                                class="animate-spin -ml-0.5 mr-2 h-4 w-4 text-white"
                                                                xmlns="http://www.w3.org/2000/svg" fill="none"
                                                                viewBox="0 0 24 24">...</svg> {{-- Shortened SVG --}}
                                                            Confirm Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                {{-- Empty State --}}
                                <div
                                    class="text-center py-8 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                                    {{-- ... empty state content ... --}}
                                    <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No plans yet
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating
                                        your first nutrition plan.</p>
                                    <div class="mt-6">
                                        <a href="{{ route('plans.create.select-route') }}"
                                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                            <x-heroicon-o-plus-circle class="w-5 h-5 mr-2 -ml-1" />
                                            Create First Plan
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div> {{-- End Recent Plans Card --}}
                </div> {{-- End Column 1 --}}


                {{-- Column 2: Secondary Info/Links --}}
                <div class="space-y-6">

                    {{-- Weather Card --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Local Weather</h3>
                                {{-- Weather Icon based on data --}}
                                @if ($weatherData && $weatherData['current'])
                                    {{-- Placeholder: Add more specific icon logic based on $weatherData['current']['weather_code'] if desired --}}
                                    @if ($weatherData['current']['is_day'])
                                        <x-heroicon-o-sun class="h-6 w-6 text-yellow-500" />
                                    @else
                                        <x-heroicon-o-moon class="h-6 w-6 text-gray-400" />
                                    @endif
                                @else
                                    <x-heroicon-o-map-pin class="h-6 w-6 text-gray-400" />
                                @endif
                            </div>

                            {{-- Loading State (Only shows when Livewire method is running) --}}
                            <div wire:loading wire:target="fetchWeatherForBrowserLocation, handleLocationError">
                                <div class="text-center py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <svg class="animate-spin h-6 w-6 text-indigo-500 mx-auto mb-2"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Processing location...
                                </div>
                            </div>

                            {{-- Display Weather or Prompt (Hide when Livewire is processing location) --}}
                            <div wire:loading.remove wire:target="fetchWeatherForBrowserLocation, handleLocationError">

                                {{-- 1. Geolocation Error Display --}}
                                @if ($geolocationError)
                                    <div class="text-center py-6 text-sm text-red-600 dark:text-red-400">
                                        <x-heroicon-o-exclamation-triangle class="mx-auto h-8 w-8 text-red-400 mb-2" />
                                        {{ $geolocationError }}
                                        @if (Str::contains($geolocationError, 'denied'))
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">You may need to
                                                allow location access in your browser settings for this site.</p>
                                        @endif
                                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            Alternatively, you can set a default location in your
                                            <a href="{{ route('profile.edit') }}"
                                                class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">profile</a>.
                                        </p>
                                    </div>

                                    {{-- 2. Weather Data Display --}}
                                @elseif($weatherData && $weatherData['current'])
                                    {{-- Current Weather --}}
                                    <div class="border-b dark:border-gray-700 pb-4 mb-4">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Now</p>
                                        <div class="flex items-center justify-between">
                                            <span class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                                                {{ round($weatherData['current']['temp_c']) }}°C
                                            </span>
                                            <div class="text-right text-sm">
                                                <p class="text-gray-600 dark:text-gray-300">Feels like
                                                    {{ round($weatherData['current']['apparent_temp_c']) }}°C</p>
                                                <p class="text-gray-500 dark:text-gray-400">
                                                    {{ $weatherData['current']['humidity'] }}% Humidity</p>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Hourly Forecast --}}
                                    @if (!empty($weatherData['hourly']))
                                        <div class="space-y-3">
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Next Hours
                                            </h4>
                                            @foreach ($weatherData['hourly'] as $hourly)
                                                @if ($loop->index < 6)
                                                    <div
                                                        class="flex items-center justify-between text-sm border-t dark:border-gray-700 pt-2">
                                                        <span
                                                            class="w-1/4 text-gray-600 dark:text-gray-300">{{ $hourly['time']->format('H:i') }}</span>
                                                        <span class="w-1/4 text-center">
                                                            @if (($hourly['precip_probability'] ?? 0) > 40)
                                                                <x-heroicon-s-cloud-arrow-down
                                                                    class="h-5 w-5 inline-block text-blue-400" />
                                                            @elseif(($hourly['temp_c'] ?? 15) < 5)
                                                                <x-heroicon-s-cloud
                                                                    class="h-5 w-5 inline-block text-blue-300" />
                                                            @elseif(($hourly['temp_c'] ?? 15) > 25)
                                                                <x-heroicon-s-sun
                                                                    class="h-5 w-5 inline-block text-yellow-500" />
                                                            @else<x-heroicon-s-cloud
                                                                    class="h-5 w-5 inline-block text-gray-400" />
                                                            @endif
                                                        </span>
                                                        <span
                                                            class="w-1/4 text-center text-gray-800 dark:text-gray-200 font-medium">{{ round($hourly['temp_c']) }}°C</span>
                                                        <span
                                                            class="w-1/4 text-right text-xs text-blue-500 dark:text-blue-400">{{ $hourly['precip_probability'] ?? 0 }}%</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Hourly forecast data
                                            unavailable.</p>
                                    @endif

                                    {{-- 3. Initial Prompt / Alpine Trigger --}}
                                @elseif(!$locationSet)
                                    {{-- Only show this if location isn't set AND no error/data exists yet --}}
                                    <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400"
                                        x-data="{ requesting: false, attempted: false }" {{-- Added 'attempted' flag --}} x-init="if (!attempted) { // Only run once
                                            attempted = true; // Mark as attempted
                                            console.log('[x-init] Weather: Checking. Saved Location: {{ $locationSet ? 'true' : 'false' }}');
                                            // Check PHP $locationSet again just to be sure state hasn't changed
                                            if (!{{ $locationSet ? 'true' : 'false' }}) {
                                                console.log('[x-init] Weather: Requesting browser location...');
                                                requesting = true;
                                                if ('geolocation' in navigator) {
                                                    navigator.geolocation.getCurrentPosition(
                                                        (position) => {
                                                            console.log('[x-init] Weather: Position received:', position.coords);
                                                            // Call Livewire. Display handled by Livewire render cycle.
                                                            $wire.call('fetchWeatherForBrowserLocation', position.coords.latitude, position.coords.longitude)
                                                                .catch((error) => { console.error('[x-init] Weather: Livewire success call failed', error); }) // Log potential wire call errors
                                                                .finally(() => {
                                                                    requesting = false; // May not be needed if component re-renders
                                                                    console.log('[x-init] Weather: Livewire call finished (success)');
                                                                });
                                                        },
                                                        (error) => {
                                                            console.error('[x-init] Weather: Geolocation Error:', error);
                                                            let message = 'Could not get location.';
                                                            switch (error.code) {
                                                                case error.PERMISSION_DENIED:
                                                                    message = 'Location permission denied.';
                                                                    break;
                                                                case error.POSITION_UNAVAILABLE:
                                                                    message = 'Location information unavailable.';
                                                                    break;
                                                                case error.TIMEOUT:
                                                                    message = 'Location request timed out.';
                                                                    break;
                                                            }
                                                            // Call Livewire. Display handled by Livewire render cycle.
                                                            $wire.call('handleLocationError', message)
                                                                .catch((error) => { console.error('[x-init] Weather: Livewire error call failed', error); })
                                                                .finally(() => {
                                                                    requesting = false; // May not be needed if component re-renders
                                                                    console.log('[x-init] Weather: Livewire call finished (error handled)');
                                                                });
                                                        }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 0 }
                                                    );
                                                } else {
                                                    console.error('[x-init] Weather: Geolocation not supported.');
                                                    $wire.call('handleLocationError', 'Geolocation not supported by browser.')
                                                        .finally(() => { requesting = false; });
                                                }
                                            } else {
                                                console.log('[x-init] Weather: Saved location found, Alpine doing nothing.');
                                            }
                                        } else {
                                            console.log('[x-init] Weather: Already attempted.');
                                        }">
                                        {{-- Loading Indicator managed by Alpine --}}
                                        <div x-show="requesting" x-transition>
                                            <svg class="animate-spin h-6 w-6 text-indigo-500 mx-auto mb-2"
                                                xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24">...</svg>
                                            Requesting your location...
                                        </div>
                                        {{-- Initial Prompt (Only shown if !requesting and no error/data) --}}
                                        <div x-show="!requesting" x-cloak>
                                            <x-heroicon-o-map-pin class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                                            Set a default location in your <a href="{{ route('profile.edit') }}"
                                                class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">profile</a>
                                            or allow browser access for local weather.
                                        </div>
                                    </div>

                                    {{-- 4. Fallback if weather fetch failed for SAVED location --}}
                                @else
                                    <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                                        <x-heroicon-o-exclamation-triangle
                                            class="mx-auto h-8 w-8 text-yellow-400 mb-2" />
                                        Weather forecast currently unavailable using saved location. Please try again
                                        later or check profile.
                                    </div>
                                @endif
                            </div> {{-- End wire:loading.remove --}}
                        </div>
                    </div>

                    {{-- Profile Card --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            {{-- ... Profile card content ... --}}
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Account</h3>
                                <x-heroicon-o-user-circle class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                            </div>
                            <a href="{{ route('profile.edit') }}"
                                class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium inline-flex items-center group">
                                Edit Your Profile & Settings
                                <x-heroicon-s-arrow-right
                                    class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform duration-150" />
                            </a>
                        </div>
                    </div>

                    {{-- Pantry Card --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            {{-- ... Pantry card content ... --}}
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pantry</h3>
                                <x-heroicon-o-archive-box class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                            </div>
                            <a href="{{ route('pantry.index') ?? '#' }}"
                                class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium inline-flex items-center group">
                                Manage Pantry Items
                                <x-heroicon-s-arrow-right
                                    class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform duration-150" />
                            </a>
                        </div>
                    </div>

                </div> {{-- End Column 2 --}}

            </div> {{-- End Grid --}}

        </div>
    </div>
</div>
