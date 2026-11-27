<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Goods Receipt') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $receipt->receipt_number }}</p>
        </div>
        <a href="{{ route('goods-receipts.index') }}" wire:navigate
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
            {{ __('Back to Receipts') }}
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
    </div>
    @endif

    {{-- Receipt header --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Receipt Number') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $receipt->receipt_number }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Purchase') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    <a href="{{ route('purchases.show', $receipt->purchase) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900">
                        {{ $receipt->purchase?->purchase_number }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Supplier') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $receipt->purchase?->supplier?->name ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Received Date') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $receipt->received_date->format('M d, Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Received By') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $receipt->receiver?->display_name ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total Received') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $receipt->total_received_quantity }} {{ __('garments') }}</dd>
            </div>
            @if($receipt->notes)
            <div class="md:col-span-4">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Notes') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ $receipt->notes }}</dd>
            </div>
            @endif
        </dl>
    </div>

    {{-- Generated garments --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Generated Garment Identities') }}</h3>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $receipt->garments->count() }} {{ __('garments') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Barcode') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Serial') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Product') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Size') }}</th>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cost') }}</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($receipt->garments as $garment)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-white">{{ $garment->barcode }}</td>
                        <td class="px-6 py-4 text-sm font-mono text-gray-500 dark:text-gray-400">{{ $garment->fiscal_year_serial }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $garment->product?->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $garment->productSize?->size_label ?? __('N/A') }}</td>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ number_format((float) $garment->getRawOriginal('purchase_cost'), 2) }}</td>
                        @endif
                        <td class="px-6 py-4 text-sm">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Available') }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Print labels placeholder (Session 2.7) --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mt-6">
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Print Barcode Labels') }}</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Label printing will be available in Session 2.7.') }}</p>
    </div>
</div>
