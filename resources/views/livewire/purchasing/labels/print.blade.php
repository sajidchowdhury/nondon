<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Print Labels') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Select garments by receipt or ID range, choose a label format, preview, then print.') }}
        </p>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-6">
        {{-- Selection mode --}}
        <div>
            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Select Garments') }}</span>
            <div class="mt-2 flex items-center space-x-6">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="radio" wire:model="selectionMode" value="receipt"
                           class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 dark:border-gray-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('By Goods Receipt') }}</span>
                </label>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="radio" wire:model="selectionMode" value="range"
                           class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 dark:border-gray-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('By Garment ID Range') }}</span>
                </label>
            </div>
        </div>

        @if($selectionMode === 'receipt')
            {{-- Receipt selector --}}
            <div>
                <label for="receipt" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Goods Receipt') }}
                </label>
                <select id="receipt" wire:model="receiptId"
                        class="mt-1 block w-full max-w-2xl px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">— {{ __('Select a receipt') }} —</option>
                    @foreach($receipts as $receipt)
                        <option value="{{ $receipt->id }}">
                            {{ $receipt->receipt_number }} — {{ $receipt->purchase?->purchase_number }} ({{ $receipt->garments_count }} {{ __('garments') }})
                        </option>
                    @endforeach
                </select>
                @error('receiptId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        @else
            {{-- Garment ID range --}}
            <div class="flex items-end space-x-4">
                <div>
                    <label for="fromGarmentId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('First Garment ID') }}
                    </label>
                    <input id="fromGarmentId" type="number" min="1" wire:model.number="fromGarmentId"
                           class="mt-1 block w-40 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                </div>
                <div>
                    <label for="toGarmentId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Last Garment ID') }}
                    </label>
                    <input id="toGarmentId" type="number" min="1" wire:model.number="toGarmentId"
                           class="mt-1 block w-40 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                </div>
            </div>
        @endif

        {{-- Format + price visibility --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="format" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Label Format') }}
                </label>
                <select id="format" wire:model="format"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @foreach($formats as $formatOption)
                        <option value="{{ $formatOption['key'] }}">{{ $formatOption['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end pb-2">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="showPrice"
                           class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 dark:border-gray-600 rounded">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Show price on label') }}</span>
                </label>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" wire:click="loadPreview"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                {{ __('Preview') }}
            </button>
            <a href="{{ $this->printSheetUrl() }}" target="_blank" rel="noopener"
               class="px-4 py-2 bg-gray-800 hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-gray-500">
                {{ __('Print') }}
            </a>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('Print opens a new tab with correctly sized label pages and the browser print dialog.') }}
            </span>
        </div>
        @error('preview') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    {{-- Inline preview --}}
    @if($previewLabels !== null)
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Label Preview') }}</h2>

            @if($previewNote)
                <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">{{ $previewNote }}</p>
            @endif

            @if(count($previewLabels) === 0)
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('No garments found for this selection.') }}</p>
            @else
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($previewLabels as $label)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-md p-3 flex flex-col items-center gap-1">
                            <img src="{{ $label['barcode_image'] }}" alt="Barcode {{ $label['barcode'] }}"
                                 class="w-full object-contain" style="height: {{ $label['format']['barcode_height_px'] }}px;">
                            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $label['serial'] }}</div>
                            <div class="text-sm text-center text-gray-700 dark:text-gray-300">{{ $label['product_name'] }}</div>
                            @if($label['size_label'])
                                <div class="text-xs font-semibold text-gray-900 dark:text-white">{{ $label['size_label'] }}</div>
                            @endif
                            @if(array_key_exists('standard_price', $label) && $label['standard_price'] !== null)
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ number_format((float) $label['standard_price'], 2) }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
