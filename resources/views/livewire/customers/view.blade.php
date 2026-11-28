<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Customer Details') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $customer->display_name }}</p>
        </div>
        @can('update', $customer)
        <a href="{{ route('customers.edit', $customer) }}" wire:navigate
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">
            {{ __('Edit Customer') }}
        </a>
        @endcan
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
    </div>
    @endif

    {{-- Customer info --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Phone') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $customer->phone }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Name') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $customer->name ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Type') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $customer->type_label }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt>
                <dd class="mt-1">
                    @if($customer->is_active)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Active') }}</span>
                    @elseif($customer->isMerged())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Merged') }}</span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Inactive') }}</span>
                    @endif
                </dd>
            </div>
            @if($customer->address)
            <div class="md:col-span-2">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Address') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $customer->address }}</dd>
            </div>
            @endif
            @if($customer->merged_into_id)
            <div class="md:col-span-2">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Merged Into') }}</dt>
                <dd class="mt-1 text-sm">
                    <a href="{{ route('customers.show', $customer->mergedInto) }}" wire:navigate class="text-emerald-600 hover:text-emerald-900">{{ $customer->mergedInto?->display_name ?? __('N/A') }}</a>
                </dd>
            </div>
            @endif
        </dl>
    </div>

    {{-- Purchase history (placeholder — populated in Session 3.10) --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Purchase History') }}</h3>
        @if($history->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Purchase history will appear here once the Sales module is built (Session 3.2).') }}</p>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Invoice') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Date') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($history as $invoice)
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $invoice->invoice_number ?? $invoice->id }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $invoice->completed_at ?? $invoice->created_at }}</td>
                        <td class="px-6 py-4 text-sm text-right text-gray-900 dark:text-white">{{ $invoice->total_amount ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
