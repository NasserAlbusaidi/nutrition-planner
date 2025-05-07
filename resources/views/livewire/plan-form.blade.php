<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create Plan: Details for Route') }} "{{ $routeName }}"
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8"> {{-- Use wider container --}}

            {{-- Flash Messages Section (Moved to top for better visibility) --}}
            <div class="mb-6 space-y-4">
                @if (session()->has('error'))
                    <div class="p-4 bg-red-50 dark:bg-red-900/50 text-red-700 dark:text-red-200 border-l-4 border-red-400 dark:border-red-500 rounded-md shadow" role="alert">
                        <div class="flex"><div class="flex-shrink-0"><x-heroicon-s-x-circle class="h-5 w-5"/></div><div class="ml-3"><p class="text-sm font-medium">{{ session('error') }}</p></div></div>
                    </div>
                @endif
                @if (session()->has('message'))
                    <div class="p-4 bg-green-50 dark:bg-green-800/50 text-green-700 dark:text-green-200 border-l-4 border-green-400 dark:border-green-500 rounded-md shadow" role="alert">
                        <div class="flex"><div class="flex-shrink-0"><x-heroicon-s-check-circle class="h-5 w-5"/></div><div class="ml-3"><p class="text-sm font-medium">{{ session('message') }}</p></div></div>
                    </div>
                @endif
                @if (session()->has('plan_warning'))
                    <div class="p-4 bg-yellow-50 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-200 border-l-4 border-yellow-400 dark:border-yellow-500 rounded-md shadow" role="alert">
                        <div class="flex"><div class="flex-shrink-0"><x-heroicon-s-exclamation-triangle class="h-5 w-5"/></div><div class="ml-3"><p class="text-sm font-medium">Note:</p><p class="text-sm">{{ session('plan_warning') }}</p></div></div>
                    </div>
                @endif
                 @if (session()->has('plan_info'))
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/50 text-blue-700 dark:text-blue-200 border-l-4 border-blue-400 dark:border-blue-500 rounded-md shadow" role="alert">
                        <div class="flex"><div class="flex-shrink-0"><x-heroicon-s-information-circle class="h-5 w-5"/></div><div class="ml-3"><p class="text-sm font-medium">{{ session('plan_info') }}</p></div></div>
                    </div>
                @endif
            </div>

            {{-- Use Grid for Layout: Map/Info on Left, Form on Right (on medium screens up) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                {{-- Left Column: Route Info & Map --}}
                <div class="md:col-span-1 space-y-6">
                    {{-- Selected Route Information Card --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 mb-3">Selected Route</h3>
                            <div class="flex items-center mb-2">
                                <x-heroicon-o-map-pin class="h-5 w-5 text-cyan-600 dark:text-cyan-400 mr-2 flex-shrink-0" />
                                <span class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $routeName }}</span>
                            </div>
                             @if($routeSource !== 'manual' && $routeId)
                                <p class="text-xs text-gray-500 dark:text-gray-400 pl-7 mb-1">Source: {{ Str::upper($routeSource) }} | ID: {{ Str::limit($routeId, 15) }} </p>
                             @endif
                            <p class="text-xs text-gray-500 dark:text-gray-400 pl-7">
                                {{ number_format($routeDistanceKm, 1) }} km / {{ number_format($routeElevationM, 0) }} m elev.
                            </p>
                        </div>
                    </div>

                    {{-- Map Preview Card (Conditional based on routeSource and fetchedPolyline) --}}
                    @if ($routeSource === 'strava' && $fetchedPolyline)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                            <div class="p-1">
                                <div id="route-map-preview" wire:ignore class="w-full h-64 md:h-80 rounded-md bg-slate-200 dark:bg-slate-700" data-polyline="{{ $this->fetchedPolyline ?? '' }}">
                                     <div class="absolute inset-0 flex items-center justify-center map-placeholder-content text-xs text-gray-500 dark:text-gray-400">Loading Map Preview...</div>
                                </div>
                            </div>
                            <p class="p-2 text-center text-xs text-gray-400 dark:text-gray-500">Route Preview (Strava)</p>
                        </div>
                    @elseif($routeSource === 'gpx')
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                            <div class="p-4 border-l-4 border-blue-400 dark:border-blue-500">
                                <div class="flex items-center">
                                    <x-heroicon-o-map class="h-6 w-6 mr-3 text-blue-500 dark:text-blue-400"/>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">Map preview for GPX routes not available on this page.</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Right Column: Form --}}
                <div class="md:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                        <form wire:submit.prevent="generatePlan">
                            {{-- Card Body with Padding and Spacing --}}
                            <div class="p-6 space-y-6">

                                {{-- Section 1: Basic Plan Settings --}}
                                <fieldset class="space-y-6">
                                     <legend class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2 mb-3">Core Plan Details</legend>
                                    {{-- Planned Start Date & Time --}}
                                    <div>
                                        <x-input-label for="planned_start_datetime" :value="__('Planned Start Date & Time')" />
                                        <x-text-input id="planned_start_datetime" type="datetime-local"
                                            class="mt-1 block w-full sm:w-2/3 dark:[color-scheme:dark]"
                                            wire:model.defer="planned_start_datetime" required />
                                        <x-input-error :messages="$errors->get('planned_start_datetime')" class="mt-2" />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select the date and time you plan to start the activity.</p>
                                    </div>

                                    {{-- Planned Intensity --}}
                                    <div>
                                        <x-input-label for="planned_intensity" :value="__('Planned Intensity')" />
                                        <select id="planned_intensity" wire:model.defer="planned_intensity" required
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-cyan-500 dark:focus:border-cyan-600 focus:ring-cyan-500 dark:focus:ring-cyan-600 sm:text-sm rounded-md shadow-sm">
                                            <option value="" disabled>-- Select Intensity --</option>
                                            @foreach ($intensityOptions as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('planned_intensity')" class="mt-2" />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select the overall effort level for this activity.</p>
                                    </div>
                                </fieldset>


                                {{-- ****** NEW: PRODUCT SELECTION SECTION ****** --}}
                                <fieldset class="pt-6 border-t border-gray-200 dark:border-gray-700">
                                     <legend class="text-base font-semibold text-gray-900 dark:text-gray-100">Nutrition Items to Carry (Optional)</legend>
                                     <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 mb-4">
                                        Specify quantities for items you'll bring. Leave all as 0 for automatic selection from your pantry.
                                     </p>

                                     @if($availablePantry === null) {{-- Pantry loading state --}}
                                        <div class="mt-4 p-4 border border-dashed border-gray-300 dark:border-gray-600 rounded-md text-center">
                                             <div class="flex justify-center items-center space-x-2 text-gray-500 dark:text-gray-400">
                                                 <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                <span>Loading your pantry...</span>
                                             </div>
                                        </div>
                                     @elseif($availablePantry->isEmpty())
                                         <p class="mt-4 text-sm text-yellow-700 dark:text-yellow-300 bg-yellow-50 dark:bg-yellow-900/40 p-3 rounded-md border border-yellow-200 dark:border-yellow-700">Your pantry is currently empty. Add products in 'Manage Pantry', or proceed for automatic generation using global products (if any).</p>
                                     @else
                                         <div class="mt-4 space-y-3 max-h-[28rem] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-1 sm:p-4 styled-scrollbar">
                                             @php $currentType = null; @endphp
                                             @foreach ($availablePantry->sortBy('type')->groupBy('type') as $type => $productsOfType)
                                                 {{-- Subheading for Type --}}
                                                  <h4 class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 tracking-wider pt-3 pb-1
                                                               {{ !$loop->first ? 'border-t border-gray-200 dark:border-gray-700' : '' }}">
                                                      {{ Str::title(str_replace('_', ' ', $type)) }}
                                                  </h4>
                                                 @foreach ($productsOfType->sortBy('name') as $product)
                                                     <div class="flex items-center justify-between space-x-3 py-2 px-3 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                                                         <div class="flex items-center space-x-3 min-w-0 flex-1">
                                                             <span class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-600 flex items-center justify-center flex-shrink-0" title="{{ Str::title(str_replace('_',' ', $product->type)) }}">
                                                                  <x-dynamic-component :component="$this->getItemIconFromProduct($product)" class="h-5 w-5 text-slate-500 dark:text-slate-300"/>
                                                             </span>
                                                             <div class="min-w-0 flex-grow">
                                                                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" title="{{ $product->name }} - {{ $product->serving_size_description ?? '1 serving' }}">
                                                                      {{ $product->name }}
                                                                  </p>
                                                                 <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                                     {{ $product->brand ?? 'Generic' }} | {{ $product->serving_size_description ?? '1 serving' }}
                                                                      ({{ number_format($product->carbs_g ?? 0) }}g C, {{ number_format($product->sodium_mg ?? 0) }}mg S)
                                                                 </p>
                                                             </div>
                                                         </div>
                                                          {{-- Quantity Input --}}
                                                          <div class="flex-shrink-0">
                                                              <label for="product_{{ $product->id }}" class="sr-only">Quantity for {{ $product->name }}</label>
                                                              <x-text-input type="number"
                                                                      id="product_{{ $product->id }}"
                                                                      wire:model.defer="selectedProducts.{{ $product->id }}"
                                                                      min="0" max="99" step="1"
                                                                      class="w-20 px-2 py-1 text-center dark:bg-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500"
                                                                      placeholder="0"
                                                                      />
                                                          </div>
                                                     </div>
                                                     <div class="pl-11"><x-input-error :messages="$errors->get('selectedProducts.' . $product->id)" /></div>
                                                 @endforeach
                                             @endforeach
                                         </div>
                                          <x-input-error :messages="$errors->get('selectedProducts')" class="mt-2" />
                                     @endif
                                </fieldset>
                                 {{-- ****** END PRODUCT SELECTION SECTION ****** --}}

                            </div> {{-- End Card Body p-6 space-y-6 --}}

                            {{-- Card Footer for Actions --}}
                            <div class="flex items-center justify-end gap-4 px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                                <div wire:loading wire:target="generatePlan" class="text-sm text-gray-500 dark:text-gray-400">
                                    <svg class="animate-spin inline-block h-4 w-4 mr-1 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Generating Plan...
                                </div>
                                <button type="submit" wire:loading.attr="disabled"
                                    class="inline-flex items-center px-4 py-2 bg-cyan-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-cyan-700 active:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 transition ease-in-out duration-150">
                                    <span wire:loading.remove wire:target="generatePlan">{{ __('Generate Nutrition Plan') }}</span>
                                    <span wire:loading wire:target="generatePlan">Generating...</span>
                                </button>
                            </div> {{-- End Card Footer --}}
                        </form>
                    </div> {{-- End Form Card --}}
                </div> {{-- End Right Column --}}
            </div> {{-- End Grid --}}
        </div> {{-- End Max Width Container --}}
    </div> {{-- End Padding Container py-12 --}}

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <style>
            #route-map-preview:empty::before { content: 'Loading Map...'; display: flex; align-items: center; justify-content: center; height: 100%; color: #6b7280; }
            .leaflet-container { background-color: #fff; }
            .dark .leaflet-container { background-color: #374151; /* dark map bg, tiles will overlay */ }
            .leaflet-control-attribution a { color: #4f46e5 !important; }
            .leaflet-control-zoom { display: none; }

             /* Custom Scrollbar for product list */
             .styled-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
             .styled-scrollbar::-webkit-scrollbar-track { background: transparent; }
             .styled-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; } /* slate-300 */
             .dark .styled-scrollbar::-webkit-scrollbar-thumb { background: #4b5563; } /* gray-600 */
             .styled-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; } /* slate-400 */
             .dark .styled-scrollbar::-webkit-scrollbar-thumb:hover { background: #6b7280; } /* gray-500 */
        </style>
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="https://unpkg.com/@mapbox/polyline@1.1.1/src/polyline.js"></script>
        <script>
            // Self-executing function for Leaflet map
            (function() {
                document.addEventListener('DOMContentLoaded', function () {
                    const mapElementId = 'route-map-preview';
                    const mapElement = document.getElementById(mapElementId);

                    if (!mapElement) {
                         // console.log("Map element for preview not found (likely non-Strava route or no polyline).");
                        return; // No map element, nothing to do
                    }
                    const placeholderContent = mapElement.querySelector('.map-placeholder-content');
                    const encodedPolyline = mapElement.dataset.polyline || '';

                    if (!encodedPolyline) {
                         console.warn("No route polyline data for map preview.");
                         if (placeholderContent) placeholderContent.textContent = 'Route map data unavailable.';
                         else mapElement.innerHTML = '<div class="absolute inset-0 flex items-center justify-center map-placeholder-content text-xs text-gray-500 dark:text-gray-400 px-2 text-center">Route map data unavailable.</div>';
                        return;
                    }

                    if (placeholderContent) placeholderContent.remove(); // Remove placeholder before map init

                    try {
                        console.log("Initializing Leaflet map for route preview on PlanForm...");
                        const map = L.map(mapElement, { scrollWheelZoom: false, attributionControl: false }).setView([0, 0], 2);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);
                        const coordinates = polyline.decode(encodedPolyline);
                        if (coordinates && coordinates.length > 0) {
                            const routeLine = L.polyline(coordinates, { color: '#3b82f6', weight: 3, opacity: 0.8 }).addTo(map);
                            map.fitBounds(routeLine.getBounds().pad(0.1));
                        } else { mapElement.innerHTML = '<div class="absolute inset-0 ...">Invalid map data</div>'; }
                    } catch (error) {
                         console.error("Error initializing Leaflet map on PlanForm:", error);
                         mapElement.innerHTML = '<div class="absolute inset-0 ...">Map Error</div>';
                    }
                });
            })();
        </script>
    @endpush
</div>
