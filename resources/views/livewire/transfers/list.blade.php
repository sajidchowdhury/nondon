<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Transfers') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Inter-branch transfer requests.') }}</p>
        </div>
        @can('create', \App\Modules\Transfers\Models\TransferRequest::class)
        <a href="{{ route('transfers.create') }}" wire:navigate
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">
            {{ __('New Transfer') }}
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Status') }}</label>
                <select wire:model.live="statusFilter" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach(\App\Modules\Transfers\Models\TransferRequest::STATUSES as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Direction') }}</label>
                <select wire:model.live="directionFilter" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                    <option value="">{{ __('All Directions') }}</option>
                    <option value="incoming">{{ __('Incoming') }}</option>
                    <option value="outgoing">{{ __('Outgoing') }}</option>
                </select>
            </div>
        </div>
        @if($statusFilter || $directionFilter)
        <div class="mt-3"><button wire:click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear filters') }}</button></div>
        @endif
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Number') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('From → To') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Requested by') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Date') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Items') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($transfers as $transfer)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $transfer->request_number }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                            {{ $transfer->fromBranch?->code }} → {{ $transfer->toBranch?->code }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @switch($transfer->status)
                                    @case('pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                    @case('approved') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                    @case('dispatched') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 @break
                                    @case('received') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                    @case('rejected') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                    @case('cancelled') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @break
                                    @case('discrepancy') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 @break
                                    @default bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                @endswitch">
                                {{ ucfirst($transfer->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $transfer->requestedBy?->full_name ?? $transfer->requestedBy?->username }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $transfer->requested_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ $transfer->items()->count() }}</td>
                        <td class="px-6 py-4 text-right text-sm font-medium">
                            <a href="{{ route('transfers.show', $transfer) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900">{{ __('View') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No transfer requests found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transfers->hasPages())
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">{{ $transfers->links() }}</div>
        @endif
    </div>
</div>
