<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Add Purchase') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Create a new draft purchase order.') }}</p>
    </div>
    <div class="mb-4"><a href="{{ route('purchases.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Purchases') }}</a></div>

    <form wire:submit="create" class="space-y-6">
        {{-- Header section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">{{ __('Purchase Details') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Supplier') }} <span class="text-red-500">*</span></label>
                    <select wire:model="supplier_id"
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">{{ __('Select supplier...') }}</option>
                        @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fiscal Year') }} <span class="text-red-500">*</span></label>
                    <select wire:model="fiscal_year_id"
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">{{ __('Select fiscal year...') }}</option>
                        @foreach($fiscalYears as $fy)
                        <option value="{{ $fy->id }}">{{ $fy->name }}</option>
                        @endforeach
                    </select>
                    @error('fiscal_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Purchase Date') }} <span class="text-red-500">*</span></label>
                    <input wire:model="purchase_date" type="date"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('purchase_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Expected Delivery') }}</label>
                    <input wire:model="expected_delivery_date" type="date"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Notes') }}</label>
                    <textarea wire:model="notes" rows="2" maxlength="2000"
                              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"></textarea>
                </div>
            </div>
        </div>

        {{-- Line items section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Line Items') }}</h3>
                <button type="button" wire:click="addItem"
                        class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                    + {{ __('Add Item') }}
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Product') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Size') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Qty') }}</th>
                            @if(auth()->user()?->hasRole('main_branch_admin'))
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Unit Cost') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Line Total') }}</th>
                            @endif
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($items as $index => $item)
                        <tr>
                            <td class="px-3 py-2">
                                <select wire:model="items.{{ $index }}.product_id"
                                        class="block w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>
                                    @endforeach
                                </select>
                                @error("items.{$index}.product_id") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-3 py-2">
                                <select wire:model="items.{{ $index }}.product_size_id"
                                        class="block w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">{{ __('N/A') }}</option>
                                    @php
                                        $selectedProduct = collect($products)->firstWhere('id', (int) $item['product_id']);
                                    @endphp
                                    @if($selectedProduct)
                                    @foreach($selectedProduct->sizes->where('is_active', true) as $size)
                                    <option value="{{ $size->id }}">{{ $size->size_label }}</option>
                                    @endforeach
                                    @endif
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input wire:model="items.{{ $index }}.ordered_quantity" type="number" min="1" step="1" placeholder="0"
                                       class="block w-20 px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-right focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                                @error("items.{$index}.ordered_quantity") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </td>
                            @if(auth()->user()?->hasRole('main_branch_admin'))
                            <td class="px-3 py-2">
                                <input wire:model="items.{{ $index }}.unit_cost" type="number" min="0" step="0.01" placeholder="0.00"
                                       class="block w-24 px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-right focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                                @error("items.{$index}.unit_cost") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-3 py-2 text-right text-sm text-gray-900 dark:text-white">
                                {{ number_format((float) ($item['ordered_quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0), 2) }}
                            </td>
                            @endif
                            <td class="px-3 py-2 text-right">
                                @if(count($items) > 1)
                                <button type="button" wire:click="removeItem({{ $index }})" class="text-red-500 hover:text-red-700 text-sm">&times;</button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(auth()->user()?->hasRole('main_branch_admin'))
            <div class="mt-4 flex justify-end">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Grand Total') }}: <span class="text-lg text-gray-900 dark:text-white">{{ $this->grandTotal }}</span>
                </div>
            </div>
            @endif
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('purchases.index') }}" wire:navigate
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </a>
            <button type="submit" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Save as Draft') }}</span>
                <span wire:loading>{{ __('Saving...') }}</span>
            </button>
        </div>
    </form>
</div>
