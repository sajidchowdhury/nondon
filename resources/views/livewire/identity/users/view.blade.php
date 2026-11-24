<div>
    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $user->display_name }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $user->username }}</p>
        </div>
        @can('update', $user)
        <a href="{{ route('users.edit', $user) }}" wire:navigate
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">
            {{ __('Edit User') }}
        </a>
        @endcan
    </div>

    {{-- Back link --}}
    <div class="mb-4">
        <a href="{{ route('users.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            &larr; {{ __('Back to Users') }}
        </a>
    </div>

    {{-- User details --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Profile --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Profile') }}</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Username') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $user->username }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Full Name') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $user->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Email') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $user->email ?? __('N/A') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Branch') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">
                        @if($user->assignedBranch)
                        {{ $user->assignedBranch->code }} — {{ $user->assignedBranch->name }}
                        @else
                        {{ __('N/A') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Roles') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">
                        @foreach($user->roles as $role)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200 mr-1">
                            {{ $role->display_name ?? $role->name }}
                        </span>
                        @endforeach
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Account status --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Account Status') }}</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt>
                    <dd class="text-sm">
                        @if($user->isLocked())
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Locked') }}</span>
                        @elseif(!$user->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Inactive') }}</span>
                        @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Active') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Last Login') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $user->last_login_at?->format('M d, Y H:i:s') ?? __('Never') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Last Login IP') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $user->last_login_ip ?? __('N/A') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Password Changed') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $user->password_changed_at?->format('M d, Y H:i') ?? __('N/A') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Failed Login Attempts') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $user->failed_login_attempts }}</dd>
                </div>
                @if($user->isLocked())
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Locked At') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $user->locked_at?->format('M d, Y H:i:s') }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Created') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $user->created_at->format('M d, Y H:i') }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Login history --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mt-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Recent Login Activity') }}</h3>
        @if($loginAttempts->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Time') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('IP Address') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Result') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Reason') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($loginAttempts as $attempt)
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $attempt->attempted_at->format('M d, Y H:i:s') }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $attempt->ip_address ?? __('N/A') }}</td>
                        <td class="px-4 py-2 text-sm">
                            @if($attempt->success)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Success') }}</span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{{ __('Failed') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $attempt->failure_reason ?? __('') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No login activity recorded.') }}</p>
        @endif
    </div>
</div>
