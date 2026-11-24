<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Garment Lookup') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Quick lookup by barcode or serial number.') }}</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 max-w-2xl">
        <form wire:submit="lookup" class="space-y-4">
            <div class="flex items-center space-x-4">
                <label class="flex items-center">
                    <input type="radio" wire:model="searchType" value="barcode" class="text-emerald-600 focus:ring-emerald-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Barcode') }}</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" wire:model="searchType" value="serial" class="text-emerald-600 focus:ring-emerald-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Serial') }}</span>
                </label>
            </div>
            <div class="flex space-x-2">
                <input wire:model="searchTerm" type="text" placeholder="{{ $searchType === 'barcode' ? __('Enter barcode (e.g., NND-25-00001)') : __('Enter serial (e.g., 25-00001)') }}"
                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm font-mono"
                       wire:keydown.enter.prevent="lookup">
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                    {{ __('Lookup') }}
                </button>
            </div>
            @error('searchTerm') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </form>

        @if($garment)
        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
            <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <span class="text-sm font-mono text-gray-900 dark:text-white">{{ $garment->barcode }}</span>
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            @switch($garment->current_status)
                                @case('available') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                @case('reserved') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                @case('sold') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @break
                                @default bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                            @endswitch
                        ">{{ $garment->status_label }}</span>
                    </div>
                    <a href="{{ route('garments.show', $garment) }}" wire:navigate class="text-sm text-emerald-600 hover:text-emerald-900">{{ __('Full Details') }}</a>
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Product') }}</dt><dd class="text-gray-900 dark:text-white">{{ $garment->product?->name }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Size') }}</dt><dd class="text-gray-900 dark:text-white">{{ $garment->productSize?->size_label ?? __('N/A') }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Branch') }}</dt><dd class="text-gray-900 dark:text-white">{{ $garment->currentBranch?->code ?? __('N/A') }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">{{ __('Serial') }}</dt><dd class="text-gray-900 dark:text-white font-mono">{{ $garment->fiscal_year_serial }}</dd></div>
                </dl>
            </div>
        </div>
        @endif
    </div>
</div>
