<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Garments') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Individual garment inventory tracking.') }}</p>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Search') }}</label>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Barcode or serial') }}"
                       class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Branch') }}</label>
                <select wire:model.live="branchFilter" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Branches') }}</option>
                    @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->code }} — {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Status') }}</label>
                <select wire:model.live="statusFilter" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach($statuses as $status)
                    <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Product') }}</label>
                <select wire:model.live="productFilter" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Products') }}</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if($search || $branchFilter || $statusFilter || $productFilter)
        <div class="mt-3"><button wire:click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear filters') }}</button></div>
        @endif
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Barcode') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Serial') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Product') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Size') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Branch') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($garments as $garment)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ $garment->barcode }}</td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-500 dark:text-gray-400">{{ $garment->fiscal_year_serial }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $garment->product?->name ?? __('N/A') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $garment->productSize?->size_label ?? __('N/A') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $garment->currentBranch?->code ?? __('N/A') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                @switch($garment->current_status)
                                    @case('available') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                    @case('reserved') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                    @case('sold') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @break
                                    @case('damaged') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                    @case('missing') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 @break
                                    @default bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                @endswitch
                            ">{{ $garment->status_label }}</span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-medium">
                            <a href="{{ route('garments.show', $garment) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900">{{ __('View') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No garments found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($garments->hasPages())
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">{{ $garments->links() }}</div>
        @endif
    </div>
</div>
