<div>
    @if (session()->has('message'))
        <div {{ $attributes->merge(['class' => 'p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-900/80 dark:text-green-300 border border-green-300 dark:border-green-700 shadow-sm']) }} role="alert">
            {{ session('message') }}
        </div>
    @elseif (session()->has('success')) {{-- Add other common types --}}
        <div {{ $attributes->merge(['class' => 'p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-900/80 dark:text-green-300 border border-green-300 dark:border-green-700 shadow-sm']) }} role="alert">
            {{ session('success') }}
        </div>
    @elseif (session()->has('error'))
        <div {{ $attributes->merge(['class' => 'p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-900/80 dark:text-red-300 border border-red-300 dark:border-red-700 shadow-sm']) }} role="alert">
            {{ session('error') }}
        </div>
    @elseif (session()->has('warning'))
        <div {{ $attributes->merge(['class' => 'p-4 mb-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg dark:bg-yellow-900/80 dark:text-yellow-300 border border-yellow-300 dark:border-yellow-700 shadow-sm']) }} role="alert">
            {{ session('warning') }}
        </div>
    @elseif (session()->has('info'))
        <div {{ $attributes->merge(['class' => 'p-4 mb-4 text-sm text-blue-700 bg-blue-100 rounded-lg dark:bg-blue-900/80 dark:text-blue-300 border border-blue-300 dark:border-blue-700 shadow-sm']) }} role="alert">
            {{ session('info') }}
        </div>
    @endif
</div>
