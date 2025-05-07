<div x-data="{
    activeTab: @js($initialActiveTab), {{-- Set by Livewire: 'strava' or 'gpx' --}}
    initStravaMapsIfNeeded() {
        if (this.activeTab === 'strava' && {{ $stravaConnected ? 'true' : 'false' }}) {
            this.$nextTick(() => {
                console.log('Alpine x-data: initStravaMapsIfNeeded called for Strava tab.');
                if (typeof window.initializeAllMapsInStravaTabGlobal === 'function') {
                    window.initializeAllMapsInStravaTabGlobal();
                } else {
                    console.error('Alpine x-data: initializeAllMapsInStravaTabGlobal function not found on window for initStravaMapsIfNeeded.');
                }
            });
        }
    },
    handleTabChange(tabName) {
        this.activeTab = tabName;
        this.initStravaMapsIfNeeded(); // Will only init if new tab is 'strava' and connected
        console.log('Alpine x-data: Tab changed to', this.activeTab);
    }
 }"
 x-init="
    console.log('Alpine x-init: Component initializing. Initial activeTab from Livewire is {{ $initialActiveTab }}. Current Alpine activeTab:', activeTab);
    initStravaMapsIfNeeded(); // Attempt initial map load based on initialActiveTab
 "
 x-route-map>
<x-slot name="header">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create Plan: Select Route Source') }}
        </h2>
    </div>
</x-slot>

