<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Fiscal Years') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Manage fiscal years and serial number sequences. Fiscal year runs July 1 to June 30.') }}</p>
        </div>
        @can('create', \App\Modules\Organization\Models\FiscalYear::class)
        <a href="{{ route('fiscal-years.create') }}" wire:navigate
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">
            {{ __('Add Fiscal Year') }}
        </a>
        @endcan
    </div>

    {{-- Current fiscal year banner --}}
    @if($currentFiscalYear)
    <div class="bg-emerald-50 dark:bg-emerald-900 border border-emerald-200 dark:border-emerald-700 rounded-lg p-4 mb-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ __('Current Active Fiscal Year') }}</h3>
                <p class="text-sm text-emerald-700 dark:text-emerald-300">
                    {{ $currentFiscalYear->name }} — {{ $currentFiscalYear->date_range }}
                    ({{ __('Serial prefix') }}: {{ $currentFiscalYear->serial_prefix }})
                </p>
            </div>
        </div>
    </div>
    @else
    <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 mb-6">
        <p class="text-sm text-yellow-800 dark:text-yellow-200">
            {{ __('No active fiscal year. Please activate a fiscal year to enable garment receiving.') }}
        </p>
    </div>
    @endif

    {{-- Fiscal years table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Name') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Date Range') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Prefix') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Garments Received') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($fiscalYears as $fy)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $fy->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $fy->date_range }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white font-mono">{{ $fy->serial_prefix }}-XXXXX</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $fy->garments_received_count }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if($fy->is_closed)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Closed') }}</span>
                            @elseif($fy->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Active') }}</span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-medium">
                            <a href="{{ route('fiscal-years.show', $fy) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900">{{ __('View') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No fiscal years found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
