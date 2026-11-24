<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fiscalYear->name }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $fiscalYear->date_range }}</p>
        </div>
        @can('activate', $fiscalYear)
        <div class="flex space-x-3 mt-4 sm:mt-0">
            @if(!$fiscalYear->is_closed && !$fiscalYear->is_active)
            <button wire:click="activate" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                {{ __('Activate') }}
            </button>
            @endif
            @if(!$fiscalYear->is_closed)
            <button wire:click="close" wire:loading.attr="disabled" wire:confirm="{{ __('Close this fiscal year? Unsold stock will carry forward.') }}"
                    class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                {{ __('Close Fiscal Year') }}
            </button>
            @endif
        </div>
        @endcan
    </div>
    <div class="mb-4"><a href="{{ route('fiscal-years.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Fiscal Years') }}</a></div>
    @error('is_active') <p class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    @error('is_closed') <p class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

    {{-- Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Fiscal year info --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Fiscal Year Details') }}</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Name') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $fiscalYear->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Start Date') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $fiscalYear->start_date->format('F d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('End Date') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">{{ $fiscalYear->end_date->format('F d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Serial Prefix') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white font-mono">{{ $fiscalYear->serial_prefix }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt>
                    <dd class="text-sm">
                        @if($fiscalYear->is_closed)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Closed') }}</span>
                        @elseif($fiscalYear->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Active') }}</span>
                        @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">{{ __('Inactive') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Serial info --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Serial Number Information') }}</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Garments Received This Fiscal Year') }}</dt>
                    <dd class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fiscalYear->garments_received_count }}</dd>
                </div>
                @if(!$fiscalYear->is_closed)
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Next Serial Number') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white font-mono text-lg">{{ $previewSerial }}</dd>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900 rounded-md p-3 mt-4">
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        {{ __('Serial format: YY-NNNNN. The prefix (:prefix) is derived from the start year. Each garment receives the next sequential number.', ['prefix' => $fiscalYear->serial_prefix]) }}
                    </p>
                </div>
                @else
                <div class="bg-gray-50 dark:bg-gray-700 rounded-md p-3 mt-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('This fiscal year is closed. No new serials can be generated.') }}
                    </p>
                </div>
                @endif
            </dl>
        </div>
    </div>
</div>
