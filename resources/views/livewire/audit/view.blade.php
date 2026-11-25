<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Audit Entry Detail') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $log->action }}</p>
    </div>
    <div class="mb-4"><a href="{{ route('audit.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Audit Log') }}</a></div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Action info --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Action Details') }}</h3>
            <dl class="space-y-3">
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Action') }}</dt><dd class="text-sm text-gray-900 dark:text-white font-mono">{{ $log->action }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Category') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ ucfirst($log->category) }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Performed By') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $log->user?->display_name ?? __('System') }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Branch') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $log->branch?->display_name ?? __('N/A') }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Reference') }}</dt><dd class="text-sm text-gray-900 dark:text-white">@if($log->reference_type){{ ucfirst(str_replace('_', ' ', $log->reference_type)) }} #{{ $log->reference_id }}@else{{ __('N/A') }}@endif</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Timestamp') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $log->performed_at->format('M d, Y H:i:s') }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('IP Address') }}</dt><dd class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $log->ip_address ?? __('N/A') }}</dd></div>
                @if($log->reason)
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Reason') }}</dt><dd class="text-sm text-gray-900 dark:text-white italic">{{ $log->reason }}</dd></div>
                @endif
            </dl>
        </div>

        {{-- Values --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Values') }}</h3>

            @if($log->previous_values)
            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ __('Previous Values') }}</h4>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-3 overflow-x-auto">
                    <pre class="text-xs text-gray-800 dark:text-gray-200">{{ json_encode($log->previous_values, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif

            @if($log->new_values)
            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ __('New Values') }}</h4>
                <div class="bg-emerald-50 dark:bg-emerald-900 rounded-md p-3 overflow-x-auto">
                    <pre class="text-xs text-emerald-800 dark:text-emerald-200">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif

            @if(!$log->previous_values && !$log->new_values)
            <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No value changes recorded for this action.') }}</p>
            @endif

            @if($log->user_agent)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('User Agent') }}</h4>
                <p class="text-xs text-gray-400 dark:text-gray-500 break-all">{{ $log->user_agent }}</p>
            </div>
            @endif
        </div>
    </div>
</div>
