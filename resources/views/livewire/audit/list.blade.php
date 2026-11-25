<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Audit Log') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            @if($isAdmin)
            {{ __('All actions across all branches and dates.') }}
            @else
            {{ __('Actions for your branch today only.') }}
            @endif
        </p>
    </div>

    {{-- Filters (admin only — supervisors see today/own-branch only) --}}
    @if($isAdmin)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Search') }}</label>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Action, category, or reference') }}"
                       class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Category') }}</label>
                <select wire:model.live="categoryFilter" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Branch') }}</label>
                <select wire:model.live="branchFilter" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Branches') }}</option>
                    @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->code }} — {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('User') }}</label>
                <select wire:model.live="userFilter" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">{{ __('All Users') }}</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->username }} ({{ $u->full_name }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Date From') }}</label>
                <input wire:model.live="dateFrom" type="date" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Date To') }}</label>
                <input wire:model.live="dateTo" type="date" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
        </div>
        @if($search || $categoryFilter || $actionFilter || $userFilter || $branchFilter || $dateFrom || $dateTo)
        <div class="mt-3"><button wire:click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear filters') }}</button></div>
        @endif
    </div>
    @endif

    {{-- Audit log table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Time') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('User') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Action') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Category') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Branch') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Reference') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $log->performed_at->format('M d, Y H:i:s') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $log->user?->display_name ?? __('System') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="font-mono text-xs text-gray-900 dark:text-white">{{ $log->action }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">{{ $log->category }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $log->branch?->code ?? __('N/A') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                            @if($log->reference_type)
                            {{ ucfirst(str_replace('_', ' ', $log->reference_type)) }} #{{ $log->reference_id }}
                            @else
                            —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-medium">
                            <a href="{{ route('audit.show', $log) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900">{{ __('View') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No audit entries found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
