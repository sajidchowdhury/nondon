<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Goods Receipts') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Goods receiving history and generated garment identities.') }}</p>
    </div>

    {{-- Search --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Search by receipt number or purchase number') }}"
               class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
        @if($search)
        <div class="mt-2"><button wire:click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear search') }}</button></div>
        @endif
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Receipt Number') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Purchase') }}</th>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Supplier') }}</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Date') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Received By') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Qty') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($receipts as $receipt)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $receipt->receipt_number }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $receipt->purchase?->purchase_number }}</td>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $receipt->purchase?->supplier?->name ?? __('N/A') }}</td>
                        @endif
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $receipt->received_date->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $receipt->receiver?->display_name ?? __('N/A') }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ $receipt->total_received_quantity }}</td>
                        <td class="px-6 py-4 text-right text-sm font-medium">
                            <a href="{{ route('goods-receipts.show', $receipt) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900">{{ __('View') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No goods receipts found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($receipts->hasPages())
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">{{ $receipts->links() }}</div>
        @endif
    </div>
</div>
