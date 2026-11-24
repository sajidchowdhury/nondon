<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
            {{ __('Select Active Role') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('You have multiple roles assigned. Select the role you want to work with for this session.') }}
        </p>
    </div>

    <form wire:submit="switchRole" class="space-y-4">
        @foreach($roles as $role)
        <label class="flex items-start space-x-3 p-4 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
            <input
                type="radio"
                wire:model="selectedRoleId"
                value="{{ $role->id }}"
                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
            >
            <div class="flex-1">
                <span class="block text-sm font-medium text-gray-900 dark:text-white">
                    {{ $role->display_name ?? $role->name }}
                </span>
                <span class="block text-sm text-gray-500 dark:text-gray-400">
                    {{ $role->description ?? __('No description available') }}
                </span>
            </div>
        </label>
        @endforeach

        @error('selectedRoleId')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <div>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                <span wire:loading.remove>{{ __('Continue') }}</span>
                <span wire:loading>{{ __('Switching...') }}</span>
            </button>
        </div>
    </form>
</div>
