<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Purchase Details') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $purchase->purchase_number }}</p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            @can('update', $purchase)
            <a href="{{ route('purchases.edit', $purchase) }}" wire:navigate
               class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">
                {{ __('Edit') }}
            </a>
            @endcan
            @can('receive', $purchase)
            <a href="{{ route('purchases.receive', $purchase) }}" wire:navigate
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm">
                {{ __('Receive Goods') }}
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
    </div>
    @endif

    {{-- Status badge --}}
    <div class="mb-6">
        @switch($purchase->status)
            @case('draft') <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Draft') }}</span> @break
            @case('confirmed') <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ __('Confirmed') }}</span> @break
            @case('partially_received') <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">{{ __('Partially Received') }}</span> @break
            @case('received') <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Received') }}</span> @break
            @case('cancelled') <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Cancelled') }}</span> @break
        @endswitch
    </div>

    {{-- Header info --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Supplier') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $purchase->supplier?->name ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Fiscal Year') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $purchase->fiscalYear?->name ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Purchase Date') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $purchase->purchase_date->format('M d, Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Expected Delivery') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $purchase->expected_delivery_date?->format('M d, Y') ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Created By') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $purchase->creator?->display_name ?? __('N/A') }}</dd>
            </div>
            @if($purchase->confirmed_at)
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Confirmed By') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $purchase->confirmer?->display_name ?? __('N/A') }}</dd>
            </div>
            @endif
            @if(auth()->user()?->hasRole('main_branch_admin'))
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total Cost') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ number_format((float) $purchase->total_cost, 2) }}</dd>
            </div>
            @endif
            @if($purchase->notes)
            <div class="md:col-span-3">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Notes') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ $purchase->notes }}</dd>
            </div>
            @endif
        </dl>
    </div>

    {{-- Line items --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Line Items') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Product') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Size') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ordered') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Received') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Remaining') }}</th>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Unit Cost') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Line Total') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($purchase->items as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item->product?->code }} — {{ $item->product?->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $item->productSize?->size_label ?? __('N/A') }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ $item->ordered_quantity }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ $item->received_quantity }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-500 dark:text-gray-400">{{ $item->remainingQuantity() }}</td>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ number_format((float) $item->unit_cost, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ $item->line_total }}</td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
                @if(auth()->user()?->hasRole('main_branch_admin'))
                <tfoot class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <td colspan="6" class="px-6 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Grand Total') }}</td>
                        <td class="px-6 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">{{ number_format((float) $purchase->total_cost, 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Goods receipts --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mt-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Goods Receipts') }}</h3>
            @if($purchase->goodsReceipts->isNotEmpty())
            <a href="{{ route('goods-receipts.index') }}" wire:navigate class="text-sm text-emerald-600 hover:text-emerald-900">{{ __('View All') }}</a>
            @endif
        </div>
        @if($purchase->goodsReceipts->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Receipt Number') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Date') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Received By') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Qty') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($purchase->goodsReceipts as $receipt)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 text-sm font-medium text-emerald-600">
                            <a href="{{ route('goods-receipts.show', $receipt) }}" wire:navigate>{{ $receipt->receipt_number }}</a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $receipt->received_date->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $receipt->receiver?->display_name ?? __('N/A') }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ $receipt->total_received_quantity }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
            {{ __('No goods receipts yet.') }}
            @can('receive', $purchase)
            <br><a href="{{ route('purchases.receive', $purchase) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900">{{ __('Receive goods now') }}</a>
            @endcan
        </div>
        @endif
    </div>
</div>
