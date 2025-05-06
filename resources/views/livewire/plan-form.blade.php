<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create Plan: Details for Route') }} "{{ $routeName }}"
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8"> {{-- Use wider container --}}

            {{-- Display potential errors --}}
            @if (session()->has('error'))
                <div
                    class="mb-4 p-4 bg-red-100 dark:bg-red-900 dark:text-red-200 border border-red-300 dark:border-red-700 rounded-lg shadow-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif
            @if (session()->has('message'))
                <div
                    class="mb-4 p-4 bg-blue-100 dark:bg-blue-900 dark:text-blue-200 border border-blue-300 dark:border-blue-700 rounded-lg shadow-sm text-blue-700">
                    {{ session('message') }}
                </div>
            @endif

            {{-- Use Grid for Layout: Map/Info on Left, Form on Right (on medium screens up) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                {{-- Left Column: Route Info & Map --}}
                <div class="md:col-span-1 space-y-6">
                    {{-- Selected Route Information Card --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 mb-3">Selected
                                Route</h3>
                            <div class="flex items-center mb-2">
                                <x-heroicon-o-map-pin
                                    class="h-5 w-5 text-indigo-600 dark:text-indigo-400 mr-2 flex-shrink-0" />
                                <span
                                    class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $routeName }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 pl-7 mb-1">Route ID: {{ $routeId }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 pl-7">
                                {{ number_format($routeDistanceKm, 1) }} km / {{ number_format($routeElevationM) }} m
                                elev.
                            </p>
                        </div>
                    </div>

                    {{-- Map Preview Card --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                        <div class="p-1"> {{-- Minimal padding around map --}}
                            {{-- Map Container --}}
                            <div id="route-map-preview" wire:ignore class="w-full h-64 md:h-80 rounded-md"
                                style="background-color: #e5e7eb;" data-polyline="{{ $this->fetchedPolyline ?? '' }}">
                                {{-- <-- MODIFIED HERE --}}
                                {{-- Placeholder background --}}
                            </div>
                        </div>
                        <p class="p-2 text-center text-xs text-gray-400 dark:text-gray-500">Route Preview</p>
                    </div>

                </div>

                {{-- Right Column: Form --}}
                <div class="md:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                        <form wire:submit.prevent="generatePlan">
                            {{-- Card Body with Padding and Spacing --}}
                            <div class="p-6 space-y-6">

                                {{-- Planned Start Date & Time --}}
                                <div>
                                    <x-input-label for="planned_start_datetime" :value="__('Planned Start Date & Time')" />
                                    <x-text-input id="planned_start_datetime" type="datetime-local"
                                        class="mt-1 block w-full dark:[color-scheme:dark]"
                                        wire:model.defer="planned_start_datetime" required />
                                    <x-input-error :messages="$errors->get('planned_start_datetime')" class="mt-2" />
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Select the date and time
                                        you plan to start the activity.</p>
                                </div>

                                {{-- Planned Intensity --}}
                                <div>
                                    <x-input-label for="planned_intensity" :value="__('Planned Intensity')" />
                                    <select id="planned_intensity" wire:model.defer="planned_intensity" required
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                        <option value="" disabled>-- Select Intensity --</option>
                                        @foreach ($intensityOptions as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('planned_intensity')" class="mt-2" />
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Select the overall effort
                                        level you plan for this activity.</p>
                                </div>

                            </div> {{-- End Card Body --}}

                            {{-- Card Footer for Actions --}}
                            <div
                                class="flex items-center justify-end gap-4 px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                                {{-- Loading indicator text --}}
                                <span wire:loading wire:target="generatePlan"
                                    class="text-sm text-gray-500 dark:text-gray-400">
                                    <svg class="animate-spin inline-block h-4 w-4 mr-1 text-gray-400"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Generating Plan...
                                </span>

                                {{-- Generate Button --}}
                                <button type="submit" wire:loading.attr="disabled"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 transition ease-in-out duration-150">
                                    <span wire:loading.remove wire:target="generatePlan">
                                        {{ __('Generate Nutrition Plan') }}
                                    </span>
                                    <span wire:loading wire:target="generatePlan">Generating...</span>
                                </button>
                            </div> {{-- End Card Footer --}}
                        </form>
                    </div> {{-- End Form Card --}}
                </div> {{-- End Right Column --}}

            </div> {{-- End Grid --}}
        </div>
    </div>

    {{-- Push Leaflet CSS and JS Assets --}}
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <style>
            #route-map-preview:empty::before {
                content: 'Loading Map...';
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100%;
                color: #6b7280;
                /* gray-500 */
            }

            .leaflet-container {
                background-color: #fff;
            }

            /* Ensure map bg is white */
            .leaflet-control-attribution a {
                color: #4f46e5 !important;
            }

            /* Optional: Style attribution link */
            .leaflet-control-zoom {
                display: none;
            }

            /* Hide zoom controls if desired on preview */
        </style>
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="https://unpkg.com/@mapbox/polyline@1.1.1/src/polyline.js"></script>

        <script>
            (function() {
                document.addEventListener('DOMContentLoaded', function() {
                    const mapElementId = 'route-map-preview';
                    const mapElement = document.getElementById(mapElementId);

                    if (!mapElement) {
                        console.error(`Map element #${mapElementId} not found!`);
                        return;
                    }

                    // *** Get polyline from data attribute ***
                    const encodedPolyline = mapElement.dataset.polyline ||
                    ''; // Default to empty string if attribute not found or empty

                    // Check if encodedPolyline is actually empty
                    if (!encodedPolyline) {
                        console.warn("No route polyline data available to display on the map.");
                        mapElement.innerHTML =
                            '<div class="flex items-center justify-center h-full text-sm text-gray-500 dark:text-gray-400">Route map data unavailable.</div>';
                        return; // Stop map initialization if no polyline
                    }

                    try {
                        console.log("Initializing Leaflet map for route preview...");
                        const map = L.map(mapElement, {
                            scrollWheelZoom: false,
                            attributionControl: false,
                        }).setView([0, 0], 2);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 18,
                        }).addTo(map);

                        console.log("Decoding polyline:", encodedPolyline); // Log the polyline being used
                        const coordinates = polyline.decode(encodedPolyline);

                        if (coordinates && coordinates.length > 0) {
                            const routeLine = L.polyline(coordinates, {
                                color: '#3b82f6', // Blue-500
                                weight: 3,
                                opacity: 0.8
                            }).addTo(map);

                            map.fitBounds(routeLine.getBounds().pad(0.1));
                            console.log("Route polyline added to map and bounds fitted.");
                        } else {
                            console.warn("Decoded polyline has no coordinates.");
                            mapElement.innerHTML =
                                '<div class="flex items-center justify-center h-full text-sm text-gray-500 dark:text-gray-400">Could not display route map.</div>';
                        }

                    } catch (error) {
                        console.error("Error initializing Leaflet map or drawing polyline:", error);
                        mapElement.innerHTML =
                            '<div class="flex items-center justify-center h-full text-sm text-red-500">Error loading map.</div>';
                    }
                });
            })();
        </script>
    @endpush
</div>
