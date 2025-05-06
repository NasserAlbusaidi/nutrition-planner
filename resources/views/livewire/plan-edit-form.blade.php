<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Nutrition Plan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            {{-- Display potential errors from the update process --}}
            @if (session()->has('error'))
                <div class="mb-4 p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg">
                {{-- Form targets the updatePlan method on submit --}}
                <form wire:submit="updatePlan" class="p-6 space-y-6"> {{-- Added space-y for spacing --}}
                    <header>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Plan Information
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Update the basic details for the plan "{{ $plan->name }}".
                        </p>
                    </header>


                    {{-- Plan Name --}}
                    <div>
                        <x-input-label for="name" :value="__('Plan Name')" />
                        <x-text-input wire:model.lazy="name" id="name" class="block mt-1 w-full" type="text"
                            name="name" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    {{-- Planned Start Datetime --}}
                    <div>
                        <x-input-label for="planned_start_datetime" :value="__('Planned Start Time')" />
                        <x-text-input wire:model.lazy="planned_start_datetime" id="planned_start_datetime"
                            class="block mt-1 w-full dark:[color-scheme:dark]" type="datetime-local"
                            name="planned_start_datetime" required />
                        <x-input-error :messages="$errors->get('planned_start_datetime')" class="mt-2" />
                        {{-- Add dark mode color scheme hint for better calendar appearance --}}
                    </div>

                    {{-- Planned Intensity --}}
                    {{-- Planned Intensity --}}
                    <div>
                        <x-input-label for="planned_intensity" :value="__('Planned Intensity')" />
                        {{-- Replace x-select-input with standard HTML select --}}
                        <select wire:model="planned_intensity" id="planned_intensity" name="planned_intensity"
                            class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            required>
                            <option value="" disabled>-- Select Intensity --</option>
                            @foreach ($intensityOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('planned_intensity')" class="mt-2" />
                    </div>
                    {{-- Disclaimer about schedule not updating --}}
                    <div class="p-4 bg-yellow-50 dark:bg-gray-700 border-l-4 border-yellow-400 dark:border-yellow-500">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <x-heroicon-s-exclamation-triangle
                                    class="h-5 w-5 text-yellow-400 dark:text-yellow-500" />
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                    Note: Changing start time or intensity does not automatically regenerate the
                                    nutrition schedule. Weather and target calculations may become inaccurate relative
                                    to the generated items.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex items-center justify-end gap-4"> {{-- Use gap for spacing --}}
                        {{-- Cancel Button --}}
                        <a href="{{ route('plans.show', $plan) }}"
                            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                            Cancel
                        </a>

                        {{-- Save Button --}}
                        <button type="submit" wire:loading.attr="disabled"
                            wire:loading.class="opacity-75 cursor-not-allowed"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            {{-- Loading Spinner --}}
                            <svg wire:loading wire:target="updatePlan"
                                class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <span>Save Changes</span> {{-- Wrap text in span for potentially hiding --}}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
