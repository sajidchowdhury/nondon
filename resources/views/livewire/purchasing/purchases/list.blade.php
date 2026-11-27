<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Purchases') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Purchase orders and goods receiving.') }}</p>
        </div>
        @can('create', \App\Modules\Purchasing\Models\Purchase::class)
        <a href="{{ route('purchases.create') }}" wire:navigate
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">
            {{ __('Add Purchase') }}
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Search') }}</label>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Purchase number or supplier') }}"
                       class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Supplier') }}</label>
                <select wire:model.live="supplierFilter"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Suppliers') }}</option>
                    @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Status') }}</label>
                <select wire:model.live="statusFilter"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="confirmed">{{ __('Confirmed') }}</option>
                    <option value="partially_received">{{ __('Partially Received') }}</option>
                    <option value="received">{{ __('Received') }}</option>
                    <option value="cancelled">{{ __('Cancelled') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fiscal Year') }}</label>
                <select wire:model.live="fiscalYearFilter"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Years') }}</option>
                    @foreach($fiscalYears as $fy)
                    <option value="{{ $fy->id }}">{{ $fy->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if($search || $supplierFilter || $statusFilter || $fiscalYearFilter)
        <div class="mt-3"><button wire:click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear filters') }}</button></div>
        @endif
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Number') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Supplier') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Date') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Items') }}</th>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Total Cost') }}</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($purchases as $purchase)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $purchase->purchase_number }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $purchase->supplier?->name ?? __('N/A') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $purchase->purchase_date->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $purchase->items_count ?? $purchase->items->count() }}</td>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <td class="px-6 py-4 text-right text-sm text-gray-900 dark:text-white">{{ number_format((float) $purchase->total_cost, 2) }}</td>
                        @endif
                        <td class="px-6 py-4 text-sm">
                            @switch($purchase->status)
                                @case('draft')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Draft') }}</span>
                                    @break
                                @case('confirmed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ __('Confirmed') }}</span>
                                    @break
                                @case('partially_received')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">{{ __('Partially Received') }}</span>
                                    @break
                                @case('received')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Received') }}</span>
                                    @break
                                @case('cancelled')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Cancelled') }}</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-medium">
                            <a href="{{ route('purchases.show', $purchase) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900 mr-3">{{ __('View') }}</a>
                            @can('update', $purchase)
                            <a href="{{ route('purchases.edit', $purchase) }}" wire:navigate class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ auth()->user()?->hasRole('main_branch_admin') ? 7 : 6 }}" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No purchases found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchases->hasPages())
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">{{ $purchases->links() }}</div>
        @endif
    </div>
</div>
