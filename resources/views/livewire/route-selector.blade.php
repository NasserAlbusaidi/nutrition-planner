<div x-data x-route-map>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Plan: Select Strava Route') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Loading Indicator --}}
            <div wire:loading wire:target="loadRoutes"
                class="mb-4 p-4 bg-blue-100 text-blue-700 border border-blue-300 rounded text-center">
                <svg class="animate-spin inline-block h-5 w-5 mr-2 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                Loading Strava routes...
            </div>

            {{-- Error Message --}}
            @if ($errorMessage)
                <div class="mb-4 p-4 bg-red-100 text-red-700 border border-red-300 rounded">
                    {{ $errorMessage }}
                    @if (Str::contains($errorMessage, 'connect'))
                        <a href="{{ route('profile.edit') }}" class="underline ml-2 font-semibold">Go to Profile</a>
                    @endif
                </div>
            @endif

            {{-- Session Error for confirmSelection --}}
            @if (session()->has('error'))
                 <div class="mb-4 p-4 bg-red-100 text-red-700 border border-red-300 rounded">
                    {{ session('error') }}
                </div>
            @endif


            {{-- Main Content Area (Single Column for Table) --}}
            {{-- Show table container only when not loading initially --}}
            <div wire:loading.remove wire:target="loadRoutes">

                {{-- Routes Table --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex justify-end mb-4"> {{-- Refresh Button --}}
                             <button wire:click="loadRoutes" wire:loading.attr="disabled" wire:target="loadRoutes"
                                class="inline-flex items-center px-3 py-1.5 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-50 transition ease-in-out duration-150">
                                <svg wire:loading wire:target="loadRoutes"
                                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Refresh Routes
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Route Name</th>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Dist (km)</th>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Elev (m)</th>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                                            {{-- Width constraint for map --}}
                                            Map Preview</th>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="route-table-body">
                                    {{-- Add id to tbody for easier JS targeting if needed --}}
                                    @if (!$errorMessage && !$isLoading)
                                        @forelse ($routes as $index => $route)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $route['name'] }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $route['distance'] }}</td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $route['elevation_gain'] }}</td>
                                                <td class="px-4 py-4 text-sm text-gray-500 align-top">
                                                    {{-- Map Container - Unique ID and Data Attribute --}}
                                                    <div id="map-route-{{ $route['id'] }}"
                                                         data-polyline="{{ $route['summary_polyline'] ?? '' }}"
                                                         class="w-full h-24 rounded border map-container" {{-- Added map-container class --}}
                                                         style="background-color: #e5e7eb;">
                                                        {{-- Placeholder text/styling --}}
                                                         @if(empty($route['summary_polyline']))
                                                            <span class="text-xs text-gray-400 flex items-center justify-center h-full">No map data</span>
                                                         @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium align-middle">
                                                     {{-- Selection Button --}}
                                                    <button wire:click="confirmSelection('{{ $route['id'] }}')"
                                                    wire:loading.attr="disabled" wire:target="confirmSelection('{{ $route['id'] }}')" {{-- Also update target --}}
                                                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50">
                                                    Select
                                                    <svg wire:loading wire:target="confirmSelection('{{ $route['id'] }}')" class="animate-spin ml-1 -mr-0.5 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                         <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                         <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                     </svg>
                                                </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" {{-- Adjusted colspan --}}
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                                    No Strava routes found or unable to fetch.
                                                </td>
                                            </tr>
                                        @endforelse
                                    @elseif($isLoading) {{-- Show loading row only during initial load --}}
                                        <tr>
                                             <td colspan="5" {{-- Adjusted colspan --}}
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                                 Loading routes list... {{-- Different message from the top indicator --}}
                                             </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div> {{-- End wire:loading.remove --}}

        </div>
    </div>
</div>

{{-- Add Leaflet CSS and JS, plus Polyline decoder --}}
@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .map-container:empty::before,
        .map-container:not(:has(.leaflet-container))::before { /* Show placeholder only if map not initialized or empty */
            content: 'Loading map...';
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            font-size: 0.75rem; /* text-xs */
            color: #9ca3af; /* gray-400 */
            text-align: center;
        }
        .leaflet-container {
            background-color: #fff; /* Ensure map background is white */
            cursor: default !important; /* Override default grab cursor if not needed */
        }
        .leaflet-control-attribution {
            font-size: 0.6rem !important; /* Make attribution smaller */
            padding: 0 2px !important;
        }
        .leaflet-control-zoom {
             display: none; /* Hide zoom controls on small maps */
        }
    </style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/@mapbox/polyline@1.1.1/src/polyline.js"></script>

