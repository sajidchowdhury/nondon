<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Supplier Details') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $supplier->display_name }}</p>
        </div>
        @can('update', $supplier)
        <a href="{{ route('suppliers.edit', $supplier) }}" wire:navigate
           class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">
            {{ __('Edit Supplier') }}
        </a>
        @endcan
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
    </div>
    @endif

    {{-- Supplier info --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Code') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $supplier->code }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Name') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $supplier->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Contact Person') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $supplier->contact_person ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Phone') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $supplier->phone ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Email') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $supplier->email ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Address') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $supplier->address ?? __('N/A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Outstanding Balance') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ number_format((float) $supplier->outstanding_balance, 2) }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt>
                <dd class="mt-1">
                    @if($supplier->is_active)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Active') }}</span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Inactive') }}</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    {{-- Purchase history summary (placeholder — populated in Session 2.2) --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Purchase History') }}</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Purchase records will appear here once the Purchase module is built (Session 2.2).') }}</p>
    </div>
</div>
