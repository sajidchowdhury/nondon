<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Add Customer') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Create a new customer record. Phone is mandatory; name is optional.') }}</p>
    </div>

    <form wire:submit="create" class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Phone') }} <span class="text-red-500">*</span></label>
                    <input wire:model.blur="phone" type="text" maxlength="50" placeholder="017xxxxxxxx"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Name') }}</label>
                    <input wire:model.blur="name" type="text" maxlength="255"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Address') }}</label>
                    <textarea wire:model.blur="address" rows="2" maxlength="1000"
                              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"></textarea>
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Duplicate-offer banner: when the service detects an existing
             customer with the entered phone, it returns the existing id so
             the salesperson can reuse it instead of forcing a duplicate. --}}
        @if($existingCustomerId)
        @php
            $existing = \App\Modules\Customers\Models\Customer::find($existingCustomerId);
        @endphp
        @if($existing)
        <div class="bg-amber-50 dark:bg-amber-900 border-l-4 border-amber-400 p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ __('A customer with this phone already exists.') }}</p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">{{ $existing->display_name }}</p>
                </div>
                <a href="{{ route('customers.show', $existing) }}" wire:navigate
                   class="ml-4 inline-flex items-center px-3 py-1.5 border border-amber-400 text-amber-800 dark:text-amber-200 text-sm font-medium rounded-md hover:bg-amber-100 dark:hover:bg-amber-800">
                    {{ __('View existing') }}
                </a>
            </div>
        </div>
        @endif
        @endif

        <div class="flex justify-end space-x-3">
            <a href="{{ route('customers.index') }}" wire:navigate
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </a>
            <button type="submit" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Create Customer') }}</span>
                <span wire:loading>{{ __('Saving...') }}</span>
            </button>
        </div>
    </form>
</div>
