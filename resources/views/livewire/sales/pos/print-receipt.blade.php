<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Print Receipt') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Invoice :number', ['number' => $invoice->invoice_number]) }}</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 max-w-md">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Receipt format') }}</label>
                <select wire:model.live="format" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                    @foreach($formats as $f)
                    <option value="{{ $f['key'] }}">{{ $f['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="text-sm text-gray-600 dark:text-gray-400">
                <p>{{ __('Total') }}: <span class="font-medium text-gray-900 dark:text-white">{{ $invoice->total_amount }}</span></p>
                <p>{{ __('Date') }}: <span class="font-medium text-gray-900 dark:text-white">{{ $invoice->completed_at?->format('Y-m-d H:i') }}</span></p>
            </div>

            <div class="flex flex-col gap-2 pt-2">
                {{-- Open the receipt route in a new tab — the controller renders the
                     receipt HTML with @page sizing; the browser print dialog fires. --}}
                <a href="{{ $printUrl }}" target="_blank" rel="noopener"
                   class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm text-center">
                    {{ __('Print') }}
                </a>
                <a href="{{ route('pos.index') }}" wire:navigate
                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 text-center hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Back to POS') }}
                </a>
            </div>
        </div>
    </div>
</div>
