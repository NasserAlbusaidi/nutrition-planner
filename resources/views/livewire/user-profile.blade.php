<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('My Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Success Message --}}
             @if (session()->has('message'))
                 {{-- Enhanced message style --}}
                <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)"
                    class="p-4 mb-6 bg-green-50 dark:bg-green-800/50 text-green-700 dark:text-green-200 border-l-4 border-green-400 dark:border-green-500 rounded-md shadow-md"
                    role="alert">
                    <div class="flex items-center">
                        <x-heroicon-s-check-circle class="h-5 w-5 mr-3 flex-shrink-0"/>
                        <p class="text-sm font-medium">{{ session('message') }}</p>
                    </div>
                </div>
            @endif

            {{-- Card for Profile Form Sections --}}
            <div class="bg-white dark:bg-gray-800 shadow-xl overflow-hidden sm:rounded-lg">

                 {{-- Use a standard form section component or layout (like Jetstream's form-section) for better structure --}}
                {{-- Here's an approximation using divs and standard Tailwind classes --}}
                <form wire:submit.prevent="save">
                    {{-- Use grid for layout (title/desc on left, form on right on larger screens) --}}
                     {{-- Section 1: Strava Connection --}}
                    <div class="md:grid md:grid-cols-3 md:gap-6 px-4 py-5 sm:p-6">
                         <div class="md:col-span-1">
                             <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">Strava Connection</h3>
                            @if(Auth::user()->strava_user_id)
                                 <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                     Your account is connected to Strava. You can import routes for planning.
                                 </p>
                             @else
                                 <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                     Connect your Strava account to easily import your saved routes for nutrition planning.
                                 </p>
                             @endif
                         </div>
                         <div class="mt-5 md:mt-0 md:col-span-2">
                            @if(Auth::user()->strava_user_id)
                                <div class="space-y-3">
                                    <div class="flex items-center space-x-2">
                                         <svg class="h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                                             <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                             <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                                        </svg>
                                        <span class="text-sm font-medium text-green-700 dark:text-green-400">Connected (User ID: {{ Auth::user()->strava_user_id }})</span>
                                    </div>
                                    <button type="button" {{-- Use type="button" if wire:click handles submission logic --}}
                                            wire:click="disconnectStrava"
                                            wire:loading.attr="disabled"
                                            wire:confirm="Are you sure you want to disconnect your Strava account? This will remove access tokens but won't delete plans based on Strava routes."
                                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-800 focus:outline-none focus:border-red-700 focus:ring ring-red-300 disabled:opacity-50 transition ease-in-out duration-150">
                                        <x-heroicon-o-x-circle class="h-4 w-4 mr-1.5"/>
                                        Disconnect Strava
                                        <svg wire:loading wire:target="disconnectStrava" class="animate-spin ml-2 -mr-1 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    </button>
                                 </div>
                            @else
                                 {{-- Brand color consistent with Strava --}}
                                <a href="{{ route('strava.redirect') }}" class="inline-flex items-center px-4 py-2 bg-[#FC4C02] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[#FC4C02] focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                     <svg class="h-4 w-4 mr-1.5" fill="currentColor" viewBox="0 0 24 24"><path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h3.919L10.156 0l-5.168 10.201h3.919z"></path></svg>
                                     Connect with Strava
                                </a>
                            @endif
                         </div>
                     </div>

                     {{-- Divider --}}
                     <div class="border-t border-gray-200 dark:border-gray-700"></div>

                      {{-- Section 2: Performance Metrics --}}
                     <div class="md:grid md:grid-cols-3 md:gap-6 px-4 py-5 sm:p-6">
                          <div class="md:col-span-1">
                              <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">Performance Metrics</h3>
                              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                  Enter your current metrics. These are used to estimate power, energy, and personalize nutrition calculations.
                              </p>
                          </div>
                          <div class="mt-5 md:mt-0 md:col-span-2 space-y-4">
                               {{-- Weight --}}
                               <div>
                                  <x-input-label for="weight_kg" value="{{ __('Weight (kg)') }}" />
                                  <x-text-input id="weight_kg" type="number" step="0.1" class="mt-1 block w-full sm:w-1/2"
                                         wire:model.defer="weight_kg" />
                                  <x-input-error :messages="$errors->get('weight_kg')" class="mt-2" />
                              </div>
                               {{-- FTP --}}
                              <div>
                                  <x-input-label for="ftp_watts" value="{{ __('FTP (Watts)') }}" />
                                  <x-text-input id="ftp_watts" type="number" step="1" class="mt-1 block w-full sm:w-1/2"
                                         wire:model.defer="ftp_watts" />
                                  <x-input-error :messages="$errors->get('ftp_watts')" class="mt-2" />
                              </div>
                          </div>
                      </div>

                     {{-- Divider --}}
                     <div class="border-t border-gray-200 dark:border-gray-700"></div>

                     {{-- Section 3: Nutrition/Hydration Profile --}}
                      <div class="md:grid md:grid-cols-3 md:gap-6 px-4 py-5 sm:p-6">
                          <div class="md:col-span-1">
                              <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">Nutrition Profile</h3>
                              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                  Help personalize fluid and electrolyte recommendations based on your typical sweat and salt loss.
                              </p>
                          </div>
                          <div class="mt-5 md:mt-0 md:col-span-2 space-y-4">
                               {{-- Sweat Level --}}
                                <div>
                                    <x-input-label for="sweat_level" value="{{ __('Typical Sweat Level') }}" />
                                    <select id="sweat_level" wire:model.defer="sweat_level" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:outline-none focus:ring-indigo-500 dark:focus:ring-indigo-600 focus:border-indigo-500 dark:focus:border-indigo-600 sm:text-sm rounded-md shadow-sm">
                                        <option value="" disabled>{{ __('-- Select --') }}</option>
                                        <option value="light">{{ __('Light (minimal sweat)') }}</option>
                                        <option value="average">{{ __('Average') }}</option>
                                        <option value="heavy">{{ __('Heavy (drenched)') }}</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('sweat_level')" class="mt-2" />
                                </div>
                                {{-- Salt Loss Level --}}
                                <div>
                                    <x-input-label for="salt_loss_level" value="{{ __('Perceived Salt Loss') }}" />
                                     <select id="salt_loss_level" wire:model.defer="salt_loss_level" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:outline-none focus:ring-indigo-500 dark:focus:ring-indigo-600 focus:border-indigo-500 dark:focus:border-indigo-600 sm:text-sm rounded-md shadow-sm">
                                         <option value="" disabled>{{ __('-- Select --') }}</option>
                                         <option value="low">{{ __('Low (Rarely see salt crust on gear/skin)') }}</option>
                                        <option value="average">{{ __('Average (Sometimes see salt crust)') }}</option>
                                        <option value="high">{{ __('High (Often see white salt stains)') }}</option>
                                     </select>
                                     <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Do you often notice white salt crust/stains on your kit or skin after long/hot sessions?</p>
                                     <x-input-error :messages="$errors->get('salt_loss_level')" class="mt-2" />
                                </div>
                          </div>
                      </div>

                    {{-- Form Actions Footer --}}
                    <div class="flex items-center justify-end gap-4 px-4 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                        {{-- Action Message (e.g., "Saved.") -- Optional, could use session flash --}}
                        <span wire:loading.remove wire:dirty.remove class="text-sm text-gray-600 dark:text-gray-400"></span> {{-- Placeholder for when not saved --}}
                        <span wire:dirty wire:loading.remove class="text-sm text-gray-600 dark:text-gray-400">Unsaved changes.</span>
                        <span wire:loading wire:target="save" class="text-sm text-gray-600 dark:text-gray-400">Saving...</span>

                        <button type="submit" {{-- Changed from wire:click to standard submit --}}
                                class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-slate-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-slate-500 active:bg-gray-900 dark:active:bg-slate-700 focus:outline-none focus:border-gray-900 dark:focus:border-slate-300 focus:ring ring-gray-300 dark:focus:ring-slate-700 disabled:opacity-50 transition ease-in-out duration-150"
                                wire:loading.attr="disabled">
                            {{ __('Save Profile') }}
                             <svg wire:loading wire:target="save" class="animate-spin ml-2 -mr-1 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </button>
                    </div>
                </form>

            </div> {{-- End main Card --}}
        </div> {{-- End max-w-7xl --}}
    </div> {{-- End py-12 --}}
</div>
