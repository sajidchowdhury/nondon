<div class="space-y-6">
    {{-- Header --}}
    <div class="text-center">
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 mb-4">
            <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Change Your Password') }}</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            {{ __('For security reasons, you must change your password before continuing.') }}
        </p>
    </div>

    {{-- Form --}}
    <form wire:submit="change" class="space-y-6">
        {{-- New password --}}
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('New Password') }} <span class="text-red-500">*</span>
            </label>
            <input
                id="password"
                wire:model="password"
                type="password"
                autocomplete="new-password"
                autofocus
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
            >
            @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Confirm password --}}
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
        <div>
            <button type="submit" wire:loading.attr="disabled"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 disabled:opacity-50">
                <span wire:loading.remove>{{ __('Change Password and Continue') }}</span>
                <span wire:loading>{{ __('Changing...') }}</span>
            </button>
        </div>

        {{-- Logout option --}}
        <div class="text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    {{ __('Logout instead') }}
                </button>
            </form>
        </div>
    </form>
</div>
