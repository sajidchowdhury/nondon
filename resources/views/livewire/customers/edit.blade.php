<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit Customer') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $customer->display_name }}</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Phone') }} <span class="text-red-500">*</span></label>
                    <input wire:model.blur="phone" type="text" maxlength="50"
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

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <a href="{{ route('customers.show', $customer) }}" wire:navigate
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </a>
            <div class="flex space-x-3">
                @if($customer->is_active)
                <button type="button" wire:click="deactivate" wire:loading.attr="disabled" wire:confirm="{{ __('Deactivate this customer? They will be excluded from POS lookup.') }}"
                        class="px-4 py-2 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 text-sm font-medium rounded-md shadow-sm hover:bg-red-50 dark:hover:bg-red-900 disabled:opacity-50">
                    <span wire:loading.remove wire:target="deactivate">{{ __('Deactivate') }}</span>
                    <span wire:loading wire:target="deactivate">{{ __('Working...') }}</span>
                </button>
                @endif
                <button type="submit" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                    <span wire:loading.remove>{{ __('Save Changes') }}</span>
                    <span wire:loading>{{ __('Saving...') }}</span>
                </button>
            </div>
        </div>
    </form>
</div>
