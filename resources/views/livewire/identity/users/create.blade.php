<div>
    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Create User') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Add a new user to the system.') }}</p>
    </div>

    {{-- Back link --}}
    <div class="mb-4">
        <a href="{{ route('users.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            &larr; {{ __('Back to Users') }}
        </a>
    </div>

    {{-- Form --}}
    <form wire:submit="create" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
        {{-- Username --}}
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Username') }} <span class="text-red-500">*</span>
            </label>
            <input
                id="username"
                wire:model="username"
                type="text"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
            >
            @error('username') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Full Name --}}
        <div>
            <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Full Name') }} <span class="text-red-500">*</span>
            </label>
            <input
                id="full_name"
                wire:model="full_name"
                type="text"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
            >
            @error('full_name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}</label>
            <input
                id="email"
                wire:model="email"
                type="email"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
            >
            @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Password --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Password') }} <span class="text-red-500">*</span>
                </label>
                <input
                    id="password"
                    wire:model="password"
                    type="password"
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
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
                >
                @error('password_confirmation') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Force password change --}}
        <div class="flex items-center">
            <input
                id="force_password_change"
                wire:model="force_password_change"
                type="checkbox"
                class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded"
            >
            <label for="force_password_change" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                {{ __('Force password change on next login') }}
            </label>
        </div>

        {{-- Online channel toggle --}}
        <div class="flex items-center">
            <input
                id="is_online_channel"
                wire:model="is_online_channel"
                type="checkbox"
                class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded"
            >
            <label for="is_online_channel" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                {{ __('Online Sales Team member (not a physical branch employee)') }}
            </label>
        </div>

        {{-- Branch assignment --}}
        @unless($is_online_channel)
        <div>
            <label for="assigned_branch_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Assigned Branch') }} <span class="text-red-500">*</span>
            </label>
            <select
                id="assigned_branch_id"
                wire:model="assigned_branch_id"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"
            >
                <option value="">{{ __('Select a branch') }}</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->code }} — {{ $branch->name }}</option>
                @endforeach
            </select>
            @error('assigned_branch_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
        @else
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Channel') }}</label>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $onlineBranch?->name ?? __('Online Sales Channel') }}
            </p>
        </div>
        @endunless

        {{-- Roles --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Roles') }}</label>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('All roles can sell. Supervision/admin is an additional permission layer.') }}</p>
            <div class="space-y-2">
                @foreach($roles as $role)
                <label class="flex items-start space-x-3">
                    <input
                        type="checkbox"
                        wire:model="selectedRoles"
                        value="{{ $role->name }}"
                        class="mt-1 h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded"
                    >
                    <div>
                        <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $role->display_name ?? $role->name }}</span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $role->description ?? '' }}</span>
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('users.index') }}" wire:navigate
               class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </a>
            <button
                type="submit"
                wire:loading.attr("disabled")
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50"
            >
                <span wire:loading.remove>{{ __('Create User') }}</span>
                <span wire:loading>{{ __('Creating...') }}</span>
            </button>
        </div>
    </form>
</div>
