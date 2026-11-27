<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit Purchase') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $purchase->purchase_number }}</p>
    </div>
    <div class="mb-4"><a href="{{ route('purchases.show', $purchase) }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Purchase') }}</a></div>

    @if(session('success'))
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
    </div>
    @endif

    @if(!$purchase->isDraft())
    <div class="mb-4 rounded-md bg-yellow-50 dark:bg-yellow-900 p-4">
        <p class="text-sm text-yellow-800 dark:text-yellow-200">{{ __('This purchase is :status and cannot be edited. Only draft purchases are editable.', ['status' => $purchase->status]) }}</p>
    </div>
    @endif

    <form wire:submit="update" class="space-y-6">
        {{-- Header section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Purchase Details') }}</h3>
                <div>
                    @switch($purchase->status)
                        @case('draft') <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Draft') }}</span> @break
                        @case('confirmed') <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ __('Confirmed') }}</span> @break
                        @case('received') <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Received') }}</span> @break
                        @case('cancelled') <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Cancelled') }}</span> @break
                    @endswitch
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Supplier') }} <span class="text-red-500">*</span></label>
                    <select wire:model="supplier_id" @disabled(!$purchase->isDraft())
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">{{ __('Select supplier...') }}</option>
                        @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Fiscal Year') }}</label>
                    <select wire:model="fiscal_year_id" disabled
                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm dark:bg-gray-700 dark:text-white opacity-60 cursor-not-allowed">
                        @foreach($fiscalYears as $fy)
                        <option value="{{ $fy->id }}">{{ $fy->name }}</option>
                        @endforeach
                    </select>
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

        @if($purchase->isDraft())
        {{-- Line items section (editable for draft) --}}
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
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Product') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Size') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Qty') }}</th>
                            @if(auth()->user()?->hasRole('main_branch_admin'))
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Unit Cost') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Line Total') }}</th>
                            @endif
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($items as $index => $item)
                        <tr>
                            <td class="px-3 py-2">
                                <select wire:model="items.{{ $index }}.product_id" class="block w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <select wire:model="items.{{ $index }}.product_size_id" class="block w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white">
                                    <option value="">{{ __('N/A') }}</option>
                                    @php $selectedProduct = collect($products)->firstWhere('id', (int) $item['product_id']); @endphp
                                    @if($selectedProduct)
                                    @foreach($selectedProduct->sizes->where('is_active', true) as $size)
                                    <option value="{{ $size->id }}">{{ $size->size_label }}</option>
                                    @endforeach
                                    @endif
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input wire:model="items.{{ $index }}.ordered_quantity" type="number" min="1" class="block w-20 px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-right dark:bg-gray-700 dark:text-white">
                            </td>
                            @if(auth()->user()?->hasRole('main_branch_admin'))
                            <td class="px-3 py-2">
                                <input wire:model="items.{{ $index }}.unit_cost" type="number" min="0" step="0.01" class="block w-24 px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-right dark:bg-gray-700 dark:text-white">
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
            <div class="mt-4 flex justify-end text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Grand Total') }}: <span class="ml-2 text-lg text-gray-900 dark:text-white">{{ $this->grandTotal }}</span>
            </div>
            @endif
        </div>
        @else
        {{-- Read-only line items for non-draft --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">{{ __('Line Items') }}</h3>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Product') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Size') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Ordered') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Received') }}</th>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Unit Cost') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($purchase->items as $item)
                    <tr>
                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">{{ $item->product?->name ?? __('N/A') }}</td>
                        <td class="px-3 py-2 text-sm text-gray-500">{{ $item->productSize?->size_label ?? __('N/A') }}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 dark:text-white">{{ $item->ordered_quantity }}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900 dark:text-white">{{ $item->received_quantity }}</td>
                        @if(auth()->user()?->hasRole('main_branch_admin'))
                        <td class="px-3 py-2 text-sm text-right text-gray-900 dark:text-white">{{ number_format((float) $item->unit_cost, 2) }}</td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Action buttons --}}
        <div class="flex flex-wrap justify-between gap-3">
            <div class="flex gap-3">
                @can('confirm', $purchase)
                <button type="button" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm"
                        onclick="return confirm('{{ __('Confirm this purchase? It cannot be edited after confirmation.') }}')"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                    {{ __('Confirm Purchase') }}
                </button>
                @endcan
                @can('cancel', $purchase)
                <button type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel"
                        onclick="return confirm('{{ __('Cancel this purchase? This action cannot be undone.') }}')"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                    {{ __('Cancel Purchase') }}
                </button>
                @endcan
            </div>
            <div class="flex gap-3">
                <a href="{{ route('purchases.show', $purchase) }}" wire:navigate
                   class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Back') }}
                </a>
                @can('update', $purchase)
                <button type="submit" wire:loading.attr="disabled" wire:target="update"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                    <span wire:loading.remove wire:target="update">{{ __('Save Changes') }}</span>
                    <span wire:loading wire:target="update">{{ __('Saving...') }}</span>
                </button>
                @endcan
            </div>
        </div>
    </form>
</div>
