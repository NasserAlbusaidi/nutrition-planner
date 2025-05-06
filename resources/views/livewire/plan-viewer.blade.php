<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Nutrition Plan Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Success Message --}}
            @if (session()->has('message'))
                <div class="p-4 bg-green-100 dark:bg-green-900 dark:text-green-100 border border-green-300 dark:border-green-700 rounded-lg shadow-sm text-green-700">
                    {{ session('message') }}
                </div>
            @endif

            {{-- Plan Summary Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md rounded-lg">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">{{ $plan->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Route: {{ $plan->strava_route_name ?? 'N/A' }}</p>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                        {{-- Planned Start --}}
                        <div class="flex items-center space-x-2">
                            <x-heroicon-o-calendar-days class="h-5 w-5 text-indigo-500"/>
                            <div>
                                <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Start</span>
                                <span class="text-gray-800 dark:text-gray-200">{{ $plan->planned_start_time->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                        {{-- Duration --}}
                         <div class="flex items-center space-x-2">
                            <x-heroicon-o-clock class="h-5 w-5 text-indigo-500"/>
                            <div>
                                <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Est. Duration</span>
                                <span class="text-gray-800 dark:text-gray-200">{{ $this->formatDuration($plan->estimated_duration_seconds) }}</span>
                            </div>
                        </div>
                         {{-- Intensity --}}
                        <div class="flex items-center space-x-2">
                            <x-heroicon-o-scale class="h-5 w-5 text-indigo-500"/>
                            <div>
                                <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Intensity</span>
                                <span class="text-gray-800 dark:text-gray-200">{{ Str::title(str_replace('_', ' ', $plan->planned_intensity)) }}</span>
                            </div>
                        </div>
                         {{-- Avg Power --}}
                         <div class="flex items-center space-x-2">
                            <x-heroicon-o-bolt class="h-5 w-5 text-indigo-500"/>
                             <div>
                                <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Est. Avg Power</span>
                                <span class="text-gray-800 dark:text-gray-200">{{ $plan->estimated_avg_power_watts ?? 'N/A' }} W</span>
                            </div>
                        </div>
                         {{-- Weather --}}
                         <div class="flex items-center space-x-2 col-span-2 sm:col-span-1">
                            <x-heroicon-o-cloud class="h-5 w-5 text-indigo-500"/>
                             <div>
                                <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Weather</span>
                                <span class="text-gray-800 dark:text-gray-200">{{ $plan->weather_summary ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                 {{-- Estimated Totals Footer --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700 text-sm text-center sm:text-left">
                     <span class="font-semibold text-gray-700 dark:text-gray-300">Totals:</span>
                     <span class="text-gray-800 dark:text-gray-200 ml-2">
                         <x-heroicon-o-cube class="h-4 w-4 inline-block text-yellow-600 mr-1"/> {{ number_format($plan->estimated_total_carbs_g, 1) }}g Carbs
                     </span> |
                     <span class="text-gray-800 dark:text-gray-200 ml-2">
                         <x-heroicon-o-underline class="h-4 w-4 inline-block text-blue-600 mr-1"/> {{ number_format($plan->estimated_total_fluid_ml) }}ml Fluid
                     </span> |
                     <span class="text-gray-800 dark:text-gray-200 ml-2">
                         <x-heroicon-o-sparkles class="h-4 w-4 inline-block text-gray-500 mr-1"/> {{ number_format($plan->estimated_total_sodium_mg) }}mg Sodium
                     </span>
                </div>
            </div>

            {{-- Plan Schedule Timeline --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md rounded-lg">
                 <div class="p-6">
                     <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">Plan Schedule</h3>
                     <div class="space-y-6">
                        @forelse ($plan->items /* Already ordered in mount */ as $index => $item)
                            <div class="flex items-start space-x-4">
                                {{-- Time & Timeline Dot --}}
                                <div class="flex flex-col items-center pt-1">
                                    <span class="font-mono text-sm font-semibold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                                        {{ $this->formatTimeOffset($item->time_offset_seconds) }}
                                    </span>
                                    {{-- Timeline Line (optional, adds complexity) --}}
                                    {{-- @if(!$loop->last)
                                        <div class="w-px h-full bg-gray-300 dark:bg-gray-600 mt-2"></div>
                                    @endif --}}
                                </div>

                                {{-- Item Details Card --}}
                                <div class="flex-1 bg-gray-50 dark:bg-gray-700 p-4 rounded-md border border-gray-200 dark:border-gray-600">
                                    <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-2">
                                         {{-- Instruction & Product --}}
                                        <div class="flex items-center space-x-2 mb-2 sm:mb-0">
                                             @php $iconName = $this->getItemIcon($item); @endphp
                                             <x-dynamic-component :component="$iconName" class="h-6 w-6 text-indigo-500 flex-shrink-0"/>
                                             <span class="font-semibold text-gray-800 dark:text-gray-100">
                                                {{ Str::title($item->instruction_type) }}:
                                                {{ $item->product->name ?? $item->product_name_override ?? 'Item' }}
                                             </span>
                                             <span class="text-sm text-gray-600 dark:text-gray-300">({{ $item->quantity_description }})</span>
                                        </div>
                                        {{-- Nutritional Details --}}
                                        <div class="flex items-center space-x-3 text-xs text-gray-600 dark:text-gray-400 flex-wrap">
                                            <span>C: {{ round($item->calculated_carbs_g) }}g</span>
                                            <span>F: {{ round($item->calculated_fluid_ml) }}ml</span>
                                            <span>S: {{ round($item->calculated_sodium_mg) }}mg</span>
                                        </div>
                                    </div>
                                     {{-- Notes --}}
                                    @if($item->notes)
                                    <p class="text-sm text-gray-500 dark:text-gray-300 mt-1 pl-8 sm:pl-0">
                                         {{ $item->notes }}
                                    </p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                                No schedule items generated for this plan.
                            </div>
                        @endforelse
                    </div>
                 </div>
            </div>

            {{-- Actions --}}
            <div class="mt-6 flex justify-end space-x-3">
                 <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                     <x-heroicon-o-arrow-left class="h-4 w-4 mr-2"/>
                     Back to Dashboard
                 </a>
                 {{-- Add other actions like Edit, Delete, Export here if needed --}}
                 {{-- Example:
                 <button wire:click="editPlan" class="...">Edit</button>
                 <button wire:click="deletePlan" class="...">Delete</button>
                 --}}
            </div>

        </div>
    </div>
</div>
