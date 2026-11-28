<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Checkout') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Collect payment and complete the sale.') }}</p>
    </div>

    @if($completionSuccess)
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $completionSuccess }}</p>
        @if($completedInvoiceId)
        <div class="mt-3 flex flex-wrap gap-3">
            {{-- Print receipt (Session 3.5): opens the print-receipt page with a format selector + browser print. --}}
            <a href="{{ route('pos.print-receipt', ['invoiceId' => $completedInvoiceId]) }}" wire:navigate
               class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">
                {{ __('Print receipt') }}
            </a>
            <button wire:click="backToPos" class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('New sale') }}
            </button>
        </div>
        @endif
    </div>
    @endif

    @if($completionError)
    <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900 p-3">
        <p class="text-sm text-red-800 dark:text-red-200">{{ $completionError }}</p>
    </div>
    @endif

    @if($completedInvoiceId === null)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Customer section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Customer') }}</h2>

            <label class="flex items-center text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:model="walkIn" wire:change="toggleWalkIn" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500 mr-2" {{ $walkIn ? 'checked' : '' }}>
                {{ __('Walk-in (no customer)') }}
            </label>

            @if(! $walkIn)
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Phone') }} <span class="text-red-500">*</span></label>
                <div class="flex space-x-2">
                    <input wire:model="customerPhone" type="text" placeholder="017xxxxxxxx" class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                    <button wire:click="lookupCustomer" wire:loading.attr="disabled" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md disabled:opacity-50">
                        <span wire:loading.remove>{{ __('Look up') }}</span>
                        <span wire:loading>{{ __('...') }}</span>
                    </button>
                </div>
                @error('customerPhone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if($customerLookupDone && $foundCustomerName)
            <div class="rounded-md bg-green-50 dark:bg-green-900 p-3">
                <p class="text-sm text-green-800 dark:text-green-200">{{ __('Existing customer') }}: <span class="font-medium">{{ $foundCustomerName }}</span></p>
            </div>
            @elseif($customerLookupDone)
            <div class="rounded-md bg-blue-50 dark:bg-blue-900 p-3">
                <p class="text-sm text-blue-800 dark:text-blue-200">{{ __('No existing customer. A new walk-in will be created at completion.') }}</p>
            </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Name') }} ({{ __('optional') }})</label>
                <input wire:model="customerName" type="text" maxlength="255" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
            </div>
            @endif
        </div>

        {{-- Payment section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Payment') }}</h2>

            @foreach($payments as $index => $payment)
            <div class="border border-gray-200 dark:border-gray-700 rounded-md p-3 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Payment') }} {{ $index + 1 }}</span>
                    @if(count($payments) > 1)
                    <button wire:click="removePaymentMethod({{ $index }})" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400">{{ __('Remove') }}</button>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">{{ __('Method') }}</label>
                        <select wire:model="payments.{{ $index }}.method" class="block w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                            @foreach($paymentMethods as $pm)
                            <option value="{{ $pm->name }}">{{ $pm->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">{{ __('Amount') }}</label>
                        <input wire:model="payments.{{ $index }}.amount" type="number" step="0.01" min="0" placeholder="0.00" class="block w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-right focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
                @php
                    $pm = $paymentMethods->firstWhere('name', $payment['method']);
                    $needsRef = $pm && $pm->requires_reference;
                @endphp
                @if($needsRef)
                <div>
                    <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">{{ __('Reference') }} <span class="text-red-500">*</span></label>
                    <input wire:model="payments.{{ $index }}.reference" type="text" maxlength="100" placeholder="Txn ID" class="block w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                </div>
                @endif
            </div>
            @endforeach

            <button wire:click="addPaymentMethod" class="text-sm text-emerald-600 hover:text-emerald-800 dark:text-emerald-400">{{ __('+ Add payment method') }}</button>

            {{-- Summary --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-3 space-y-1 text-sm">
                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                    <span>{{ __('Sale total') }}</span><span>{{ $cartTotal }}</span>
                </div>
                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                    <span>{{ __('Total paid') }}</span><span>{{ $totalPaid }}</span>
                </div>
                @if($isShort)
                <div class="flex justify-between text-red-600 dark:text-red-400 font-medium">
                    <span>{{ __('Short by') }}</span><span>{{ $shortBy }}</span>
                </div>
                @else
                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                    <span>{{ __('Change due') }}</span><span>{{ $changeDue }}</span>
                </div>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Notes') }}</label>
                <textarea wire:model="notes" rows="2" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white"></textarea>
            </div>

            <div class="flex justify-between pt-2">
                <button wire:click="backToPos" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Back to POS') }}
                </button>
                <button wire:click="completeSale" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                    <span wire:loading.remove>{{ __('Complete Sale') }}</span>
                    <span wire:loading>{{ __('Completing...') }}</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
