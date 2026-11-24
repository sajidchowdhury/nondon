<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Garment Details') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $garment->barcode }}</p>
    </div>
    <div class="mb-4"><a href="{{ route('garments.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Garments') }}</a></div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Identity --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Identity') }}</h3>
            <dl class="space-y-3">
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Barcode') }}</dt><dd class="text-sm text-gray-900 dark:text-white font-mono">{{ $garment->barcode }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Fiscal Year Serial') }}</dt><dd class="text-sm text-gray-900 dark:text-white font-mono">{{ $garment->fiscal_year_serial }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Fiscal Year') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $garment->fiscalYear?->name }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Internal ID') }}</dt><dd class="text-sm text-gray-500 dark:text-gray-400 font-mono text-xs break-all">{{ $garment->internal_id }}</dd></div>
                @if(auth()->user()?->hasRole('main_branch_admin') && $garment->purchase_cost)
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Purchase Cost') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ number_format((float)$garment->purchase_cost, 2) }}</dd></div>
                @endif
            </dl>
        </div>

        {{-- Product & Location --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Product & Location') }}</h3>
            <dl class="space-y-3">
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Product') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $garment->product?->display_name ?? __('N/A') }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Size') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $garment->productSize?->size_label ?? __('N/A') }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Current Branch') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $garment->currentBranch?->display_name ?? __('N/A') }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Location') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $garment->currentLocation?->name ?? __('N/A') }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt><dd class="text-sm"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ $garment->status_label }}</span></dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Clearance') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $garment->is_clearance ? __('Yes') : __('No') }}</dd></div>
            </dl>
        </div>
    </div>
</div>
