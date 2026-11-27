<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Suppliers') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Supplier master data for purchasing.') }}</p>
        </div>
        @can('create', \App\Modules\Purchasing\Models\Supplier::class)
        <a href="{{ route('suppliers.create') }}" wire:navigate
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">
            {{ __('Add Supplier') }}
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Search') }}</label>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Code, name, or phone') }}"
                       class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Status') }}</label>
                <select wire:model.live="statusFilter"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </select>
            </div>
        </div>
        @if($search || $statusFilter)
        <div class="mt-3"><button wire:click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear filters') }}</button></div>
        @endif
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Code') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Name') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($suppliers as $supplier)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $supplier->code }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $supplier->name }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if($supplier->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Active') }}</span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-medium">
                            <a href="{{ route('suppliers.show', $supplier) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900 mr-3">{{ __('View') }}</a>
                            @can('update', $supplier)
                            <a href="{{ route('suppliers.edit', $supplier) }}" wire:navigate class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No suppliers found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($suppliers->hasPages())
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">{{ $suppliers->links() }}</div>
        @endif
    </div>
</div>
