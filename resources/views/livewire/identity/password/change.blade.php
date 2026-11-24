<div>
    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Change Password') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Change your password. You will need to enter your current password for verification.') }}</p>
    </div>

    {{-- Form --}}
    <form wire:submit="change" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6 max-w-2xl">
        {{-- Current password --}}
        <div>
            <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Current Password') }} <span class="text-red-500">*</span>
            </label>
            <input
                id="current_password"
                wire:model="current_password"
                type="password"
                autocomplete="current-password"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
            >
            @error('current_password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Divider --}}
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">{{ __('New Password') }}</h3>
        </div>

        {{-- New password --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('New Password') }} <span class="text-red-500">*</span>
                </label>
                <input
                    id="password"
                    wire:model="password"
                    type="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
                >
                @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Confirm Password') }} <span class="text-red-500">*</span>
                </label>
                <input
                    id="password_confirmation"
                    wire:model="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
                >
                @error('password_confirmation') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Password requirements --}}
        <div class="bg-blue-50 dark:bg-blue-900 rounded-md p-4">
            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">{{ __('Password Requirements') }}</h4>
            <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                <li>- {{ __('Minimum 8 characters') }}</li>
                <li>- {{ __('Must contain at least one letter and one number') }}</li>
                <li>- {{ __('Cannot be the same as your current password') }}</li>
                <li>- {{ __('Cannot be a commonly-used weak password') }}</li>
            </ul>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('dashboard') }}" wire:navigate
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </a>
            <button type="submit" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Change Password') }}</span>
                <span wire:loading>{{ __('Changing...') }}</span>
            </button>
        </div>
    </form>
</div>
