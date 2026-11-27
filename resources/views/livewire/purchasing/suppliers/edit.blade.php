<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit Supplier') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $supplier->display_name }}</p>
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
    </div>
    @endif

    <form wire:submit="update" class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Supplier Code') }} <span class="text-red-500">*</span></label>
                    <input wire:model.blur="code" type="text" maxlength="50"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Supplier Name') }} <span class="text-red-500">*</span></label>
                    <input wire:model.blur="name" type="text" maxlength="255"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Contact Person') }}</label>
                    <input wire:model.blur="contact_person" type="text" maxlength="255"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('contact_person') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Phone') }}</label>
                    <input wire:model.blur="phone" type="text" maxlength="50"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Email') }}</label>
                    <input wire:model.blur="email" type="email" maxlength="255"
                           class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Address') }}</label>
                    <textarea wire:model.blur="address" rows="2" maxlength="1000"
                              class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"></textarea>
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Status') }}</h3>
            <div class="flex items-center gap-3">
                @if($supplier->is_active)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Active') }}</span>
                <button wire:click="deactivate" wire:loading.attr="disabled" onclick="return confirm('{{ __('Deactivate this supplier?') }}')"
                        class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">
                    {{ __('Deactivate') }}
                </button>
                @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Inactive') }}</span>
                <button wire:click="activate" wire:loading.attr="disabled"
                        class="px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-md disabled:opacity-50">
                    {{ __('Activate') }}
                </button>
                @endif
            </div>
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('suppliers.show', $supplier) }}" wire:navigate
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </a>
            <button type="submit" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Save Changes') }}</span>
                <span wire:loading>{{ __('Saving...') }}</span>
            </button>
        </div>
    </form>
</div>
