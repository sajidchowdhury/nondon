<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Receive Transfer') }} {{ $transfer->request_number }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $transfer->fromBranch?->name }} → {{ $transfer->toBranch?->name }}
            </p>
        </div>
        <a href="{{ route('transfers.show', $transfer) }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700 mt-4 sm:mt-0">{{ __('Back to transfer') }}</a>
    </div>

    @if($scanError)
    <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900 p-3">
        <p class="text-sm text-red-800 dark:text-red-200">{{ $scanError }}</p>
    </div>
    @endif

    {{-- Mode toggle --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="flex space-x-4">
            <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-300">
                <input type="radio" wire:model.live="mode" value="scan" class="text-emerald-600 focus:ring-emerald-500 mr-2">
                {{ __('Scan mode') }}
            </label>
            <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-300">
                <input type="radio" wire:model.live="mode" value="quantity" class="text-emerald-600 focus:ring-emerald-500 mr-2">
                {{ __('Quantity mode') }}
            </label>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left: scan input or quantity entry --}}
        <div class="space-y-4">
            @if($mode === 'scan')
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Scan garment barcode') }}</label>
                <div class="flex space-x-2">
                    <input wire:keydown.enter="scanBarcode" wire:model="scanInput" type="text" autofocus placeholder="NND-25-00001"
                           class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                    <button wire:click="scanBarcode" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md">+</button>
                </div>
            </div>
            @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Enter received quantities') }}</h3>
                @php
                    $groups = $expectedItems->groupBy(function ($item) {
                        return ($item->product?->name ?? 'product') . ' / ' . ($item->productSize?->size_label ?? 'no-size');
                    });
                @endphp
                @foreach($groups as $key => $items)
                <div class="mb-3">
                    <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">{{ $key }} ({{ __('dispatched') }}: {{ $items->count() }})</label>
                    <input wire:model="quantityInputs.{{ $key }}" type="number" min="0" max="{{ $items->count() }}" placeholder="0"
                           class="block w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                </div>
                @endforeach
                <button wire:click="applyQuantities" class="mt-2 text-sm text-emerald-600 hover:text-emerald-800">{{ __('Apply quantities') }}</button>
            </div>
            @endif
        </div>

        {{-- Right: received / missing summary --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">{{ __('Receiving summary') }}</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('Expected') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $expectedItems->count() }}</span>
                </div>
                <div class="flex justify-between text-green-600 dark:text-green-400">
                    <span>{{ __('Received') }}</span>
                    <span class="font-medium">{{ $receivedCount }}</span>
                </div>
                @if($missingCount > 0)
                <div class="flex justify-between text-orange-600 dark:text-orange-400">
                    <span>{{ __('Missing') }}</span>
                    <span class="font-medium">{{ $missingCount }}</span>
                </div>
                @endif
                @if($excessCount > 0)
                <div class="flex justify-between text-red-600 dark:text-red-400">
                    <span>{{ __('Excess') }}</span>
                    <span class="font-medium">{{ $excessCount }}</span>
                </div>
                @endif
            </div>

            @if($hasDiscrepancy)
            <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Discrepancy reason') }} <span class="text-red-500">*</span></label>
                <textarea wire:model="discrepancyReason" rows="2" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white"></textarea>
                <p class="mt-1 text-xs text-gray-500">{{ __('Required when there are missing or excess garments.') }}</p>
            </div>
            @endif

            {{-- List of confirmed-received garment ids (for transparency) --}}
            @if(! empty($receivedGarmentIds))
            <div class="mt-3 border-t border-gray-200 dark:border-gray-700 pt-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Confirmed received garments') }} ({{ count($receivedGarmentIds) }})</p>
                <ul class="max-h-32 overflow-y-auto space-y-0.5">
                    @foreach($receivedGarmentIds as $gid)
                    <li class="text-xs text-gray-700 dark:text-gray-300">#{{ $gid }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>

    {{-- Dispatched items table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mt-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Garment') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Product') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($expectedItems as $item)
                    @php
                        $isReceived = in_array($item->garment_id, $receivedGarmentIds);
                    @endphp
                    <tr class="{{ $isReceived ? 'bg-green-50 dark:bg-green-900' : '' }}">
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item->garment?->barcode ?? '#' . $item->garment_id }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item->product?->name }}@if($item->productSize?->size_label) ({{ $item->productSize->size_label }})@endif</td>
                        <td class="px-6 py-4 text-sm text-center">
                            @if($isReceived)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Received') }}</span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">{{ __('Not received') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Confirm button --}}
    <div class="flex justify-end mt-6">
        <button wire:click="confirmReceipt" wire:loading.attr="disabled"
                class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
            <span wire:loading.remove>{{ __('Confirm Receipt') }}</span>
            <span wire:loading>{{ __('Processing...') }}</span>
        </button>
    </div>
</div>
