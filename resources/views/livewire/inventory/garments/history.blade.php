<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Movement History') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $garment->barcode }} ({{ $garment->fiscal_year_serial }})</p>
    </div>
    <div class="mb-4"><a href="{{ route('garments.show', $garment) }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Garment') }}</a></div>

    {{-- Garment info --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><span class="text-gray-500 dark:text-gray-400">{{ __('Product') }}:</span> <span class="text-gray-900 dark:text-white">{{ $garment->product?->name }}</span></div>
            <div><span class="text-gray-500 dark:text-gray-400">{{ __('Size') }}:</span> <span class="text-gray-900 dark:text-white">{{ $garment->productSize?->size_label ?? __('N/A') }}</span></div>
            <div><span class="text-gray-500 dark:text-gray-400">{{ __('Branch') }}:</span> <span class="text-gray-900 dark:text-white">{{ $garment->currentBranch?->code ?? __('N/A') }}</span></div>
            <div><span class="text-gray-500 dark:text-gray-400">{{ __('Status') }}:</span> <span class="text-gray-900 dark:text-white">{{ $garment->status_label }}</span></div>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">{{ __('Lifecycle Timeline') }}</h3>

        @if($history->isNotEmpty())
        <div class="space-y-4">
            @foreach($history as $entry)
            <div class="flex items-start space-x-4">
                {{-- Timeline dot --}}
                <div class="flex-shrink-0 mt-1">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full
                        @switch($entry['movement_type'])
                            @case('goods_receipt') bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-300 @break
                            @case('sale') @case('sale_offline') bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 @break
                            @case('damage_report') @case('missing_report') bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-300 @break
                            @case('reservation') bg-yellow-100 text-yellow-600 dark:bg-yellow-900 dark:text-yellow-300 @break
                            @case('recovery') @case('inspection_complete') bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-300 @break
                            @default bg-emerald-100 text-emerald-600 dark:bg-emerald-900 dark:text-emerald-300
                        @endswitch
                    ">
                        @switch($entry['movement_type'])
                            @case('goods_receipt')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @break
                            @case('sale') @case('sale_offline')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                @break
                            @default
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        @endswitch
                    </span>
                </div>
                {{-- Timeline content --}}
                <div class="flex-1 pb-4 border-b border-gray-100 dark:border-gray-700 last:border-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $entry['description'] }}</p>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 space-x-3">
                        <span>{{ $entry['performed_at']->format('M d, Y H:i') }}</span>
                        <span>{{ __('by') }} {{ $entry['performed_by'] }}</span>
                        @if($entry['reference'])
                        <span>{{ $entry['reference'] }}</span>
                        @endif
                    </div>
                    <div class="mt-1 text-xs">
                        <span class="text-gray-400 dark:text-gray-500">{{ $entry['from_status'] }}</span>
                        <svg class="inline w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <span class="text-gray-700 dark:text-gray-300">{{ $entry['to_status'] }}</span>
                        @if($entry['from_branch'] !== '—' || $entry['to_branch'] !== '—')
                        <span class="ml-2 text-gray-400 dark:text-gray-500">{{ $entry['from_branch'] }} &rarr; {{ $entry['to_branch'] }}</span>
                        @endif
                    </div>
                    @if($entry['notes'])
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500 italic">{{ $entry['notes'] }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No movement history recorded.') }}</p>
        @endif
    </div>
</div>
