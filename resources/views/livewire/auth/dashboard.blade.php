<div class="space-y-6">
    {{-- Welcome message --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ __('Welcome, :name', ['name' => $this->user?->display_name]) }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('You are signed in to the Nondon ERP system.') }}
        </p>
    </div>

    {{-- Current session info --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Active Role') }}</h3>
            <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                {{ $this->activeRole?->display_name ?? $this->activeRole?->name ?? __('No active role') }}
            </p>
            @if($this->user?->roles->count() > 1)
            <a href="{{ route('role.select') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-500">
                {{ __('Switch role') }} &rarr;
            </a>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Assigned Branch') }}</h3>
            <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                {{ $this->assignedBranch?->name ?? __('No branch assigned') }}
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $this->assignedBranch?->code }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Account Status') }}</h3>
            <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                {{ $this->user?->is_active ? __('Active') : __('Inactive') }}
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Last login') }}: {{ $this->user?->last_login_at?->format('M d, Y H:i') ?? __('N/A') }}
            </p>
        </div>
    </div>

    {{-- Phase 0 status --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            {{ __('System Status') }}
        </h3>
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <p>{{ __('Phase 0: Project Setup — Complete') }}</p>
            <p>{{ __('Foundation modules loaded: Identity, Organization') }}</p>
            <p>{{ __('Authentication system active with login security and role management.') }}</p>
            <p>{{ __('Business modules will be added in subsequent development phases.') }}</p>
        </div>
    </div>
</div>