<div class="py-8 sm:py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Error Message for Strava Connection or general issues --}}
        @if ($errorMessage)
            <div class="mb-6 p-4 bg-{{ Str::contains($errorMessage, 'connect Strava') ? 'yellow' : 'red' }}-50 dark:bg-{{ Str::contains($errorMessage, 'connect Strava') ? 'yellow' : 'red' }}-900/50 text-{{ Str::contains($errorMessage, 'connect Strava') ? 'yellow' : 'red' }}-700 dark:text-{{ Str::contains($errorMessage, 'connect Strava') ? 'yellow' : 'red' }}-200 border-l-4 border-{{ Str::contains($errorMessage, 'connect Strava') ? 'yellow' : 'red' }}-400 dark:border-{{ Str::contains($errorMessage, 'connect Strava') ? 'yellow' : 'red' }}-500 rounded-md shadow-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        @if(Str::contains($errorMessage, 'connect Strava'))
                            <x-heroicon-s-information-circle class="h-5 w-5 text-yellow-400 dark:text-yellow-500" />
                        @else
                            <x-heroicon-s-x-circle class="h-5 w-5 text-red-400 dark:text-red-500" />
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">{{ $errorMessage }}</p>
                        @if (Str::contains($errorMessage, 'connect Strava'))
                            <p class="mt-1 text-sm"><a href="{{ route('profile.edit') }}" class="font-medium underline hover:text-yellow-600 dark:hover:text-yellow-100">Go to Profile to Connect Strava</a></p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Session Error (e.g., route not found on confirmSelection in RouteSelector.php) --}}
        @if (session()->has('error'))
             <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/50 text-red-700 dark:text-red-200 border-l-4 border-red-500 dark:border-red-600 rounded-md shadow-md">
                 <div class="flex">
                    <div class="flex-shrink-0"><x-heroicon-s-exclamation-triangle class="h-5 w-5 text-red-400 dark:text-red-500" /></div>
                    <div class="ml-3"><p class="text-sm font-medium">{{ session('error') }}</p></div>
                </div>
            </div>
        @endif

        {{-- Tab Navigation --}}
        <div class="mb-6 sm:mb-8">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-6 sm:space-x-8" aria-label="Tabs">
                    @if ($stravaConnected) {{-- Only show Strava tab if connected --}}
                        <button @click="handleTabChange('strava')"
                                :class="{ 'border-indigo-500 dark:border-indigo-400 text-indigo-600 dark:text-indigo-300': activeTab === 'strava', 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-500': activeTab !== 'strava' }"
                                class="whitespace-nowrap py-3 sm:py-4 px-1 border-b-2 font-medium text-sm transition-colors focus:outline-none">
                            <x-heroicon-o-list-bullet class="h-5 w-5 mr-1.5 inline-block align-text-bottom"/> From Strava
                        </button>
                    @endif
                    <button @click="handleTabChange('gpx')" {{-- GPX tab always visible --}}
                            :class="{ 'border-indigo-500 dark:border-indigo-400 text-indigo-600 dark:text-indigo-300': activeTab === 'gpx', 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-500': activeTab !== 'gpx' }"
                            class="whitespace-nowrap py-3 sm:py-4 px-1 border-b-2 font-medium text-sm transition-colors focus:outline-none">
                        <x-heroicon-o-document-arrow-up class="h-5 w-5 mr-1.5 inline-block align-text-bottom"/> Upload GPX File
                    </button>
                </nav>
            </div>
        </div>

        {{-- Tab Content --}}
        <div>
            {{-- Strava Routes Tab Content --}}
            @if ($stravaConnected)
                <div x-show="activeTab === 'strava'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" style="display: none;">
                    {{-- Loading Indicator for the "Refresh Routes" button action specifically --}}
                    <div wire:loading.block wire:target="loadRoutes" class="mb-6 p-6 bg-blue-50 dark:bg-slate-800 text-blue-700 dark:text-blue-300 border-l-4 border-blue-500 dark:border-blue-400 rounded-md shadow-md text-center">
                        <div class="flex items-center justify-center"><svg class="animate-spin h-6 w-6 mr-3 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span class="font-medium text-lg">Refreshing Strava routes...</span></div>
                    </div>

                    <div wire:loading.remove wire:target="loadRoutes">
                        @if($isLoading) {{-- Handles initial loading state when component mounts and stravaConnected is true --}}
                            <div class="mb-6 p-6 bg-blue-50 dark:bg-slate-800 text-blue-700 dark:text-blue-300 border-l-4 border-blue-500 dark:border-blue-400 rounded-md shadow-md text-center">
                                 <div class="flex items-center justify-center"><svg class="animate-spin h-6 w-6 mr-3 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span class="font-medium text-lg">Loading your Strava routes...</span></div>
                            </div>
                        @else {{-- Show table if not loading --}}
                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                        <div><h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">Your Strava Routes</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose from your connected Strava account.</p></div>
                                        <div class="flex-shrink-0"><button wire:click="loadRoutes" wire:loading.attr="disabled" wire:target="loadRoutes" title="Refresh Strava routes" class="inline-flex items-center justify-center px-4 py-2 bg-slate-600 dark:bg-slate-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-slate-700 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 transition ease-in-out duration-150"><svg wire:loading wire:target="loadRoutes" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><x-heroicon-o-arrow-path class="h-4 w-4 mr-1.5 inline-block wire:loading.remove wire:target='loadRoutes'"/>Refresh</button></div>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"><thead class="bg-gray-50 dark:bg-gray-700/60"><tr><th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Route Name</th><th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Dist (km)</th><th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Elev (m)</th><th scope="col" class="px-6 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider w-32 sm:w-40">Preview</th><th scope="col" class="px-6 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Action</th></tr></thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="route-table-body">
                                            @forelse ($routes as $index => $route)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150 group">
                                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $route['name'] }}</div><div class="text-xs text-gray-500 dark:text-gray-400">Strava ID: {{ $route['id_str'] ?? $route['id'] }}</div></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format(($route['distance'] ?? 0) / 1000, 1) }}</td>{{-- Convert meters to KM for display --}}
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700 dark:text-gray-300">{{ number_format($route['elevation_gain'] ?? 0, 0) }}</td>
                                                    <td class="px-6 py-4 text-sm text-gray-500 align-middle">
                                                        <div id="map-route-{{ $route['id'] }}" data-polyline="{{ $route['map']['summary_polyline'] ?? $route['summary_polyline'] ?? '' }}" class="w-full h-20 sm:h-24 rounded-md border border-gray-300 dark:border-gray-600 map-container overflow-hidden group-hover:ring-2 group-hover:ring-indigo-500 group-hover:shadow-lg transition-all duration-150 relative" style="aspect-ratio: 16 / 9;" title="Route preview for {{ $route['name'] }}">
                                                            <div class="absolute inset-0 flex items-center justify-center map-placeholder-content text-xs text-gray-400 dark:text-gray-500 px-2 text-center">
                                                                @if(empty($route['map']['summary_polyline'] ?? $route['summary_polyline'] ?? '')) No map preview @else Loading preview... @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium align-middle"><button wire:click="confirmSelection('{{ $route['id'] }}')" wire:loading.attr="disabled" wire:target="confirmSelection('{{ $route['id'] }}')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 disabled:opacity-50">Select <svg wire:loading wire:target="confirmSelection('{{ $route['id'] }}')" class="animate-spin ml-1.5 -mr-0.5 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></button></td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="px-6 py-16 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center"><div class="flex flex-col items-center"><x-heroicon-o-map class="h-16 w-16 text-gray-400 dark:text-gray-500 mb-4"/><p class="text-lg font-medium text-gray-700 dark:text-gray-300">No Strava Routes Found</p><p class="mt-2 max-w-md">We couldn't find any routes in your Strava account. Ensure you have routes created there, or try the "Refresh" button. If your Strava account isn't connected, please check your <a href="{{ route('profile.edit') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">profile settings</a>.</p></div></td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if ($routes && (is_array($routes) ? count($routes) : $routes->count()) > 0)
                                    <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/60 rounded-b-lg">Displaying {{ is_array($routes) ? count($routes) : $routes->count() }} {{ Str::plural('route', is_array($routes) ? count($routes) : $routes->count()) }} from Strava.</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- GPX Upload Tab Content --}}
            <div x-show="activeTab === 'gpx'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" style="display: none;">
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6"><h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-gray-100">Upload GPX File</h3><p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Select a GPX route file. Calculations will be based on the information within this file.</p></div>
                    <div class="border-t border-gray-200 dark:border-gray-700">
                       <form wire:submit.prevent="processGpxFile" class="space-y-6 p-6">
                            <div>
                                <label for="gpxFileInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300 sr-only">GPX file</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-md hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
                                    <div class="space-y-1 text-center"><x-heroicon-o-document-arrow-up class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"/><div class="flex text-sm text-gray-600 dark:text-gray-400 justify-center"><label for="gpxFileInput" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-800 focus-within:ring-indigo-500 px-1"><span>Upload a file</span><input id="gpxFileInput" wire:model.live="gpxFile" name="gpxFile" type="file" class="sr-only" accept=".gpx"></label><p class="pl-1 hidden sm:inline">or drag and drop</p></div><p class="text-xs text-gray-500 dark:text-gray-500">GPX files up to 5MB.</p></div>
                                </div>
                                <div wire:loading wire:target="gpxFile" class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 animate-pulse">Validating file...</div>
                                @if ($gpxFileName && !$errors->has('gpxFile') && !$gpxProcessingError)
                                    <div class="mt-3 text-sm text-green-600 dark:text-green-400"><x-heroicon-s-check-circle class="h-5 w-5 mr-1 inline-block align-text-bottom text-green-500"/><span class="font-semibold">Selected:</span> {{ $gpxFileName }}</div>
                                @endif
                                <x-input-error :messages="$errors->get('gpxFile')" class="mt-2" />
                            </div>
                            @if ($gpxProcessingError)
                                <div class="mt-3 p-3 bg-red-50 dark:bg-red-900/40 border-l-4 border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 rounded-md"><div class="flex"><div class="flex-shrink-0"><x-heroicon-s-x-circle class="h-5 w-5 text-red-400 dark:text-red-500"/></div><div class="ml-3"><p class="text-sm font-medium">{{ $gpxProcessingError }}</p></div></div></div>
                            @endif
                            <div class="pt-2">
                                <button type="submit" wire:loading.attr="disabled" wire:target="processGpxFile, gpxFile" class="w-full inline-flex justify-center items-center py-2.5 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-green-500 disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="processGpxFile"><x-heroicon-o-arrow-up-on-square class="h-5 w-5 mr-2 inline-block -ml-1"/>Use This GPX File</span>
                                    <span wire:loading wire:target="processGpxFile"><svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Processing GPX...</span>
                                </button>
                            </div>
                       </form>
                    </div>
                </div>
            </div>
        </div> {{-- End Tab Content div --}}
    </div> {{-- End max-w-7xl --}}
