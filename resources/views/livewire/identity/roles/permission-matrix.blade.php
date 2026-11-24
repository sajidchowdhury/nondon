<div>
    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Permission Matrix') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('View and manage permissions assigned to each role. Admin-only permissions are locked to the Main Branch Admin role.') }}
        </p>
    </div>

    {{-- Legend --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <div class="flex items-center">
                <span class="w-4 h-4 bg-emerald-500 rounded mr-2"></span>
                <span class="text-gray-700 dark:text-gray-300">{{ __('Granted') }}</span>
            </div>
            <div class="flex items-center">
                <span class="w-4 h-4 bg-gray-300 dark:bg-gray-600 rounded mr-2"></span>
                <span class="text-gray-700 dark:text-gray-300">{{ __('Not Granted') }}</span>
            </div>
            <div class="flex items-center">
                <svg class="w-4 h-4 text-yellow-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
                <span class="text-gray-700 dark:text-gray-300">{{ __('Admin-Only (Locked)') }}</span>
            </div>
        </div>
    </div>

    {{-- Matrix table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider sticky left-0 bg-gray-50 dark:bg-gray-900">
                            {{ __('Permission') }}
                        </th>
                        @foreach($roles as $role)
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[120px]">
                            {{ $role->display_name ?? ucfirst(str_replace('_', ' ', $role->name)) }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($permissionsByModule as $module => $modulePermissions)
                    {{-- Module header row --}}
                    <tr class="bg-gray-100 dark:bg-gray-900">
                        <td colspan="{{ $roles->count() + 1 }}" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ ucfirst(str_replace('_', ' ', $module)) }}
                        </td>
                    </tr>
                    @foreach($modulePermissions as $permission)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                            {{ $permission->display_name ?? ucfirst(str_replace('.', ' ', $permission->name)) }}
                        </td>
                        @foreach($roles as $role)
                        @php
                        $isAdminOnly = $this->isAdminOnlyPermission($permission->name);
                        $isLocked = $isAdminOnly && $role->name !== 'main_branch_admin';
                        $hasPermission = $role->permissions->contains('id', $permission->id);
                        @endphp
                        <td class="px-4 py-3 text-center">
                            @if($isLocked)
                            {{-- Locked: admin-only permission on non-admin role --}}
                            <span class="inline-flex items-center justify-center w-6 h-6 text-yellow-500" title="{{ __('Admin-only permission') }}">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            @else
                            <button wire:click="togglePermission({{ $role->id }}, {{ $permission->id }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center justify-center w-6 h-6 rounded transition {{ $hasPermission ? 'bg-emerald-500 hover:bg-emerald-600' : 'bg-gray-300 dark:bg-gray-600 hover:bg-gray-400' }} disabled:opacity-50"
                                    title="{{ $hasPermission ? __('Click to remove') : __('Click to grant') }}">
                                @if($hasPermission)
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                @endif
                            </button>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
