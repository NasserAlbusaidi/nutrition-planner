<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight">
            {{ __('Nutrition Plan Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Success Message (uses auto-hide) --}}
            @if (session()->has('message'))
                <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3500)"
                    class="p-4 mb-6 bg-emerald-50 dark:bg-emerald-800/50 text-emerald-700 dark:text-emerald-200 border-l-4 border-emerald-400 dark:border-emerald-500 rounded-md shadow-md"
                    role="alert">
                    <div class="flex items-center"><x-heroicon-s-check-circle class="h-5 w-5 mr-3 flex-shrink-0" />
                        <p class="text-sm font-medium">{{ session('message') }}</p>
                    </div>
                </div>
            @endif



            {{-- Plan Summary Card --}}
            <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-lg sm:rounded-lg">
                <div class="p-6 border-b border-slate-200 dark:border-slate-700">
                    {{-- Plan Title & Route Name ... --}}
                    <h3 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 mb-2">
                        {{ $plan->name }}</h3>
                    {{-- ... activity source info ... --}}
                    @if ($plan->strava_route_name || $plan->source === 'gpx')
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Activity:
                            {{ $plan->strava_route_name ?? ($plan->routeName ?? 'N/A') }} (Source:
                            {{ Str::upper($plan->source) }})
                        </p>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Activity Source:
                            {{ Str::title($plan->source) }}</p>
                    @endif

                    {{-- Grid for Plan Details -- adjust grid-cols as needed --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-6 text-sm">
                        {{-- e.g., 4 cols on xl --}}
                        {{-- Start --}}
                        <div class="flex items-center space-x-3"><x-heroicon-o-calendar-days
                                class="h-6 w-6 text-cyan-600 dark:text-cyan-400 flex-shrink-0" />
                            <div><span
                                    class="block text-xs font-medium text-slate-500 dark:text-slate-400">Start</span><span
                                    class="font-medium text-slate-800 dark:text-slate-200">{{ $plan->planned_start_time->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                        {{-- Est. Duration --}}
                        <div class="flex items-center space-x-3"><x-heroicon-o-clock
                                class="h-6 w-6 text-cyan-600 dark:text-cyan-400 flex-shrink-0" />
                            <div><span class="block text-xs font-medium text-slate-500 dark:text-slate-400">Est.
                                    Duration</span><span
                                    class="font-medium text-slate-800 dark:text-slate-200">{{ $this->formatDuration($plan->estimated_duration_seconds) }}</span>
                            </div>
                        </div>
                        {{-- Intensity --}}
                        <div class="flex items-center space-x-3"><x-heroicon-o-scale
                                class="h-6 w-6 text-cyan-600 dark:text-cyan-400 flex-shrink-0" />
                            <div><span
                                    class="block text-xs font-medium text-slate-500 dark:text-slate-400">Intensity</span><span
                                    class="font-medium text-slate-800 dark:text-slate-200">{{ Str::title(str_replace('_', ' ', $plan->planned_intensity)) }}</span>
                            </div>
                        </div>

                        {{-- *** NEW: Estimated Time of Arrival (ETA) *** --}}
                        <div class="flex items-center space-x-3">
                            <x-heroicon-o-flag class="h-6 w-6 text-cyan-600 dark:text-cyan-400 flex-shrink-0" />
                            <div><span class="block text-xs font-medium text-slate-500 dark:text-slate-400">Est. Arrival
                                    (ETA)</span><span
                                    class="font-medium text-slate-800 dark:text-slate-200">{{ $this->estimatedTimeOfArrival }}</span>
                            </div>
                        </div>

                        {{-- Est. Avg Power --}}
                        <div class="flex items-center space-x-3"><x-heroicon-o-bolt
                                class="h-6 w-6 text-cyan-600 dark:text-cyan-400 flex-shrink-0" />
                            <div><span class="block text-xs font-medium text-slate-500 dark:text-slate-400">Est. Avg
                                    Power</span><span
                                    class="font-medium text-slate-800 dark:text-slate-200">{{ $plan->estimated_avg_power_watts ? $plan->estimated_avg_power_watts . ' W' : 'N/A' }}</span>
                            </div>
                        </div>

                        {{-- *** NEW: Estimated Average Speed *** --}}
                        <div class="flex items-center space-x-3">
                            <x-heroicon-o-rocket-launch
                                class="h-6 w-6 text-cyan-600 dark:text-cyan-400 flex-shrink-0" />
                            <div><span class="block text-xs font-medium text-slate-500 dark:text-slate-400">Est. Avg
                                    Speed</span><span
                                    class="font-medium text-slate-800 dark:text-slate-200">{{ $this->estimatedAverageSpeed }}</span>
                            </div>
                        </div>

                        {{-- Weather (might make this col-span-2 if you have an odd number overall or want it wider) --}}
                        <div class="flex items-center space-x-3 md:col-span-2 xl:col-span-1"> {{-- Example: take remaining space or force wider --}}
                            <x-heroicon-o-cloud class="h-6 w-6 text-cyan-600 dark:text-cyan-400 flex-shrink-0" />
                            <div><span
                                    class="block text-xs font-medium text-slate-500 dark:text-slate-400">Weather</span><span
                                    class="font-medium text-slate-800 dark:text-slate-200">{{ $plan->weather_summary ?? 'Unavailable' }}</span>
                            </div>
                        </div>
                        {{-- You might want to explicitly add Distance here too if not clear from "Activity" name --}}
                        <div class="flex items-center space-x-3">
                            <x-heroicon-o-map-pin class="h-6 w-6 text-cyan-600 dark:text-cyan-400 flex-shrink-0" />
                            <div><span
                                    class="block text-xs font-medium text-slate-500 dark:text-slate-400">Distance</span><span
                                    class="font-medium text-slate-800 dark:text-slate-200">{{ number_format($plan->estimated_distance_km ?? 0, 1) }}
                                    km</span></div>
                        </div>

                    </div>
                </div>
                {{-- Estimated Totals Footer --}}
                <div
                    class="p-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700">
                    <div class="flex flex-wrap justify-center sm:justify-start gap-x-6 gap-y-3 items-center">
                        <span class="font-semibold text-slate-700 dark:text-slate-300 text-sm">Plan Totals:</span>

                        {{-- Carbs Total --}}
                        <span class="inline-flex items-center text-sm text-slate-800 dark:text-slate-200 whitespace-nowrap"
                            title="Scheduled: {{ number_format($plan->estimated_total_carbs_g, 1) }}g Carbs / Recommended: {{ number_format($plan->recommended_total_carbs_g, 1) }}g Carbs">
                            <x-heroicon-s-cube class="h-5 w-5 mr-1.5 text-amber-500 dark:text-amber-400" />
                            {{ number_format($plan->estimated_total_carbs_g, 0) }}g
                            @if ($plan->recommended_total_carbs_g) {{-- Only show if recommended exists --}}
                                <span class="text-xs text-slate-500 dark:text-slate-400 ml-1">/ {{ number_format($plan->recommended_total_carbs_g, 0) }}g</span>
                            @endif
                        </span>

                        {{-- Fluid Total --}}
                        <span class="inline-flex items-center text-sm text-slate-800 dark:text-slate-200 whitespace-nowrap"
                            title="Scheduled: {{ number_format($plan->estimated_total_fluid_ml) }}ml Fluid / Recommended: {{ number_format($plan->recommended_total_fluid_ml) }}ml Fluid">
                            <x-heroicon-s-beaker class="h-5 w-5 mr-1.5 text-sky-500 dark:text-sky-400" />
                            {{ number_format($plan->estimated_total_fluid_ml) }}ml
                            @if ($plan->recommended_total_fluid_ml)
                                <span class="text-xs text-slate-500 dark:text-slate-400 ml-1">/ {{ number_format($plan->recommended_total_fluid_ml) }}ml</span>
                            @endif
                        </span>

                        {{-- Sodium Total --}}
                        <span class="inline-flex items-center text-sm text-slate-800 dark:text-slate-200 whitespace-nowrap"
                            title="Scheduled: {{ number_format($plan->estimated_total_sodium_mg) }}mg Sodium / Recommended: {{ number_format($plan->recommended_total_sodium_mg) }}mg Sodium">
                            <x-heroicon-s-sparkles class="h-5 w-5 mr-1.5 text-violet-400 dark:text-violet-500" />
                            {{ number_format($plan->estimated_total_sodium_mg) }}mg
                            @if ($plan->recommended_total_sodium_mg)
                                <span class="text-xs text-slate-500 dark:text-slate-400 ml-1">/ {{ number_format($plan->recommended_total_sodium_mg) }}mg</span>
                            @endif
                        </span>
                    </div>

                    {{-- Conditional Warning Message for Deficits --}}
                    @php
                        $showDeficitMessage = false;
                        $deficitMessages = [];
                        $deficitThreshold = 25; // Percentage deficit to trigger warning

                        if ($plan->recommended_total_carbs_g && $plan->recommended_total_carbs_g > 0) {
                            $carbDeficitPercentage = (($plan->recommended_total_carbs_g - $plan->estimated_total_carbs_g) / $plan->recommended_total_carbs_g) * 100;
                            if ($carbDeficitPercentage > $deficitThreshold) {
                                $deficitMessages[] = 'carbs';
                                $showDeficitMessage = true;
                            }
                        }
                        if ($plan->recommended_total_fluid_ml && $plan->recommended_total_fluid_ml > 0) {
                            $fluidDeficitPercentage = (($plan->recommended_total_fluid_ml - $plan->estimated_total_fluid_ml) / $plan->recommended_total_fluid_ml) * 100;
                            if ($fluidDeficitPercentage > $deficitThreshold) {
                                $deficitMessages[] = 'fluid';
                                $showDeficitMessage = true;
                            }
                        }
                        if ($plan->recommended_total_sodium_mg && $plan->recommended_total_sodium_mg > 0) {
                            $sodiumDeficitPercentage = (($plan->recommended_total_sodium_mg - $plan->estimated_total_sodium_mg) / $plan->recommended_total_sodium_mg) * 100;
                            if ($sodiumDeficitPercentage > $deficitThreshold) {
                                $deficitMessages[] = 'sodium';
                                $showDeficitMessage = true;
                            }
                        }
                    @endphp

                    @if ($showDeficitMessage)
                        <div class="mt-3 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-800/30 p-2 rounded-md flex items-start">
                            <x-heroicon-s-exclamation-triangle class="h-4 w-4 mr-2 mt-0.5 flex-shrink-0" />
                            <span>
                                Note: Your scheduled plan is significantly lower than recommended for
                                @foreach ($deficitMessages as $nutrient)
                                    {{ $nutrient }}@if (!$loop->last && count($deficitMessages) > 2), @elseif (!$loop->last && count($deficitMessages) == 2) and @endif
                                @endforeach.
                                This is based on your selected items. Review and consider adjusting for optimal fueling.
                            </span>
                        </div>
                    @endif

                    {{-- Display specific warnings from plan_notes (if any) --}}
                    @if (!empty($plan->plan_notes))
                        <div class="mt-3 text-xs text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-700/50 p-2 rounded-md">
                            <p class="font-semibold mb-1">Additional Plan Notes:</p>
                            <div style="white-space: pre-wrap;">{{ $plan->plan_notes }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ****** PREPARATION SUMMARY SECTION ****** --}}
            @if (!empty($preparationSummary['products'])) {{-- Use correct key --}}
                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="px-4 py-4 sm:px-6 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100 flex items-center">
                            <x-heroicon-o-shopping-bag class="h-5 w-5 mr-2 text-cyan-600 dark:text-cyan-400" />
                            Preparation Checklist
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"> Total items needed for this plan.
                        </p>
                    </div>
                    <ul role="list" class="divide-y divide-slate-200 dark:divide-slate-700">
                        {{-- Bottles Needed --}}
                        <li class="py-3 sm:py-4 px-4 sm:px-6"> {{-- Add padding --}}
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0"> <span
                                        class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center"><x-heroicon-o-beaker
                                            class="h-5 w-5 text-blue-600 dark:text-blue-400" /></span></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">Bottles
                                    </p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 truncate">Approx.
                                        {{ $preparationSummary['bottles_needed'] }} x 750ml bottle(s) needed</p>
                                </div>
                            </div>
                        </li>

                        {{-- Loop through SPECIFIC Products --}}
                        @foreach ($preparationSummary['products'] as $productInfo)
                            {{-- Renamed key --}}
                            <li
                                class="py-3 sm:py-4 px-4 sm:px-6 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <span
                                            class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                            @php $prepIcon = $this->getPrepItemIcon($productInfo['type']); @endphp
                                            <x-dynamic-component :component="$prepIcon"
                                                class="h-5 w-5 text-slate-500 dark:text-slate-400" />
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">
                                            {{ $productInfo['name'] }}</p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                                            {{ $productInfo['notes'] ?? '' }}</p>
                                    </div>
                                    <div
                                        class="inline-flex items-center text-sm font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                        {{ $productInfo['total_qty_desc'] }}
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            {{-- ****** END PREPARATION SUMMARY SECTION ****** --}}


            {{-- Plan Schedule Card --}}
            <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-lg sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100">Plan Schedule</h3>
                </div>
                <div class="p-4 sm:p-6"> {{-- Consistent padding --}}
                    @php
                        $planItems = $plan->items->sortBy('time_offset_seconds');
                        $groupedItems = $planItems->groupBy(fn($item) => floor($item->time_offset_seconds / 3600));
                        $durationHoursTotal = max(1, ceil(($plan->estimated_duration_seconds ?? 0) / 3600));
                        $hasItems = $planItems->isNotEmpty();
                        $lastItemOffset = $planItems->last()?->time_offset_seconds; // Needed for line logic
                    @endphp

                    @if (!$hasItems)
                        <p class="text-center text-slate-500 dark:text-slate-400 py-10">No schedule items generated for
                            this plan.</p>
                    @else
                        <div class="flow-root">
                            <ul role="list">
                                {{-- START Marker --}}
                                <li class="relative pb-6">
                                    {{-- Start line slightly longer? --}}
                                    <div class="absolute left-5 top-5 -ml-px h-[calc(100%-1.25rem)] w-0.5 bg-slate-200 dark:bg-slate-700"
                                        aria-hidden="true"></div>
                                    <div class="relative flex items-center space-x-3">
                                        <div><span
                                                class="h-10 w-10 rounded-full bg-emerald-500 flex items-center justify-center ring-8 ring-white dark:ring-slate-800 shadow-sm"><x-heroicon-s-play
                                                    class="h-5 w-5 text-white" /></span></div>
                                        <div class="min-w-0 flex-1 pt-1.5">
                                            <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                                Activity Start <span class="font-mono text-xs">(00:00:00)</span></p>
                                        </div>
                                    </div>
                                </li>
                                {{-- Loop through each hour --}}
                                @for ($hourIndex = 0; $hourIndex < $durationHoursTotal; $hourIndex++)
                                    @php
                                        $itemsForHour = $groupedItems->get($hourIndex); // Collection or null
                                        $hourStartTimeFormatted = $this->formatDuration($hourIndex * 3600);
                                        $hourEndTimeFormatted = $this->formatDuration(($hourIndex + 1) * 3600);
                                        $weatherString = $this->getWeatherForHour($hourIndex);
                                        $isLastHour = $hourIndex === $durationHoursTotal - 1;
                                        $hourHasItems = $itemsForHour && $itemsForHour->count() > 0; // Check if hour has items
                                    @endphp

                                    {{-- Hour Section Start (uses LI for timeline structure) --}}
                                    <li
                                        class="relative {{ $hourIndex % 2 != 0 ? 'bg-slate-50/80 dark:bg-slate-800/60' : '' }} py-5 px-2 -mx-2 rounded-lg">
                                        {{-- Line From Above Hour Marker --}}
                                        <div class="absolute left-5 -top-5 -ml-px h-5 w-0.5 bg-slate-200 dark:bg-slate-700"
                                            aria-hidden="true"></div>
                                        {{-- Line continuing below hour block --}}
                                        {{-- Only draw line if not last hour OR if last hour HAS items --}}
                                        @if (!$isLastHour || $hourHasItems)
                                            <div class="absolute left-5 top-10 -ml-px h-full w-0.5 bg-slate-200 dark:bg-slate-700"
                                                aria-hidden="true"></div>
                                        @endif

                                        <div class="relative mb-5"> {{-- Hour Header --}}
                                            <div class="relative flex items-center space-x-3">
                                                <div><span
                                                        class="h-10 w-10 rounded-full bg-cyan-600 dark:bg-cyan-700 flex items-center justify-center ring-8 ring-white dark:ring-slate-800 shadow-sm"><span
                                                            class="text-base font-semibold text-white">{{ $hourIndex + 1 }}</span></span>
                                                </div>
                                                <div
                                                    class="min-w-0 flex-1 pt-1 flex justify-between items-center flex-wrap gap-x-4 gap-y-1">
                                                    <div>
                                                        <p
                                                            class="text-base font-semibold text-slate-900 dark:text-slate-100">
                                                            Hour {{ $hourIndex + 1 }} <span
                                                                class="text-xs font-normal text-slate-500 dark:text-slate-400">({{ $hourStartTimeFormatted }}
                                                                - {{ $hourEndTimeFormatted }})</span></p>
                                                    </div>
                                                    <div class="text-xs font-medium text-slate-500 dark:text-slate-300 flex items-center whitespace-nowrap rounded-full bg-slate-100 dark:bg-slate-700/80 px-2 py-0.5"
                                                        title="Planned weather for this hour"><x-heroicon-o-cloud
                                                            class="h-4 w-4 mr-1 text-sky-500 dark:text-sky-400" /><span>{!! $weatherString !!}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Items within the Hour (OR Empty Message) --}}
                                        <div class="ml-[4.5rem] space-y-5 pl-2">
                                            @if ($hourHasItems)
                                                {{-- Check if there are items FOR THIS HOUR --}}
                                                @php $itemsGroupedByTime = $itemsForHour->groupBy('time_offset_seconds'); @endphp
                                                @foreach ($itemsGroupedByTime as $timeOffset => $itemsAtThisTime)
                                                    {{-- Item Group Container --}}
                                                    <div class="relative group">
                                                        {{-- Line connecting item group markers - stop if last group AND last hour --}}
                                                        @if (!($isLastHour && $loop->last))
                                                            <div class="absolute -left-[1.8rem] top-4 -ml-px h-full w-0.5 bg-slate-200 dark:bg-slate-700"
                                                                aria-hidden="true"></div>
                                                        @endif
                                                        {{-- Time Marker --}}
                                                        <div class="absolute -left-[1.8rem] top-2.5">
                                                            <div
                                                                class="h-3 w-3 bg-slate-400 dark:bg-slate-500 rounded-full ring-4 ring-white dark:ring-slate-800 group-hover:ring-cyan-500 transition-colors">
                                                            </div>
                                                        </div>
                                                        {{-- Item Details Box(es) --}}
                                                        <div class="space-y-3">
                                                            <p
                                                                class="text-xs font-bold text-cyan-700 dark:text-cyan-400 uppercase tracking-wider mb-1 ml-3">
                                                                {{ $this->formatTimeOffset($timeOffset) }}</p>
                                                            @foreach ($itemsAtThisTime as $item)
                                                                {{-- Actual Item Card --}}
                                                                <div
                                                                    class="bg-white dark:bg-slate-700/70 p-4 rounded-lg border border-slate-200 dark:border-slate-600 shadow hover:shadow-md hover:border-cyan-400 dark:hover:border-cyan-600 transition-all">
                                                                    {{-- Content of the card (icon, title, qty, notes, C/F/S) --}}
                                                                    <div
                                                                        class="flex flex-col sm:flex-row sm:items-start sm:justify-between">
                                                                        <div
                                                                            class="flex items-start space-x-3 mb-2 sm:mb-0 flex-grow pr-2">
                                                                            <div class="flex-shrink-0 pt-0.5">
                                                                                <x-dynamic-component :component="$this->getItemIcon($item)"
                                                                                    class="h-5 w-5 text-cyan-600 dark:text-cyan-400 " />
                                                                            </div>
                                                                            <div>
                                                                                <h4
                                                                                    class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                                                    {{ $item->product_name ?? ($item->product_name_override ?? Str::title($item->instruction_type)) }}
                                                                                </h4>
                                                                                <p
                                                                                    class="text-xs text-slate-600 dark:text-slate-300">
                                                                                    {{ $item->quantity_description }}
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                        <div
                                                                            class="flex items-center justify-start sm:justify-end space-x-4 text-xs text-slate-600 dark:text-slate-400 tabular-nums flex-shrink-0 pl-8 sm:pl-0">
                                                                            <span>C: <strong
                                                                                    class="dark:text-slate-200">{{ number_format($item->calculated_carbs_g, 0) }}g</strong></span>
                                                                            <span>F: <strong
                                                                                    class="dark:text-slate-200">{{ number_format($item->calculated_fluid_ml, 0) }}ml</strong></span>
                                                                            <span>S: <strong
                                                                                    class="dark:text-slate-200">{{ number_format($item->calculated_sodium_mg, 0) }}mg</strong></span>
                                                                        </div>
                                                                    </div>
                                                                    @if ($item->notes)
                                                                        <p
                                                                            class="text-xs text-slate-500 dark:text-slate-400 mt-2 pl-8">
                                                                            {{ $item->notes }}</p>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach {{-- End Item Group Loop --}}
                                            @else
                                                {{-- This hour has NO items --}}
                                                <div class="relative pt-1"> {{-- Adjust padding/alignment slightly --}}
                                                    <x-heroicon-o-no-symbol
                                                        class="h-4 w-4 text-slate-400 dark:text-slate-500 inline-block mr-1" />
                                                    <p
                                                        class="text-xs text-slate-400 dark:text-slate-500 italic inline">
                                                        No specific items scheduled this hour.</p>
                                                </div>
                                            @endif {{-- End Hour Has Items Check --}}
                                        </div>
                                    </li> {{-- End Hour Section LI --}}
                                @endfor {{-- End Hour Loop --}}

                                {{-- END Marker --}}
                                <li class="relative mt-4">
                                    <div class="absolute left-5 -top-4 -ml-px h-4 w-0.5 bg-slate-200 dark:bg-slate-700"
                                        aria-hidden="true"></div>
                                    <div class="relative flex items-center space-x-3">
                                        <div><span
                                                class="h-10 w-10 rounded-full bg-emerald-500 flex items-center justify-center ring-8 ring-white dark:ring-slate-800 shadow-sm"><x-heroicon-s-flag
                                                    class="h-5 w-5 text-white" /></span></div>
                                        <div class="min-w-0 flex-1 pt-1.5">
                                            <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                                Activity End <span
                                                    class="font-mono text-xs">({{ $this->formatDuration($plan->estimated_duration_seconds) }})</span>
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    @endif {{-- End HasItems Check --}}
                </div> {{-- End Card Body p-6 --}}
            </div> {{-- End Schedule Card --}}
            {{-- Actions --}}
            <div class="mt-8 flex flex-col sm:flex-row justify-end items-center gap-4">
                <span class="text-xs text-slate-500 dark:text-slate-400 italic mr-4">(Note: Plan is based on forecast
                    at generation time. Regeneration coming soon!)</span>
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center px-4 py-2 bg-slate-700 dark:bg-slate-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-slate-600 dark:hover:bg-slate-500 active:bg-slate-800 focus:outline-none focus:border-slate-900 dark:focus:border-slate-300 focus:ring ring-slate-300 dark:focus:ring-slate-700 transition ease-in-out duration-150"><x-heroicon-o-arrow-left
                        class="h-4 w-4 mr-2" />Back</a>
            </div>

        </div>
    </div>
</div>