</div> {{-- End py-8 --}}

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .map-container { background-color: #f3f4f6; } /* gray-100 */
        .dark .map-container { background-color: #374151; } /* gray-700 */
        /* Styles for the inner placeholder div are handled by Tailwind classes directly in the HTML */
        .leaflet-container { background-color: #ffffff !important; }
        .leaflet-control-attribution { font-size: 0.625rem !important; padding: 1px 4px !important; background: rgba(255, 255, 255, 0.75) !important; backdrop-filter: blur(2px); border-radius: 3px; }
        .dark .leaflet-control-attribution { background: rgba(31, 41, 55, 0.75) !important; color: #d1d5db; }
        .leaflet-control-attribution a { color: #4f46e5 !important; }
        .dark .leaflet-control-attribution a { color: #818cf8 !important; }
        .leaflet-control-zoom { display: none !important; }
    </style>
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/@mapbox/polyline@1.1.1/src/polyline.js"></script>
    <script>
        (function() {
            window.routeMaps = window.routeMaps || {};

            window.initializeRouteMap = function(mapElementId, encodedPolyline) {
                // console.log(`INITIALIZEROUTEMAP for ${mapElementId} - START. Polyline present: ${!!encodedPolyline}`);
                if (window.routeMaps[mapElementId]) { console.log(`Map ${mapElementId} ALREADY IN window.routeMaps. Returning.`); return window.routeMaps[mapElementId]; }
                const mapElement = document.getElementById(mapElementId);
                if (!mapElement) { console.error(`Map element #${mapElementId} NOT FOUND in DOM!`); return null; }
                // console.log(`Map element ${mapElementId} FOUND.`);
                const placeholder = mapElement.querySelector('.map-placeholder-content');
                if (!encodedPolyline || encodedPolyline.length < 5) {
                    // console.warn(`No or invalid polyline for ${mapElementId}. Polyline: '${encodedPolyline}'`);
                    if(placeholder && !placeholder.textContent.includes("No map preview")) placeholder.textContent = 'Map preview unavailable';
                    return null;
                }
                if (placeholder) { /* console.log(`Removing placeholder for ${mapElementId}`); */ placeholder.remove(); }
                else { /* console.log(`No placeholder div for ${mapElementId}, clearing innerHTML.`); */ mapElement.innerHTML = '';}
                try {
                    // console.log(`Attempting L.map() for ${mapElementId}`);
                    const map = L.map(mapElement, { zoomControl: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, dragging: false, attributionControl: true }).setView([20, 0], 1);
                    // console.log(`L.map() SUCCESS for ${mapElementId}`);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, minZoom: 1 }).addTo(map);
                    // console.log(`Tile layer ADDED for ${mapElementId}`);
                    const coordinates = polyline.decode(encodedPolyline);
                    // console.log(`Polyline DECODED for ${mapElementId}. Count: ${coordinates ? coordinates.length : 'null'}`);
                    if (coordinates && coordinates.length > 0) {
                        const routeLine = L.polyline(coordinates, { color: '#4338ca', weight: 3, opacity: 0.75 }).addTo(map);
                        // console.log(`Polyline DRAWN for ${mapElementId}`);
                        requestAnimationFrame(() => { try { map.invalidateSize(); map.fitBounds(routeLine.getBounds(), { padding: [10, 10], maxZoom: 15 }); /* console.log(`Bounds FITTED for ${mapElementId}`); */ } catch(fitError) { /* console.error(`Fitbounds err ${mapElementId}:`,fitError); */ if (coordinates[0]&&coordinates[0].length===2) map.setView([coordinates[0][0],coordinates[0][1]],13); } });
                    } else {
                        //  console.warn(`No coords after decode for ${mapElementId}.`);
                         mapElement.innerHTML = '<div class="absolute inset-0 flex items-center justify-center map-placeholder-content text-xs text-gray-400 dark:text-gray-500 px-2 text-center">Invalid map data</div>';
                         if(map && typeof map.remove === 'function') map.remove(); return null;
                    }
                    window.routeMaps[mapElementId] = map; /* console.log(`Map ${mapElementId} successfully INITIALIZED.`); */ return map;
                } catch (E) {
                    console.error(`CRITICAL ERROR in initializeRouteMap for ${mapElementId}:`, E);
                    mapElement.innerHTML = '<div class="absolute inset-0 flex items-center justify-center map-placeholder-content text-xs text-red-500 dark:text-red-400 px-2 text-center">Map init error</div>';
                    if (window.routeMaps[mapElementId]) { try{window.routeMaps[mapElementId].remove();}catch(e){} delete window.routeMaps[mapElementId];}
                    return null;
                }
            };

            window.initializeAllMapsInStravaTabGlobal = function() {
                console.log("JS: initializeAllMapsInStravaTabGlobal CALLED");
                const mapContainers = document.querySelectorAll('#route-table-body .map-container[id^="map-route-"]');
                // console.log(`JS: Found ${mapContainers.length} Strava map containers.`);
                mapContainers.forEach(container => {
                    if (!window.routeMaps[container.id]) {
                       if (typeof window.initializeRouteMap === 'function') window.initializeRouteMap(container.id, container.dataset.polyline);
                    }
                });
            };

            window.cleanupRemovedMapsFromStravaTabGlobal = function() { /* ... (same cleanup logic as before) ... */ };

            document.addEventListener('routes-loaded', () => {
                console.log("JS EVENT: Livewire 'routes-loaded' received.");
                const alpineElement = document.querySelector('[x-data*="activeTab"]'); // More robust selector
                if (alpineElement && typeof alpineElement.__x !== 'undefined' && alpineElement.__x.$data.activeTab === 'strava') {
                    console.log("JS 'routes-loaded': Strava tab IS active. Cleaning & Initializing maps.");
                    if(typeof window.cleanupRemovedMapsFromStravaTabGlobal === 'function') window.cleanupRemovedMapsFromStravaTabGlobal();
                    if(typeof window.initializeAllMapsInStravaTabGlobal === 'function') window.initializeAllMapsInStravaTabGlobal();
                } else {
                    console.log("JS 'routes-loaded': Strava tab NOT active or Alpine instance not found. Skipping map re-init.");
                }
            });

            // No separate DOMContentLoaded needed for map init as Alpine's x-init should handle it now.
            console.log("Route Selector JS loaded.");
        })();
    </script>
@endpush
</div>
