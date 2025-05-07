<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Nutrition Plan Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8"> {{-- Increased space-y --}}

            {{-- Success Message --}}
            @if (session()->has('message'))
                <div
                    class="p-4 mb-6 bg-green-50 dark:bg-green-800/50 text-green-700 dark:text-green-200 border-l-4 border-green-400 dark:border-green-500 rounded-md shadow">
                    {{ session('message') }}
                </div>
            @endif

            {{-- Plan Summary Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg"> {{-- Increased shadow --}}
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    {{-- Plan Title & Route Name --}}
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ $plan->name }}
                    </h3>
                    @if ($plan->strava_route_name || $plan->source === 'gpx')
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                            Activity: {{ $plan->strava_route_name ?? $plan->routeName }} (Source:
                            {{ Str::upper($plan->source) }}) {{-- Show name from Plan or PlanForm component property --}}
                            {{-- Display route ID only if it exists (Strava routes) --}}
                            @if ($plan->strava_route_id)
                                [ID: {{ $plan->strava_route_id }}]
                            @endif
                        </p>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Activity Source:
                            {{ Str::title($plan->source) }}</p>
                    @endif

                    {{-- Grid for Plan Details --}}
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-5 text-sm">
                        <div class="flex items-center space-x-3">
                            <x-heroicon-o-calendar-days
                                class="h-6 w-6 text-indigo-500 dark:text-indigo-400 flex-shrink-0" />
                            <div><span
                                    class="block text-xs font-medium text-gray-500 dark:text-gray-400">Start</span><span
                                    class="font-medium text-gray-800 dark:text-gray-200">{{ $plan->planned_start_time->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <x-heroicon-o-clock class="h-6 w-6 text-indigo-500 dark:text-indigo-400 flex-shrink-0" />
                            <div><span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Est.
                                    Duration</span><span
                                    class="font-medium text-gray-800 dark:text-gray-200">{{ $this->formatDuration($plan->estimated_duration_seconds) }}</span>
                            </div> {{-- Assumes formatDuration exists in component or use helper --}}
                        </div>
                        <div class="flex items-center space-x-3">
                            <x-heroicon-o-scale class="h-6 w-6 text-indigo-500 dark:text-indigo-400 flex-shrink-0" />
                            <div><span
                                    class="block text-xs font-medium text-gray-500 dark:text-gray-400">Intensity</span><span
                                    class="font-medium text-gray-800 dark:text-gray-200">{{ Str::title(str_replace('_', ' ', $plan->planned_intensity)) }}</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <x-heroicon-o-bolt class="h-6 w-6 text-indigo-500 dark:text-indigo-400 flex-shrink-0" />
                            <div><span class="block text-xs font-medium text-gray-500 dark:text-gray-400">Est. Avg
                                    Power</span><span
                                    class="font-medium text-gray-800 dark:text-gray-200">{{ $plan->estimated_avg_power_watts ?? 'N/A' }}
                                    W</span></div>
                        </div>
                        <div class="flex items-center space-x-3 col-span-2 sm:col-span-1"> {{-- Let weather take more space if needed --}}
                            <x-heroicon-o-cloud class="h-6 w-6 text-indigo-500 dark:text-indigo-400 flex-shrink-0" />
                            <div><span
                                    class="block text-xs font-medium text-gray-500 dark:text-gray-400">Weather</span><span
                                    class="font-medium text-gray-800 dark:text-gray-200">{{ $plan->weather_summary ?? 'Unavailable' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Estimated Totals Footer --}}
                <div
                    class="p-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 flex flex-wrap justify-center sm:justify-start gap-x-6 gap-y-2">
                    <span class="font-semibold text-gray-700 dark:text-gray-300 text-sm">Plan Totals:</span>
                    <span class="inline-flex items-center text-sm text-gray-800 dark:text-gray-200 whitespace-nowrap"
                        title="{{ number_format($plan->estimated_total_carbs_g, 1) }}g Carbohydrates">
                        <x-heroicon-s-cube class="h-5 w-5 mr-1.5 text-amber-500 dark:text-amber-400" />
                        {{ number_format($plan->estimated_total_carbs_g, 0) }}g Carbs
                    </span>
                    <span class="inline-flex items-center text-sm text-gray-800 dark:text-gray-200 whitespace-nowrap"
                        title="{{ number_format($plan->estimated_total_fluid_ml) }}ml Fluid">
                        <x-heroicon-s-beaker class="h-5 w-5 mr-1.5 text-blue-500 dark:text-blue-400" />
                        {{ number_format($plan->estimated_total_fluid_ml) }}ml Fluid
                    </span>
                    <span class="inline-flex items-center text-sm text-gray-800 dark:text-gray-200 whitespace-nowrap"
                        title="{{ number_format($plan->estimated_total_sodium_mg) }}mg Sodium">
                        <x-heroicon-s-sparkles class="h-5 w-5 mr-1.5 text-slate-400 dark:text-slate-500" />
                        {{ number_format($plan->estimated_total_sodium_mg) }}mg Sodium
                    </span>
                </div>
            </div>

            {{-- Plan Schedule Timeline --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        Plan Schedule
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    @php
                        $planItems = $plan->items->sortBy('time_offset_seconds'); // Ensure sorted collection
                        // Group items by hour for easier display (Ensure items are loaded: $plan->load('items'))
                        // Ensure items are ordered by time BEFORE grouping
                        $groupedItems = $plan->items->sortBy('time_offset_seconds')->groupBy(function ($item) {
                            return floor($item->time_offset_seconds / 3600); // Group by hour index (0, 1, 2...)
                        });

                        $durationHoursTotal = max(1, ceil(($plan->estimated_duration_seconds ?? 0) / 3600));
                        $hasItems = $planItems->isNotEmpty();
                        $lastItemOffset = $planItems->last()?->time_offset_seconds; // Get the time offset of the last item
                    @endphp

                    @if (!$hasItems)
                        <p class="text-center text-gray-500 dark:text-gray-400 py-8">No schedule items were generated
                            for this plan.</p>
                    @else
                        {{-- Loop through each potential hour up to the plan duration --}}
                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                @for ($hourIndex = 0; $hourIndex < $durationHoursTotal; $hourIndex++)
                                    @php
                                        $itemsForHour = $groupedItems->get($hourIndex); // Get items for this hour index
                                        $hourStartTimeFormatted = $this->formatDuration($hourIndex * 3600); // e.g., 01:00:00
                                        $hourEndTimeFormatted = $this->formatDuration(($hourIndex + 1) * 3600);
                                    @endphp

                                    {{-- Hour Header Marker --}}
                                    <li>
                                        <div class="relative pb-8">
                                            {{-- Line connecting hours (if not the last hour) --}}
                                            @if ($hourIndex < $durationHoursTotal - 1)
                                                <span
                                                    class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"
                                                    aria-hidden="true"></span>
                                            @endif
                                            <div class="relative flex items-center space-x-3">
                                                <div>
                                                    <span
                                                        class="h-10 w-10 rounded-full bg-indigo-500 dark:bg-indigo-600 flex items-center justify-center ring-4 ring-white dark:ring-gray-800">
                                                        {{-- Icon representing the hour or just hour number --}}
                                                        <span
                                                            class="text-white font-semibold">{{ $hourIndex + 1 }}</span>
                                                        {{-- <x-heroicon-o-clock class="h-6 w-6 text-white" /> --}}
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1 pt-1.5">
                                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                                        Hour {{ $hourIndex + 1 }}
                                                        <span
                                                            class="ml-2 text-xs font-normal text-gray-500 dark:text-gray-400">({{ $hourStartTimeFormatted }}
                                                            - {{ $hourEndTimeFormatted }})</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </li>

                                    {{-- Items within the Hour --}}
                                    @if ($itemsForHour && $itemsForHour->count() > 0)
                                        {{-- Group by exact time for display --}}
                                        @php $itemsGroupedByTime = $itemsForHour->groupBy('time_offset_seconds'); @endphp
                                        @foreach ($itemsGroupedByTime as $timeOffset => $itemsAtThisTime)
                                            <li>
                                                <div class="relative pb-8">
                                                    @if ($timeOffset !== $lastItemOffset)
                                                        <span
                                                            class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"
                                                            aria-hidden="true"></span>
                                                    @endif
                                                    <div class="relative flex items-start space-x-3">
                                                        {{-- Time Marker --}}
                                                        <div class="relative px-1">
                                                            <div
                                                                class="h-8 w-8 bg-gray-100 dark:bg-gray-700 rounded-full ring-4 ring-white dark:ring-gray-800 flex items-center justify-center">
                                                                <span
                                                                    class="font-mono text-[11px] font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $this->formatTimeOffset($timeOffset) }}</span>
                                                            </div>
                                                        </div>
                                                        {{-- Item Details for this timestamp --}}
                                                        <div class="min-w-0 flex-1 py-1.5">
                                                            <div class="space-y-2">
                                                                @foreach ($itemsAtThisTime as $item)
                                                                    <div
                                                                        class="p-3 bg-slate-50 dark:bg-slate-700/40 rounded-md border border-slate-200 dark:border-slate-600/50 shadow-sm">
                                                                        <div
                                                                            class="flex flex-col sm:flex-row sm:items-start sm:justify-between">
                                                                            {{-- Left Side: Icon, Type, Name, Qty --}}
                                                                            <div
                                                                                class="flex items-center space-x-2 mb-1 sm:mb-0 flex-grow pr-2">
                                                                                @php $iconName = $this->getItemIcon($item); @endphp
                                                                                <x-dynamic-component :component="$iconName"
                                                                                    class="h-5 w-5 text-indigo-500 dark:text-indigo-400 flex-shrink-0" />
                                                                                <p
                                                                                    class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                                    {{ $item->product_name ?? ($item->product_name_override ?? Str::title($item->instruction_type)) }}
                                                                                    <span
                                                                                        class="text-gray-600 dark:text-gray-300">({{ $item->quantity_description }})</span>
                                                                                </p>
                                                                            </div>
                                                                            {{-- Right Side: C/F/S Breakdown --}}
                                                                            <div
                                                                                class="flex items-center justify-start sm:justify-end space-x-3 text-xs text-gray-600 dark:text-gray-400 flex-wrap tabular-nums flex-shrink-0">
                                                                                <span>C:{{ number_format($item->calculated_carbs_g, 0) }}g</span>
                                                                                <span>F:{{ number_format($item->calculated_fluid_ml, 0) }}ml</span>
                                                                                <span>S:{{ number_format($item->calculated_sodium_mg, 0) }}mg</span>
                                                                            </div>
                                                                        </div>
                                                                        {{-- Notes Below --}}
                                                                        @if ($item->notes)
                                                                            <p
                                                                                class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 pl-7">
                                                                                {{-- Indent notes slightly --}}
                                                                                {{ $item->notes }}
                                                                            </p>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    @else
                                        {{-- No items for this specific hour --}}
                                        <li>
                                            <div class="relative pb-8">
                                                {{-- Line connecting hours --}}
                                                @if ($hourIndex < $durationHoursTotal - 1)
                                                    <span
                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"
                                                        aria-hidden="true"></span>
                                                @endif
                                                <div class="relative flex items-start space-x-3">
                                                    <div class="relative px-1 opacity-0">
                                                        <div class="h-8 w-8"></div>
                                                    </div> {{-- Placeholder for alignment --}}
                                                    <div class="min-w-0 flex-1 py-1.5">
                                                        <p class="text-xs text-gray-400 dark:text-gray-500 italic">No
                                                            items scheduled this hour.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    @endif
                                @endfor
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="mt-6 flex justify-end space-x-3">
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                    <x-heroicon-o-arrow-left class="h-4 w-4 mr-2" /> Back to Dashboard
                </a>
            </div>

        </div>
    </div>
</div>
