<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Create Fiscal Year') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Fiscal years run from July 1 to June 30. Enter the start year to create a new fiscal year.') }}</p>
    </div>
    <div class="mb-4">
        <a href="{{ route('fiscal-years.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Fiscal Years') }}</a>
    </div>

    <form wire:submit="create" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6 max-w-2xl">
        {{-- Start year input --}}
        <div>
            <label for="startYear" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Start Year') }} <span class="text-red-500">*</span>
            </label>
            <input
                id="startYear"
                wire:model.live="startYear"
                type="number"
                min="2020"
                max="2100"
                placeholder="e.g., 2025"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
            >
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Enter the year that starts on July 1. For FY 2025-26, enter 2025.') }}</p>
            @error('startYear') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            @error('start_year') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Preview --}}
        @if($previewName)
        <div class="bg-blue-50 dark:bg-blue-900 rounded-md p-4 space-y-2">
            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ __('Preview') }}</h4>
            <div class="space-y-1 text-sm text-blue-700 dark:text-blue-300">
                <p><strong>{{ __('Name') }}:</strong> {{ $previewName }}</p>
                <p><strong>{{ __('Serial Prefix') }}:</strong> {{ $previewPrefix }}</p>
                <p><strong>{{ __('Date Range') }}:</strong> {{ $previewDateRange }}</p>
                <p class="text-xs mt-2">{{ __('The first garment serial will be') }} <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{ $previewPrefix }}-00001</code></p>
            </div>
        </div>
        @endif

        {{-- Info panel --}}
        <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-4">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('How Fiscal Year Serials Work') }}</h4>
            <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                <li>- Serial format: YY-NNNNN (e.g., 25-00001)</li>
                <li>- Global sequence per fiscal year — not per product</li>
                <li>- Each physical garment gets the next number</li>
                <li>- Serials restart at 00001 in each new fiscal year</li>
                <li>- Unsold stock carries forward with original identity</li>
            </ul>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('fiscal-years.index') }}" wire:navigate
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </a>
            <button type="submit" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Create Fiscal Year') }}</span>
                <span wire:loading>{{ __('Creating...') }}</span>
            </button>
        </div>
    </form>
</div>
