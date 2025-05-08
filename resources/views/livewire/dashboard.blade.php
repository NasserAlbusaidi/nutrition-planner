<div> {{-- Livewire Component Root --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Flash Messages (Keep styling consistent) --}}
            <x-alerts.flash-message /> {{-- Optional: Assuming you have a component --}}
            {{-- Or keep the existing @if blocks for session messages --}}
            @if (session()->has('message'))
                <div class="p-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-900/80 dark:text-green-300 border border-green-300 dark:border-green-700 shadow-sm"
                    role="alert">
                    {{ session('message') }}
                </div>
            @endif
            @if (session()->has('error'))
                <div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-900/80 dark:text-red-300 border border-red-300 dark:border-red-700 shadow-sm"
                    role="alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- 1. Welcome Header --}}
            <div class="px-4 sm:px-0">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Welcome back, {{ Auth::user()->name }}!
                </h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Ready to fuel your next performance? Let's get planning.
                </p>
            </div>

            {{-- 2. Main Grid --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 xl:gap-8">

                {{-- ===== Left Column ===== --}}
                <div class="lg:col-span-2 space-y-6 xl:space-y-8">

                    {{-- Create Plan Card --}}
                    <div
                        class="bg-gradient-to-br from-white via-white to-indigo-50 dark:from-slate-800 dark:via-slate-800 dark:to-slate-900/50 p-6 shadow-lg rounded-lg border border-slate-200 dark:border-slate-700/80">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Plan Your Next
                                    Activity</h2>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Select a route and create a
                                    tailored nutrition strategy.</p>
                            </div>
                            <a href="{{ route('plans.create.select-route') }}"
                                class="flex-shrink-0 inline-flex items-center justify-center px-5 py-2.5 bg-indigo-600 border border-transparent rounded-md shadow-sm font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800 transition ease-in-out duration-150 whitespace-nowrap">
                                <x-heroicon-o-plus-circle class="w-5 h-5 mr-2" />
                                Create New Plan
                            </a>
                        </div>
                    </div>

                    {{-- Recent Plans Card --}}
                    <div
                        class="bg-white dark:bg-slate-800 overflow-hidden shadow-lg rounded-lg border border-slate-200 dark:border-slate-700/80">
                        <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Recent Plans</h2>
                        </div>
                        <div class="px-2 py-2 sm:px-3 sm:py-3"> {{-- Reduce padding slightly for list --}}
                            @if ($recentPlans && $recentPlans->count() > 0)
                                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach ($recentPlans as $plan)
                                        <li wire:key="plan-{{ $plan->id }}"
                                            class="relative py-4 px-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition duration-150 ease-in-out">
                                            {{-- Main Info & Actions Area --}}
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                                {{-- Left Side: Plan Details --}}
                                                <div class="flex-grow min-w-0 sm:pr-6 mb-3 sm:mb-0">
                                                    <a href="{{ route('plans.show', $plan) }}" class="group">
                                                        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 truncate"
                                                            title="{{ $plan->name }}">
                                                            {{ $plan->name }}
                                                        </h3>
                                                        <div
                                                            class="mt-1 flex items-center text-xs text-slate-500 dark:text-slate-400 flex-wrap gap-x-3">
                                                            <span class="inline-flex items-center whitespace-nowrap">
                                                                <x-heroicon-s-calendar
                                                                    class="h-3.5 w-3.5 mr-1 opacity-70" />
                                                                {{ $plan->created_at->format('M d, Y') }}
                                                            </span>
                                                            @if ($plan->strava_route_name)
                                                                <span
                                                                    class="inline-flex items-center whitespace-nowrap">
                                                                    <x-heroicon-s-map-pin
                                                                        class="h-3.5 w-3.5 mr-1 opacity-70" />
                                                                    <span title="{{ $plan->strava_route_name }}">For:
                                                                        {{ Str::limit($plan->strava_route_name, 25) }}</span>
                                                                </span>
                                                            @endif
                                                            {{-- ADD DURATION & NUTRIENT SUMMARY? Optional --}}
                                                            <span class="inline-flex items-center whitespace-nowrap">
                                                                <x-heroicon-o-clock
                                                                    class="h-3.5 w-3.5 mr-1 opacity-70" />
                                                                @php
                                                                    try {
                                                                        $formattedDuration = Carbon\CarbonInterval::seconds($plan->estimated_duration_seconds ?? 0)
                                                                            ->cascade()
                                                                            ->format('%H:%I:%S');
                                                                    } catch (\Throwable $th) {
                                                                        $formattedDuration = 'N/A';
                                                                    }
                                                                @endphp
                                                                {{ $formattedDuration }}
                                                            </span>
                                                            <span class="inline-flex items-center whitespace-nowrap"
                                                                title="Carbs/Fluid/Sodium (Approx)">
                                                                <x-heroicon-o-cube
                                                                    class="h-3.5 w-3.5 mr-1 text-amber-500 opacity-70" />
                                                                {{ round($plan->estimated_total_carbs_g) }}g
                                                                <span class="mx-0.5">/</span>
                                                                {{ round($plan->estimated_total_fluid_ml / 1000, 1) }}L
                                                                <span class="mx-0.5">/</span>
                                                                {{ round($plan->estimated_total_sodium_mg) }}mg
                                                            </span>
                                                        </div>
                                                    </a>
                                                </div>

                                                {{-- Right Side: Action Buttons (hide if confirming this item) --}}
                                                <div x-data="{ shown: {{ $confirmingPlanDeletionId === $plan->id ? 'false' : 'true' }} }" x-show="shown"
                                                    x-transition:leave="transition ease-in duration-100"
                                                    x-transition:leave-start="opacity-100"
                                                    x-transition:leave-end="opacity-0">
                                                    <div class="flex-shrink-0 flex items-center space-x-2">
                                                        <a href="{{ route('plans.show', $plan) }}" title="View"
                                                            class="p-1.5 text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                                            <x-heroicon-s-eye class="h-4 w-4" /> </a>
                                                        <a href="{{ route('plans.edit', $plan) }}" title="Edit"
                                                            class="p-1.5 text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                                            <x-heroicon-s-pencil-square class="h-4 w-4" /> </a>
                                                        <button type="button" title="Delete"
                                                            wire:click="confirmPlanDeletion({{ $plan->id }})"
                                                            wire:loading.attr="disabled"
                                                            class="p-1.5 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-500 rounded-full hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors disabled:opacity-50">
                                                            <x-heroicon-s-trash class="h-4 w-4" /> </button>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Confirmation Section --}}
                                            @if ($confirmingPlanDeletionId === $plan->id)
                                                <div x-data="{ open: true }" x-show="open"
                                                    x-transition:enter="transition ease-out duration-200"
                                                    x-transition:enter-start="opacity-0 translate-y-2"
                                                    x-transition:enter-end="opacity-100 translate-y-0"
                                                    x-transition:leave="transition ease-in duration-150"
                                                    x-transition:leave-start="opacity-100 translate-y-0"
                                                    x-transition:leave-end="opacity-0 translate-y-1"
                                                    class="mt-3 pl-4 pr-4 pb-3 sm:pl-14 border-t border-red-200 dark:border-red-800/50 pt-3 bg-red-50 dark:bg-red-900/20 rounded-b-lg">
                                                    <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                                        Confirm Deletion:</p>
                                                    <p class="text-xs text-red-700 dark:text-red-300 mt-1">Are you sure?
                                                        This action cannot be undone.</p>
                                                    <div class="mt-3 flex justify-end space-x-3">
                                                        <button type="button" wire:click="cancelPlanDeletion"
                                                            wire:loading.attr="disabled"
                                                            wire:target="deletePlan({{ $plan->id }})"
                                                            class="px-3 py-1 border border-slate-300 dark:border-slate-600 shadow-sm text-xs font-medium rounded text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800">Cancel</button>
                                                        <button type="button"
                                                            wire:click="deletePlan({{ $plan->id }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="deletePlan({{ $plan->id }})"
                                                            class="inline-flex items-center px-3 py-1 border border-transparent shadow-sm text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800 disabled:opacity-50">
                                                            <svg wire:loading
                                                                wire:target="deletePlan({{ $plan->id }})"
                                                                class="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5 text-white"
                                                                xmlns="http://www.w3.org/2000/svg" fill="none"
                                                                viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12"
                                                                    r="10" stroke="currentColor" stroke-width="4">
                                                                </circle>
                                                                <path class="opacity-75" fill="currentColor"
                                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                                </path>
                                                            </svg>
                                                            Delete
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
                                    class="text-center py-10 px-6 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-lg">
                                    <svg class="mx-auto h-10 w-10 text-slate-400" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" aria-hidden="true">
                                        <path vector-effect="non-scaling-stroke" stroke-linecap="round"
                                            stroke-linejoin="round" stroke-width="1"
                                            d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">No plans
                                        created yet</h3>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your created nutrition
                                        plans will appear here.</p>
                                    <div class="mt-5">
                                        <a href="{{ route('plans.create.select-route') }}"
                                            class="inline-flex items-center px-4 py-2 bg-indigo-100 text-indigo-700 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-indigo-800/60 dark:text-indigo-300 dark:hover:bg-indigo-700/60 dark:focus:ring-offset-slate-800 transition ease-in-out duration-150">
                                            Create Your First Plan
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div> {{-- End Recent Plans Card --}}

                </div> {{-- End Left Column --}}


                {{-- ===== Right Column ===== --}}
                <div class="space-y-6 xl:space-y-8">

                    {{-- Weather Card --}}
                    <div
                        class="bg-white dark:bg-slate-800 overflow-hidden shadow-lg rounded-lg border border-slate-200 dark:border-slate-700/80">
                        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Local Weather
                                </h3>
                                {{-- You can keep the logic for sun/moon icon based on 'is_day' here --}}
                                @php
                                    $weatherIcon = 'heroicon-o-map-pin'; // Default
                                    $iconColor = 'text-slate-400';
                                    if ($weatherData && $weatherData['current']) {
                                        if ($weatherData['current']['is_day'] ?? true) {
                                            $weatherIcon = 'heroicon-o-sun';
                                            $iconColor = 'text-yellow-500';
                                        } else {
                                            $weatherIcon = 'heroicon-o-moon';
                                            $iconColor = 'text-slate-400';
                                        }
                                        // Add more logic here for specific weather codes (cloudy, rainy etc)
                                    }
                                @endphp
                                <x-dynamic-component :component="$weatherIcon" class="h-5 w-5 {{ $iconColor }}" />
                            </div>
                        </div>
                        <div class="p-5"> {{-- Consistent padding --}}
                            {{-- Loading State --}}
                            <div wire:loading wire:target="fetchWeatherForBrowserLocation, handleLocationError"
                                class="py-5 text-center text-sm text-slate-500 dark:text-slate-400">
                                <x-loading-spinner class="mx-auto text-indigo-600" /> {{-- Assumes a spinner component --}}
                                <p class="mt-2">Getting local conditions...</p>
                            </div>

                            {{-- Display States --}}
                            <div wire:loading.remove wire:target="fetchWeatherForBrowserLocation, handleLocationError">
                                {{-- Error State --}}
                                @if ($geolocationError)
                                    <div
                                        class="text-center py-4 px-3 text-sm bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-300 rounded-lg border border-red-200 dark:border-red-800/50">
                                        <x-heroicon-o-exclamation-triangle class="mx-auto h-6 w-6 text-red-400 mb-1" />
                                        <p class="font-medium">{{ $geolocationError }}</p>
                                        @if (Str::contains($geolocationError, 'denied') || Str::contains($geolocationError, 'Geolocation not supported'))
                                            <p class="mt-1 text-xs text-red-500 dark:text-red-400">Ensure location
                                                services are enabled and permitted for this site in your browser/system
                                                settings.</p>
                                        @endif
                                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Set your default
                                            location in your <a href="{{ route('profile.edit') }}"
                                                class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">profile</a>.
                                        </p>
                                    </div>
                                    {{-- Weather Display --}}
                                @elseif($weatherData && $weatherData['current'])
                                    <div class="mb-5 text-center border-b border-slate-200 dark:border-slate-700 pb-5">
                                        <div class="text-5xl font-bold text-slate-800 dark:text-slate-100 mb-1">
                                            {{ round($weatherData['current']['temp_c']) }}<span
                                                class="text-3xl align-top">°C</span>
                                        </div>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">Feels like
                                            {{ round($weatherData['current']['apparent_temp_c']) }}°C
                                            <span class="mx-1">·</span> {{ $weatherData['current']['humidity'] }}%
                                            Humidity
                                        </p>
                                    </div>
                                    @if (!empty($weatherData['hourly']))
                                        <div class="space-y-2.5">
                                            <h4
                                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                                Forecast</h4>
                                            @foreach ($weatherData['hourly'] as $hourly)
                                                @if ($loop->index < 6)
                                                    {{-- Limit display --}}
                                                    <div
                                                        class="flex items-center justify-between text-sm border-t border-slate-100 dark:border-slate-700/80 pt-2">
                                                        <span
                                                            class="w-1/4 text-slate-500 dark:text-slate-300 tabular-nums">{{ $hourly['time']->format('H:i') }}</span>
                                                        <span class="w-1/4 text-center">
                                                            {{-- Refined icon logic - needs component/function --}}
                                                            @php
                                                                $h_icon = 'heroicon-s-cloud';
                                                                $h_color = 'text-slate-400';
                                                                if ($hourly['precip_probability'] ?? 0 > 40) {
                                                                    $h_icon = 'heroicon-s-cloud-arrow-down';
                                                                    $h_color = 'text-blue-500';
                                                                } elseif ($hourly['is_day'] ?? true) {
                                                                    $h_icon = 'heroicon-s-sun';
                                                                    $h_color = 'text-yellow-500';
                                                                } else {
                                                                    $h_icon = 'heroicon-s-moon';
                                                                    $h_color = 'text-slate-400';
                                                                }
                                                                if (($hourly['cloud_cover'] ?? 0) > 60) {
                                                                    $h_icon = 'heroicon-s-cloud';
                                                                    $h_color = 'text-slate-400';
                                                                } // Override sun/moon if cloudy
                                                            @endphp
                                                            <x-dynamic-component :component="$h_icon"
                                                                class="h-4 w-4 inline-block {{ $h_color }}" />
                                                        </span>
                                                        <span
                                                            class="w-1/4 text-center text-slate-700 dark:text-slate-200 font-medium">{{ round($hourly['temp_c']) }}°</span>
                                                        <span
                                                            class="w-1/4 text-right text-xs text-sky-600 dark:text-sky-400 opacity-80">{{ $hourly['precip_probability'] ?? 0 }}%</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                    {{-- Initial Prompt State --}}
                                @elseif(!$locationSet)
                                    <div class="text-center py-6 text-sm text-slate-500 dark:text-slate-400"
                                        x-data="{ requesting: false, attempted: false }" x-init="if (!attempted) {
                                            attempted = true;
                                            console.log('[x-init] Weather Check'); // Simple log
                                            if (!{{ $locationSet ? 'true' : 'false' }}) {
                                                console.log('[x-init] Requesting browser location...');
                                                requesting = true;
                                                if ('geolocation' in navigator) {
                                                    navigator.geolocation.getCurrentPosition(
                                                        (position) => {
                                                            console.log('[x-init] Geolocation success');
                                                            $wire.call('fetchWeatherForBrowserLocation', position.coords.latitude, position.coords.longitude)
                                                                .finally(() => { requesting = false;
                                                                    console.log('[x-init] Livewire fetch call finished.'); });
                                                        },
                                                        (error) => {
                                                            console.error('[x-init] Geolocation error:', error.message);
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
                                                            $wire.call('handleLocationError', message)
                                                                .finally(() => { requesting = false;
                                                                    console.log('[x-init] Livewire error handle call finished.'); });
                                                        }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 } // Allow slightly older position (10 mins)
                                                    );
                                                } else {
                                                    console.log('[x-init] Geolocation not supported');
                                                    $wire.call('handleLocationError', 'Geolocation not supported by browser.')
                                                        .finally(() => { requesting = false; });
                                                }
                                            } else {
                                                console.log('[x-init] Saved location is set, doing nothing.');
                                                // If location IS set, we don't need to show the 'requesting' state from Alpine
                                                requesting = false; // Ensure requesting is false if location is set
                                            }
                                        } else {
                                            console.log('[x-init] Already attempted.');
                                        }">
                                        {{-- Keep Alpine trigger as before --}}
                                        {{-- Loading Indicator managed by Alpine --}}
                                        <div x-show="requesting" x-transition>
                                            <x-loading-spinner class="mx-auto text-indigo-600" />
                                            <p class="mt-2">Requesting location...</p>
                                        </div>
                                        {{-- Initial Prompt (Only shown if !requesting and no error/data) --}}
                                        <div x-show="!requesting" x-cloak>
                                            <x-heroicon-o-map-pin class="mx-auto h-8 w-8 text-slate-400 mb-2" />
                                            Allow location access or set a default location in your <a
                                                href="{{ route('profile.edit') }}"
                                                class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">profile</a>
                                            for local weather.
                                        </div>
                                    </div>
                                    {{-- Fallback State (Failed fetch for saved location) --}}
                                @else
                                    <div
                                        class="text-center py-6 text-sm text-yellow-700 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg border border-yellow-200 dark:border-yellow-800/50">
                                        <x-heroicon-o-exclamation-triangle
                                            class="mx-auto h-6 w-6 text-yellow-400 mb-1" />
                                        Weather forecast unavailable. Please check profile settings or try again later.
                                    </div>
                                @endif
                            </div> {{-- End wire:loading.remove wrapper --}}
                        </div>
                    </div>

                    {{-- Quick Links Card (Combined Account & Pantry) --}}
                    <div
                        class="bg-white dark:bg-slate-800 overflow-hidden shadow-lg rounded-lg border border-slate-200 dark:border-slate-700/80">
                        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Quick Access</h3>
                        </div>
                        <div class="p-5 divide-y divide-slate-100 dark:divide-slate-700">
                            {{-- Account Link --}}
                            <a href="{{ route('profile.edit') }}"
                                class="py-3 flex justify-between items-center text-sm text-slate-600 dark:text-slate-300 hover:text-indigo-700 dark:hover:text-indigo-400 group transition-colors">
                                <span class="inline-flex items-center">
                                    <x-heroicon-o-user-circle
                                        class="h-5 w-5 mr-2 text-slate-400 dark:text-slate-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-400 transition-colors" />
                                    My Account & Profile
                                </span>
                                <x-heroicon-s-chevron-right
                                    class="h-4 w-4 text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" />
                            </a>
                            {{-- Pantry Link --}}
                            <a href="{{ route('pantry.index') ?? '#' }}"
                                class="py-3 flex justify-between items-center text-sm text-slate-600 dark:text-slate-300 hover:text-indigo-700 dark:hover:text-indigo-400 group transition-colors">
                                <span class="inline-flex items-center">
                                    <x-heroicon-o-archive-box
                                        class="h-5 w-5 mr-2 text-slate-400 dark:text-slate-500 group-hover:text-indigo-500 dark:group-hover:text-indigo-400 transition-colors" />
                                    Manage Nutrition Pantry
                                </span>
                                <x-heroicon-s-chevron-right
                                    class="h-4 w-4 text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" />
                            </a>
                            {{-- Maybe Add Other Links (Settings, Help?) here --}}
                        </div>
                    </div>

                </div> {{-- End Right Column --}}

            </div> {{-- End Grid --}}
        </div>
    </div>
</div>
