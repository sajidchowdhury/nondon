<div>
    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit User') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $user->display_name }} ({{ $user->username }})</p>
    </div>

    {{-- Back link --}}
    <div class="mb-4">
        <a href="{{ route('users.show', $user) }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            &larr; {{ __('Back to User') }}
        </a>
    </div>

    {{-- Account status panel --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Account Status') }}</h3>
                <div class="mt-1">
                    @if($user->isLocked())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Locked') }}</span>
                    @elseif(!$user->is_active)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Inactive') }}</span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Active') }}</span>
                    @endif
                </div>
            </div>
            <div class="flex space-x-3">
                @if($user->isLocked())
                <button wire:click="unlock" wire:loading.attr="disabled"
                        class="px-3 py-1.5 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-md disabled:opacity-50">
                    {{ __('Unlock Account') }}
                </button>
                @endif
                @if($user->is_active)
                <button wire:click="deactivate" wire:loading.attr="disabled" wire:confirm="{{ __('Are you sure you want to deactivate this user?') }}"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md disabled:opacity-50">
                    {{ __('Deactivate') }}
                </button>
                @else
                <button wire:click="activate" wire:loading.attr="disabled"
                        class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md disabled:opacity-50">
                    {{ __('Activate') }}
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Edit form --}}
    <form wire:submit="update" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6">
        {{-- Username --}}
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Username') }} <span class="text-red-500">*</span></label>
            <input id="username" wire:model="username" type="text"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            @error('username') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Full Name --}}
        <div>
            <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Full Name') }} <span class="text-red-500">*</span></label>
            <input id="full_name" wire:model="full_name" type="text"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            @error('full_name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Email') }}</label>
            <input id="email" wire:model="email" type="email"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        {{-- Branch assignment --}}
        @unless($is_online_channel)
        <div>
            <label for="assigned_branch_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Assigned Branch') }} <span class="text-red-500">*</span></label>
            <select id="assigned_branch_id" wire:model="assigned_branch_id"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">{{ __('Select a branch') }}</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->code }} — {{ $branch->name }}</option>
                @endforeach
            </select>
            @error('assigned_branch_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
        @endunless

        {{-- Roles --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Roles') }}</label>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('All roles can sell. Supervision/admin is an additional permission layer.') }}</p>
            <div class="space-y-2">
                @foreach($roles as $role)
                <label class="flex items-start space-x-3">
                    <input type="checkbox" wire:model="selectedRoles" value="{{ $role->name }}"
                           class="mt-1 h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded">
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
            <a href="{{ route('users.show', $user) }}" wire:navigate
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

    {{-- Password reset section --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mt-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Password Reset') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Admin can reset the user password. User will be forced to change it on next login.') }}</p>
            </div>
            <button wire:click="$toggle('show_password_reset')"
                    class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ $show_password_reset ? __('Cancel') : __('Reset Password') }}
            </button>
        </div>

        @if($show_password_reset)
        <form wire:submit="resetPassword" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('New Password') }} <span class="text-red-500">*</span></label>
                    <input id="new_password" wire:model="new_password" type="password"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('new_password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Confirm Password') }} <span class="text-red-500">*</span></label>
                    <input id="new_password_confirmation" wire:model="new_password_confirmation" type="password"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('new_password_confirmation') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                    <span wire:loading.remove>{{ __('Reset Password') }}</span>
                    <span wire:loading>{{ __('Resetting...') }}</span>
                </button>
            </div>
        </form>
        @endif
    </div>
</div>
