<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $product->name }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $product->code }}@if($product->category) — {{ $product->category }}@endif</p>
        </div>
        @can('update', $product)
        <a href="{{ route('products.edit', $product) }}" wire:navigate class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm">{{ __('Edit Product') }}</a>
        @endcan
    </div>
    <div class="mb-4"><a href="{{ route('products.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Products') }}</a></div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Product Details') }}</h3>
            <dl class="space-y-3">
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Code') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $product->code }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Name') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $product->name }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Category') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $product->category ?? __('N/A') }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Description') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $product->description ?? __('N/A') }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt><dd class="text-sm">@if($product->is_active)<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Active') }}</span>@else<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ __('Inactive') }}</span>@endif</dd></div>
                <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Created') }}</dt><dd class="text-sm text-gray-900 dark:text-white">{{ $product->created_at->format('M d, Y') }}</dd></div>
            </dl>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('Sizes') }}</h3>
            @if($product->sizes->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach($product->sizes as $size)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm {{ $size->is_active ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 line-through' }}">{{ $size->size_label }}</span>
                @endforeach
            </div>
            @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No sizes defined.') }}</p>
            @endif
        </div>
    </div>
</div>