<script>
    // Use a self-executing function to scope variables
    (function() {
        // Keep track of initialized maps to avoid duplicates and for cleanup
        window.routeMaps = window.routeMaps || {}; // Use global scope or a dedicated object

        function initializeRouteMap(mapElementId, encodedPolyline) {
            // Check if map already initialized for this ID
            if (window.routeMaps[mapElementId]) {
                console.log(`Map ${mapElementId} already initialized.`);
                // Optionally update polyline if needed, though usually re-rendering handles this
                return window.routeMaps[mapElementId];
            }

            const mapElement = document.getElementById(mapElementId);
            if (!mapElement) {
                console.error(`Map element #${mapElementId} not found!`);
                return null;
            }

            // Check if polyline exists
            if (!encodedPolyline) {
                 console.warn(`No polyline data for map ${mapElementId}. Skipping map drawing.`);
                 mapElement.innerHTML = '<span class="text-xs text-gray-400 flex items-center justify-center h-full">No map data</span>'; // Clear potential loader
                 return null; // Don't initialize map if no polyline
            }

            console.log(`Initializing Leaflet map for ${mapElementId}...`);
            try {
                // Initialize map - disable zoom controls, maybe dragging too?
                const map = L.map(mapElement, {
                    zoomControl: false, // Disable zoom control +/- buttons
                    scrollWheelZoom: false, // Disable zoom on scroll
                    doubleClickZoom: false, // Disable zoom on double click
                    touchZoom: false, // Disable pinch zoom on touch devices
                    dragging: false, // Disable map dragging
                }).setView([20, 0], 1); // Default view, will be overridden by fitBounds

                // Add tile layer (consider a simpler/lighter one if needed)
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 20,
                    attribution: '© OSM', // Shorter attribution
                    // Consider adding errorTileUrl if tiles fail to load
                    // errorTileUrl: 'path/to/your/error/tile.png'
                }).addTo(map);

                // Decode and draw polyline
                const coordinates = polyline.decode(encodedPolyline);
                if (coordinates && coordinates.length > 0) {
                    const routeLine = L.polyline(coordinates, {
                         color: '#3b82f6', // indigo-500
                         weight: 3,
                         opacity: 0.8
                    }).addTo(map);

                    // Fit map bounds to the polyline
                    // Use requestAnimationFrame to ensure layout is stable before fitting
                    requestAnimationFrame(() => {
                        try {
                            map.invalidateSize(); // Ensure map size is correct
                            map.fitBounds(routeLine.getBounds(), { padding: [10, 10] }); // Add small padding
                        } catch(fitError) {
                             console.error(`Error fitting bounds for ${mapElementId}:`, fitError);
                             // Fallback view if bounds are invalid
                             map.setView([coordinates[0][0], coordinates[0][1]], 13);
                        }
                    });

                } else {
                     console.warn(`Decoded polyline is empty for ${mapElementId}.`);
                     mapElement.innerHTML = '<span class="text-xs text-gray-400 flex items-center justify-center h-full">Map data invalid</span>';
                     map.remove(); // Remove the initialized map object if line fails
                     return null;
                }

                // Store the map instance
                window.routeMaps[mapElementId] = map;
                console.log(`Leaflet map ${mapElementId} initialized successfully.`);
                return map;

            } catch (error) {
                console.error(`Error initializing Leaflet map ${mapElementId}:`, error);
                mapElement.innerHTML = '<span class="text-xs text-red-500 flex items-center justify-center h-full">Map Error</span>'; // Show error in div
                // Clean up if map object was partially created
                 if (window.routeMaps[mapElementId]) {
                    try { window.routeMaps[mapElementId].remove(); } catch (e) {}
                    delete window.routeMaps[mapElementId];
                 }
                return null;
            }
        }

        function initializeAllMaps() {
            console.log("Running initializeAllMaps...");
            const mapContainers = document.querySelectorAll('.map-container[id^="map-route-"]');
            console.log(`Found ${mapContainers.length} potential map containers.`);

            mapContainers.forEach(container => {
                const mapId = container.id;
                const polylineData = container.dataset.polyline;

                // Check if it needs initialization (or re-initialization if required)
                if (!window.routeMaps[mapId]) { // Only initialize if not already done
                     initializeRouteMap(mapId, polylineData);
                } else {
                    console.log(`Skipping already initialized map: ${mapId}`);
                    // You might add logic here to *update* an existing map if the polyline changed
                    // e.g., remove old layer, add new one, fit bounds again.
                    // For now, we assume Livewire replaces the whole container on refresh.
                }
            });
        }

        function cleanupRemovedMaps() {
            console.log("Running map cleanup...");
            const currentMapIds = new Set();
            document.querySelectorAll('.map-container[id^="map-route-"]').forEach(el => currentMapIds.add(el.id));

            for (const mapId in window.routeMaps) {
                if (!currentMapIds.has(mapId)) {
                    console.log(`Removing orphaned map instance: ${mapId}`);
                    try {
                        window.routeMaps[mapId].remove(); // Remove Leaflet map resources
                    } catch (e) {
                        console.error(`Error removing map ${mapId}:`, e);
                    }
                    delete window.routeMaps[mapId]; // Remove from tracking object
                }
            }
        }


        // --- Event Listeners ---

        // Initialize maps on initial load
        document.addEventListener('DOMContentLoaded', () => {
             console.log("DOM Content Loaded - Initializing maps.");
             initializeAllMaps();
        });

        // Initialize maps after Livewire finishes updating the DOM
        // Use the custom event dispatched from the component
        document.addEventListener('routes-loaded', () => {
             console.log("Livewire 'routes-loaded' event received - Re-initializing maps and cleaning up.");
             // Cleanup maps associated with elements that might have been removed by Livewire
             cleanupRemovedMaps();
             // Initialize maps for any new elements added by Livewire
             initializeAllMaps();
        });

         // Optional: Handle browser resize (might not be crucial for small static maps)
        // let resizeTimeout;
        // window.addEventListener('resize', () => {
        //     clearTimeout(resizeTimeout);
        //     resizeTimeout = setTimeout(() => {
        //         console.log("Window resized - invalidating map sizes.");
        //         for (const mapId in window.routeMaps) {
        //             if (window.routeMaps[mapId]) {
        //                  window.routeMaps[mapId].invalidateSize();
        //             }
        //         }
        //     }, 250); // Debounce resize events
        // });


    })(); // End self-executing function
</script>
@endpush
