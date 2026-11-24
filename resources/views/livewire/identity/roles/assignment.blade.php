<div>
    {{-- Current roles --}}
    <div class="space-y-3">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ __('Assigned Roles') }}</h4>

        @foreach($userRoles as $role)
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
            <div class="flex items-center space-x-3">
                <div>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $role->display_name ?? ucfirst(str_replace('_', ' ', $role->name)) }}
                    </span>
                    @if($this->user->active_role_id === $role->id)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                        {{ __('Active') }}
                    </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center space-x-2">
                @if($this->user->active_role_id !== $role->id)
                <button wire:click="setActiveRole({{ $role->id }})"
                        class="text-sm text-emerald-600 hover:text-emerald-900 dark:text-emerald-400">
                    {{ __('Set Active') }}
                </button>
                @endif
                <button wire:click="removeRole({{ $role->id }})" wire:loading.attr="disabled"
                        wire:confirm="{{ __('Remove this role?') }}"
                        class="text-sm text-red-600 hover:text-red-900 dark:text-red-400 disabled:opacity-50">
                    {{ __('Remove') }}
                </button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Add role --}}
    @if($availableRoles->isNotEmpty())
    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">{{ __('Add Role') }}</h4>
        <div class="flex items-center space-x-3">
            <select wire:model="selectedRoleId"
                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">{{ __('Select a role to assign') }}</option>
                @foreach($availableRoles as $role)
                <option value="{{ $role->id }}">{{ $role->display_name ?? ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                @endforeach
            </select>
            <button wire:click="assignRole" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Assign') }}</span>
                <span wire:loading>{{ __('Assigning...') }}</span>
            </button>
        </div>
        @error('selectedRoleId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('roles') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
    @else
    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('All roles are already assigned to this user.') }}</p>
    </div>
    @endif
</div>
