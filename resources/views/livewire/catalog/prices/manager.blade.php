<div>
    {{-- Current prices --}}
    <div class="mb-6">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">{{ __('Current Prices') }}</h4>
        @if($currentPrices->isNotEmpty())
        <div class="space-y-2">
            @foreach($currentPrices as $price)
            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                <div class="flex items-center space-x-4">
                    <div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $price->type_label }}</span>
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ __('Standard') }}: {{ number_format((float)$price->standard_price, 2) }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Minimum') }}: {{ number_format((float)$price->minimum_price, 2) }}
                        </div>
                    </div>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Effective from') }} {{ $price->effective_from->format('M d, Y') }}
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No prices set yet.') }}</p>
        @endif
    </div>

    {{-- Set new price form --}}
    @can('create', \App\Modules\Catalog\Models\ProductPrice::class)
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">{{ __('Set New Price') }}</h4>
        <form wire:submit="setPrice" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Applies To') }}</label>
                <select wire:model="product_size_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Sizes') }}</option>
                    @foreach($sizes as $size)
                    <option value="{{ $size->id }}">{{ __('Size :label', ['label' => $size->size_label]) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Standard Price') }} <span class="text-red-500">*</span></label>
                    <input wire:model="standard_price" type="number" step="0.01" min="0" placeholder="0.00" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('standard_price') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Minimum Price') }} <span class="text-red-500">*</span></label>
                    <input wire:model="minimum_price" type="number" step="0.01" min="0" placeholder="0.00" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('minimum_price') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900 rounded-md p-3">
                <p class="text-xs text-blue-700 dark:text-blue-300">{{ __('Setting a new price will close the previous price period. Historical invoices retain the price at sale time.') }}</p>
            </div>
            <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Set Price') }}</span>
                <span wire:loading>{{ __('Setting...') }}</span>
            </button>
        </form>
    </div>
    @endcan

    {{-- Price history --}}
    @if($priceHistory->isNotEmpty())
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-6">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">{{ __('Price History') }}</h4>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Applies To') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Standard') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Minimum') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('From') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('To') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Set By') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($priceHistory as $price)
                    <tr>
                        <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $price->type_label }}</td>
                        <td class="px-3 py-2 text-gray-900 dark:text-white">{{ number_format((float)$price->standard_price, 2) }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ number_format((float)$price->minimum_price, 2) }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $price->effective_from->format('M d, Y') }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $price->effective_to?->format('M d, Y') ?? __('Active') }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $price->setByUser?->display_name ?? __('System') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
