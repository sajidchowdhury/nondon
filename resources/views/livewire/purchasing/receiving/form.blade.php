<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Receive Goods') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $purchase->purchase_number }} — {{ $purchase->supplier?->name }}</p>
    </div>
    <div class="mb-4"><a href="{{ route('purchases.show', $purchase) }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Purchase') }}</a></div>

    @if(session('success'))
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
    </div>
    @endif

    {{-- Status check: only confirmed/partially_received can be received --}}
    @if(!$purchase->canBeReceived())
    <div class="mb-4 rounded-md bg-yellow-50 dark:bg-yellow-900 p-4">
        <p class="text-sm text-yellow-800 dark:text-yellow-200">
            {{ __('This purchase cannot be received. Current status: :status', ['status' => $purchase->status]) }}
        </p>
    </div>
    @endif

    <form wire:submit="receive">
        {{-- Purchase summary --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <dl class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Purchase Number') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $purchase->purchase_number }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Supplier') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $purchase->supplier?->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Purchase Date') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $purchase->purchase_date->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt>
                    <dd class="mt-1">
                        @switch($purchase->status)
                            @case('confirmed') <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ __('Confirmed') }}</span> @break
                            @case('partially_received') <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">{{ __('Partially Received') }}</span> @break
                            @case('received') <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Received') }}</span> @break
                        @endswitch
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Line items receiving table --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Line Items — Enter Received Quantities') }}</h3>
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
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Receive Now') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($purchase->items as $item)
                        @php
                            $remaining = $item->remainingQuantity();
                            $isFullyReceived = $remaining <= 0;
                        @endphp
                        <tr class="{{ $isFullyReceived ? 'opacity-50' : '' }}">
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item->product?->code }} — {{ $item->product?->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $item->productSize?->size_label ?? __('N/A') }}</td>
                            <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ $item->ordered_quantity }}</td>
                            <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ $item->received_quantity }}</td>
                            <td class="px-6 py-4 text-sm text-right {{ $remaining > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">{{ $remaining }}</td>
                            <td class="px-6 py-4 text-right">
                                <input type="number"
                                       min="0"
                                       max="{{ $remaining }}"
                                       wire:model.live="receivedQuantities.{{ $item->id }}"
                                       placeholder="0"
                                       @disabled($isFullyReceived || !$purchase->canBeReceived())
                                       class="block w-24 ml-auto px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-right focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                                @error("receivedQuantities.{{ $item->id }}") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Notes --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Receiving Notes') }}</label>
            <textarea wire:model="notes" rows="2" maxlength="2000"
                      class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
                      placeholder="{{ __('Optional notes about this receipt...') }}"></textarea>
        </div>

        @error('quantities')
        <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900 p-4">
            <p class="text-sm text-red-800 dark:text-red-200">{{ $message }}</p>
        </div>
        @enderror

        {{-- Action buttons --}}
        <div class="flex justify-end space-x-3">
            <a href="{{ route('purchases.show', $purchase) }}" wire:navigate
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </a>
            <button type="submit"
                    wire:loading.attr="disabled"
                    @disabled(!$purchase->canBeReceived())
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Receive & Generate Identities') }}</span>
                <span wire:loading>{{ __('Receiving...') }}</span>
            </button>
        </div>
    </form>
</div>
