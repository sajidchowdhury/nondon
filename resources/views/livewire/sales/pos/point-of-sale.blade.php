<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Point of Sale') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                @if($branchName)
                {{ __('Branch: :name', ['name' => $branchName]) }}
                @else
                {{ __('No active branch assigned.') }}
                @endif
            </p>
        </div>
    </div>

    {{-- Scan / search success / error flashes --}}
    @if($scanSuccess)
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-3">
        <p class="text-sm text-green-800 dark:text-green-200">{{ $scanSuccess }}</p>
    </div>
    @endif
    @if($scanError)
    <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900 p-3">
        <p class="text-sm text-red-800 dark:text-red-200">{{ $scanError }}</p>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left: scan + search --}}
        <div class="space-y-6">
            {{-- Barcode / serial scan --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Scan barcode or serial') }}</label>
                <div class="flex space-x-2">
                    <input
                        wire:model="scanInput"
                        wire:keydown.enter="scanBarcode"
                        type="text"
                        autofocus
                        placeholder="{{ __('e.g. NND-25-00001 or 25-00001') }}"
                        class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
                    >
                    <button
                        wire:click="scanBarcode"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50"
                    >
                        <span wire:loading.remove>{{ __('Add') }}</span>
                        <span wire:loading>{{ __('...') }}</span>
                    </button>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Hardware scanners that type + Enter are supported.') }}</p>
            </div>

            {{-- Product / garment search --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Search by product or barcode') }}</label>
                <input
                    wire:model.live.debounce.300ms="searchTerm"
                    wire:input.debounce.300ms="searchProducts"
                    type="text"
                    placeholder="{{ __('Type a product name, code, or barcode...') }}"
                    class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
                >
                @if(! empty($searchResults))
                <div class="mt-3 border border-gray-200 dark:border-gray-700 rounded-md max-h-72 overflow-y-auto">
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($searchResults as $row)
                        <li>
                            <button
                                type="button"
                                wire:click="addByGarmentId({{ $row['garment_id'] }})"
                                class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center justify-between"
                            >
                                <span class="text-sm text-gray-900 dark:text-white">
                                    {{ $row['product_name'] }}
                                    @if($row['size_label'])
                                    <span class="text-gray-500 dark:text-gray-400">— {{ $row['size_label'] }}</span>
                                    @endif
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['barcode'] }} · {{ $row['price'] }}</span>
                            </button>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </div>

        {{-- Right: cart --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Cart') }}
                    <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">({{ $itemCount }})</span>
                </h2>
                @if($itemCount > 0)
                <button wire:click="clearCart" wire:confirm="{{ __('Clear the entire cart?') }}"
                        class="text-sm text-red-600 hover:text-red-800 dark:text-red-400">{{ __('Clear cart') }}</button>
                @endif
            </div>

            @if(empty($cartItems))
            <p class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">{{ __('Scan a barcode or search to add items.') }}</p>
            @else
            <div class="overflow-x-auto flex-1">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Item') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Price') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Discount') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Final') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($cartItems as $row)
                        @php
                            $atMin = $row['at_or_below_minimum'];
                        @endphp
                        <tr class="{{ $atMin ? 'bg-amber-50 dark:bg-amber-900' : '' }}">
                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                <div class="font-medium">{{ $row['product_name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    @if($row['size_label']){{ $row['size_label'] }} · @endif{{ $row['barcode'] }}
                                    @if($row['is_clearance'])
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">{{ __('clearance') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 text-sm text-right text-gray-900 dark:text-white">{{ $row['standard_price'] }}</td>
                            <td class="px-3 py-2 text-sm text-right">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model="discountInputs.{{ $row['garment_id'] }}"
                                    wire:blur="applyDiscount({{ $row['garment_id'] }})"
                                    placeholder="0.00"
                                    class="w-24 px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-right text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white"
                                >
                                @error("discount.{$row['garment_id']}")
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="px-3 py-2 text-sm text-right font-medium {{ $atMin ? 'text-amber-700 dark:text-amber-300' : 'text-gray-900 dark:text-white' }}">{{ $row['final_price'] }}</td>
                            <td class="px-3 py-2 text-right text-sm">
                                <button wire:click="removeFromCart({{ $row['garment_id'] }})"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 ml-2">{{ __('Remove') }}</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Totals --}}
            <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-3 space-y-1 text-sm">
                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                    <span>{{ __('Subtotal') }}</span><span>{{ $subtotal }}</span>
                </div>
                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                    <span>{{ __('Total discount') }}</span><span>{{ $totalDiscount }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold text-gray-900 dark:text-white pt-1">
                    <span>{{ __('Total') }}</span><span>{{ $totalAmount }}</span>
                </div>
            </div>

            {{-- Proceed to checkout (wired in Session 3.4) --}}
            <button
                wire:click="proceedToCheckout"
                wire:loading.attr="disabled"
                class="mt-4 w-full px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50"
            >
                <span wire:loading.remove>{{ __('Proceed to Checkout') }}</span>
                <span wire:loading>{{ __('...') }}</span>
            </button>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center">{{ __('Checkout is wired up in Session 3.4.') }}</p>
            @endif
        </div>
    </div>
</div>
