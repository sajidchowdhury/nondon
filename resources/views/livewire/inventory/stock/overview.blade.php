<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Stock Overview') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Stock summary across all branches.') }}</p>
    </div>

    {{-- Branch stock counts --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        @foreach($branches as $branch)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $branch->code }} — {{ $branch->name }}</h3>
            <div class="mt-4 grid grid-cols-3 gap-4">
                <div>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $branch->available_count }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Available') }}</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $branch->reserved_count }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Reserved') }}</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $branch->sold_count }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Sold') }}</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Reservation summary --}}
    @if($reservations->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-4">{{ __('Active Reservations by Branch') }}</h3>
        <div class="space-y-2">
            @foreach($reservations as $res)
            <div class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900 rounded-md">
                <span class="text-sm text-gray-900 dark:text-white">{{ $res->code }} — {{ $res->name }}</span>
                <span class="text-sm font-bold text-yellow-700 dark:text-yellow-300">{{ $res->reserved_count }} {{ __('reserved') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Product-level summary --}}
    @if(!empty($summary))
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <h3 class="text-sm font-medium text-gray-900 dark:text-white p-4 border-b border-gray-200 dark:border-gray-700">{{ __('Stock by Product') }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Product') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Branch') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Size') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Count') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($summary as $product)
                    @foreach($product['branches'] as $branchData)
                    @foreach($branchData['sizes'] as $sizeLabel => $count)
                    <tr>
                        <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $product['code'] }} — {{ $product['product'] }}</td>
                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $branchData['code'] }}</td>
                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $sizeLabel }}</td>
                        <td class="px-4 py-2 text-right font-medium text-gray-900 dark:text-white">{{ $count }}</td>
                    </tr>
                    @endforeach
                    @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
