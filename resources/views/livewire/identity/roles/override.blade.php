<div>
    {{-- Current overrides --}}
    <div class="space-y-4">
        {{-- Grants --}}
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Custom Grants') }}</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('Permissions explicitly granted to this user in addition to their role permissions.') }}</p>
            @if($grants->isNotEmpty())
            <div class="space-y-2">
                @foreach($grants as $grant)
                <div class="flex items-center justify-between p-2 bg-emerald-50 dark:bg-emerald-900 rounded-md">
                    <div>
                        <span class="text-sm font-medium text-emerald-900 dark:text-emerald-100">
                            {{ $grant->permission->display_name ?? ucfirst(str_replace('.', ' ', $grant->permission->name)) }}
                        </span>
                        @if($grant->reason)
                        <span class="text-xs text-emerald-600 dark:text-emerald-400 ml-2">{{ $grant->reason }}</span>
                        @endif
                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                            {{ __('by') }} {{ $grant->grantedByUser?->display_name ?? __('Unknown') }} {{ __('on') }} {{ $grant->granted_at->format('M d, Y') }}
                        </span>
                    </div>
                    <button wire:click="removeOverride({{ $grant->permission_id }})"
                            class="text-sm text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400">
                        {{ __('Remove') }}
                    </button>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No custom grants.') }}</p>
            @endif
        </div>

        {{-- Revokes --}}
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Custom Revokes') }}</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('Permissions explicitly revoked from this user, even if their role grants them.') }}</p>
            @if($revokes->isNotEmpty())
            <div class="space-y-2">
                @foreach($revokes as $revoke)
                <div class="flex items-center justify-between p-2 bg-red-50 dark:bg-red-900 rounded-md">
                    <div>
                        <span class="text-sm font-medium text-red-900 dark:text-red-100">
                            {{ $revoke->permission->display_name ?? ucfirst(str_replace('.', ' ', $revoke->permission->name)) }}
                        </span>
                        @if($revoke->reason)
                        <span class="text-xs text-red-600 dark:text-red-400 ml-2">{{ $revoke->reason }}</span>
                        @endif
                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                            {{ __('by') }} {{ $revoke->grantedByUser?->display_name ?? __('Unknown') }} {{ __('on') }} {{ $revoke->granted_at->format('M d, Y') }}
                        </span>
                    </div>
                    <button wire:click="removeOverride({{ $revoke->permission_id }})"
                            class="text-sm text-gray-500 hover:text-emerald-600 dark:text-gray-400 dark:hover:text-emerald-400">
                        {{ __('Restore') }}
                    </button>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No custom revokes.') }}</p>
            @endif
        </div>
    </div>

    {{-- Add override form --}}
    @if($availablePermissions->isNotEmpty())
    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">{{ __('Add Override') }}</h4>
        <div class="space-y-3">
            <div class="flex items-center space-x-4">
                <label class="flex items-center">
                    <input type="radio" wire:model="action" value="grant" class="text-emerald-600 focus:ring-emerald-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Grant') }}</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" wire:model="action" value="revoke" class="text-red-600 focus:ring-red-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Revoke') }}</span>
                </label>
            </div>
            <select wire:model="selectedPermissionId"
                    class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">{{ __('Select a permission') }}</option>
                @foreach($availablePermissions as $permission)
                @php
                $isAdminOnly = $this->isAdminOnlyPermission($permission->name);
                @endphp
                <option value="{{ $permission->id }}" {{ $isAdminOnly && $action === 'grant' ? 'disabled' : '' }}>
                    {{ ucfirst($permission->module) }} — {{ $permission->display_name ?? ucfirst(str_replace('.', ' ', $permission->name)) }}
                    @if($isAdminOnly)
                    {{ __('(Admin-Only)') }}
                    @endif
                </option>
                @endforeach
            </select>
            <input wire:model="reason" type="text" placeholder="{{ __('Optional reason') }}"
                   class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            <button wire:click="applyOverride" wire:loading.attr="disabled"
                    class="px-4 py-2 {{ $action === 'grant' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700' }} text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ $action === 'grant' ? __('Grant Permission') : __('Revoke Permission') }}</span>
                <span wire:loading>{{ __('Processing...') }}</span>
            </button>
        </div>
        @error('selectedPermissionId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
    @else
    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('All permissions have overrides or none are available.') }}</p>
    </div>
    @endif
</div>
