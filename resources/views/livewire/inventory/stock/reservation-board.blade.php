<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Reservation Board') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Currently reserved garments across all branches. Reservations are released manually — no automatic expiry.') }}</p>
    </div>

    @if($reservations->isNotEmpty())
    {{-- Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach($reservations as $res)
        <div class="bg-yellow-50 dark:bg-yellow-900 rounded-lg p-4">
            <p class="text-xs text-yellow-700 dark:text-yellow-300">{{ $res->code }} — {{ $res->name }}</p>
            <p class="text-2xl font-bold text-yellow-800 dark:text-yellow-200">{{ $res->reserved_count }}</p>
            <p class="text-xs text-yellow-600 dark:text-yellow-400">{{ __('reserved') }}</p>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Reserved garments list --}}
    @if($reservedGarments->isNotEmpty())
    @foreach($reservedGarments as $branchId => $garments)
    @php $branch = \App\Modules\Organization\Models\Branch::find($branchId); @endphp
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-4">
        <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">
            {{ $branch?->code ?? __('N/A') }} — {{ $branch?->name ?? __('Unassigned') }}
            <span class="ml-2 text-xs text-gray-500">{{ $garments->count() }} {{ __('garment(s)') }}</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Barcode') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Serial') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Product') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Size') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($garments as $garment)
                    <tr>
                        <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">{{ $garment->barcode }}</td>
                        <td class="px-3 py-2 font-mono text-gray-500 dark:text-gray-400">{{ $garment->fiscal_year_serial }}</td>
                        <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $garment->product?->name }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $garment->productSize?->size_label ?? __('N/A') }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('garments.history', $garment) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900 text-xs">{{ __('History') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
    @else
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No reserved garments. All available stock is unreserved.') }}</p>
    </div>
    @endif
</div>
