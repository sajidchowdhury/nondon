<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Transfer') }} {{ $transfer->request_number }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $transfer->fromBranch?->name }} → {{ $transfer->toBranch?->name }}
            </p>
        </div>
        <a href="{{ route('transfers.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700 mt-4 sm:mt-0">{{ __('Back to list') }}</a>
    </div>

    @if($actionSuccess)
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm text-green-800 dark:text-green-200">{{ $actionSuccess }}</p>
    </div>
    @endif

    @if($actionError)
    <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900 p-3">
        <p class="text-sm text-red-800 dark:text-red-200">{{ $actionError }}</p>
    </div>
    @endif

    {{-- Header --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt>
                <dd class="mt-1 text-sm capitalize font-medium">{{ $transfer->status }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Requested by') }}</dt>
                <dd class="mt-1 text-sm">{{ $transfer->requestedBy?->full_name ?? '' }} · {{ $transfer->requested_at?->format('Y-m-d H:i') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Approved by') }}</dt>
                <dd class="mt-1 text-sm">{{ $transfer->approvedBy?->full_name ?? __('N/A') }} @if($transfer->approved_at)· {{ $transfer->approved_at->format('Y-m-d H:i') }}@endif</dd>
            </div>
            @if($transfer->dispatched_at)
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Dispatched by') }}</dt>
                <dd class="mt-1 text-sm">{{ $transfer->dispatchedBy?->full_name ?? '' }} · {{ $transfer->dispatched_at->format('Y-m-d H:i') }}</dd>
            </div>
            @endif
            @if($transfer->received_at)
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Received by') }}</dt>
                <dd class="mt-1 text-sm">{{ $transfer->receivedBy?->full_name ?? '' }} · {{ $transfer->received_at->format('Y-m-d H:i') }}</dd>
            </div>
            @endif
            @if($transfer->rejection_reason)
            <div class="md:col-span-3">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Rejection reason') }}</dt>
                <dd class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $transfer->rejection_reason }}</dd>
            </div>
            @endif
            @if($transfer->notes)
            <div class="md:col-span-3">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Notes') }}</dt>
                <dd class="mt-1 text-sm">{{ $transfer->notes }}</dd>
            </div>
            @endif
        </dl>
    </div>

    {{-- Items --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Garment') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Product') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Dispatched') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Received') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($transfer->items as $item)
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item->garment?->barcode ?? '#' . $item->garment_id }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item->product?->name }}@if($item->productSize?->size_label) ({{ $item->productSize->size_label }})@endif</td>
                        <td class="px-6 py-4 text-sm">{{ $item->dispatched ? __('Yes') : __('No') }}</td>
                        <td class="px-6 py-4 text-sm">{{ $item->received ? __('Yes') : __('No') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Discrepancies --}}
    @if($transfer->discrepancies->isNotEmpty())
    <div class="bg-orange-50 dark:bg-orange-900 border-l-4 border-orange-400 p-4 mb-6">
        <h3 class="text-sm font-medium text-orange-800 dark:text-orange-200 mb-2">{{ __('Discrepancies') }}</h3>
        @foreach($transfer->discrepancies as $d)
        <p class="text-sm text-orange-700 dark:text-orange-300">
            {{ ucfirst($d->discrepancy_type) }} —
            @if($d->missing_garment_ids){{ count($d->missing_garment_ids) }} {{ __('missing') }}@endif
            @if($d->excess_garment_ids){{ count($d->excess_garment_ids) }} {{ __('excess') }}@endif
            ({{ __('status') }}: {{ $d->status }})
        </p>
        @endforeach
    </div>
    @endif

    {{-- Actions --}}
    <div class="flex flex-wrap gap-3">
        @if($canApprove && $transfer->isPending())
        <button wire:click="approve" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
            <span wire:loading.remove>{{ __('Approve') }}</span>
            <span wire:loading>{{ __('...') }}</span>
        </button>
        @endif

        @if($canApprove && $transfer->isPending())
        <div class="flex items-center gap-2">
            <input wire:model="rejectReason" type="text" placeholder="{{ __('Rejection reason') }}" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white">
            <button wire:click="reject" wire:loading.attr="disabled" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Reject') }}</span>
                <span wire:loading>{{ __('...') }}</span>
            </button>
        </div>
        @error('rejectReason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endif

        @if($canDispatch && $transfer->isApproved())
        <button wire:click="dispatchTransfer" wire:loading.attr="disabled" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
            <span wire:loading.remove>{{ __('Dispatch') }}</span>
            <span wire:loading>{{ __('...') }}</span>
        </button>
        @endif

        @if($transfer->isDispatched())
        <a href="{{ route('transfers.dispatch-note', $transfer) }}" target="_blank" rel="noopener" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
            {{ __('Print dispatch note') }}
        </a>
        @endif

        @if($canReceive && $transfer->isDispatched())
        <a href="#" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm">
            {{ __('Receive') }} ({{ __('Session 3.8') }})
        </a>
        @endif

        @if($canCancel && $transfer->canCancel())
        <div class="flex items-center gap-2 ml-auto">
            <input wire:model="cancelReason" type="text" placeholder="{{ __('Cancellation reason') }}" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white">
            <button wire:click="cancel" wire:loading.attr="disabled" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Cancel transfer') }}</span>
                <span wire:loading>{{ __('...') }}</span>
            </button>
        </div>
        @error('cancelReason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endif
    </div>
</div>
