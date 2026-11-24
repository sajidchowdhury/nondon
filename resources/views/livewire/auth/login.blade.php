<form wire:submit="login" class="space-y-6">
    <div>
        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Username') }}
        </label>
        <div class="mt-1">
            <input
                id="username"
                wire:model="username"
                type="text"
                autocomplete="username"
                required
                autofocus
                class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm"
            >
            @error('username')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('Password') }}
        </label>
        <div class="mt-1">
            <input
                id="password"
                wire:model="password"
                type="password"
                autocomplete="current-password"
                required
                class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <input
                id="remember"
                wire:model="remember"
                type="checkbox"
                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
            >
            <label for="remember" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                {{ __('Remember me') }}
            </label>
        </div>
    </div>

    <div>
        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <span wire:loading.remove>{{ __('Sign in') }}</span>
            <span wire:loading>{{ __('Signing in...') }}</span>
        </button>
    </div>

    <div class="text-xs text-center text-gray-500 dark:text-gray-400">
        {{ __('Five failed attempts will lock the account.') }}
    </div>
</form>
