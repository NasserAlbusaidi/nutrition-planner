<div>
    {{-- Use the main layout's slot --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    {{-- Success Message --}}
                    @if (session()->has('message'))
                        <div class="mb-4 p-4 bg-green-100 text-green-700 border border-green-300 rounded">
                            {{ session('message') }}
                        </div>
                    @endif

                    <form wire:submit.prevent="save">
                        @csrf

                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Strava Connection</h3>
                            @if(Auth::user()->strava_user_id)
                                <p class="mt-1 text-sm text-green-600">
                                    Connected as Strava User ID: {{ Auth::user()->strava_user_id }}
                                </p>
                                <form action="{{ route('strava.disconnect') }}" method="POST" class="mt-2">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:border-red-700 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150">
                                        Disconnect Strava
                                    </button>
                                </form>
                            @else
                                <p class="mt-1 text-sm text-gray-600">
                                    Connect your Strava account to import routes for planning.
                                </p>
                                <a href="{{ route('strava.redirect') }}" class="mt-2 inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-500 active:bg-orange-700 focus:outline-none focus:border-orange-700 focus:ring ring-orange-300 disabled:opacity-25 transition ease-in-out duration-150">
                                    Connect with Strava
                                </a>
                            @endif
                        </div>

                        <div class="mb-4">
                            <label for="weight_kg" class="block font-medium text-sm text-gray-700">{{ __('Weight (kg)') }}</label>
                            <input id="weight_kg" type="number" step="0.1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                   wire:model.defer="weight_kg">
                            @error('weight_kg') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="ftp_watts" class="block font-medium text-sm text-gray-700">{{ __('FTP (Watts)') }}</label>
                            <input id="ftp_watts" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                   wire:model.defer="ftp_watts">
                            @error('ftp_watts') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="sweat_level" class="block font-medium text-sm text-gray-700">{{ __('Typical Sweat Level') }}</label>
                            <select id="sweat_level" wire:model.defer="sweat_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">-- Select --</option>
                                <option value="light">{{ __('Light') }}</option>
                                <option value="average">{{ __('Average') }}</option>
                                <option value="heavy">{{ __('Heavy') }}</option>
                            </select>
                            @error('sweat_level') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="salt_loss_level" class="block font-medium text-sm text-gray-700">{{ __('Perceived Salt Loss (Notice salt crust?)') }}</label>
                            <select id="salt_loss_level" wire:model.defer="salt_loss_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">-- Select --</option>
                                <option value="low">{{ __('Low (Rarely)') }}</option>
                                <option value="average">{{ __('Average (Sometimes)') }}</option>
                                <option value="high">{{ __('High (Often)') }}</option>
                            </select>
                            @error('salt_loss_level') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('Save Profile') }}
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
