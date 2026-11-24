<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit Product') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $product->display_name }}</p>
        </div>
        @can('toggleStatus', $product)
        <div class="flex space-x-3 mt-4 sm:mt-0">
            @if($product->is_active)
            <button wire:click="deactivate" wire:loading.attr="disabled" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">{{ __('Deactivate') }}</button>
            @else
            <button wire:click="activate" wire:loading.attr="disabled" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">{{ __('Activate') }}</button>
            @endif
        </div>
        @endcan
    </div>
    <div class="mb-4"><a href="{{ route('products.show', $product) }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Product') }}</a></div>

    <form wire:submit="update" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6 max-w-2xl">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Code') }} <span class="text-red-500">*</span></label>
                <input wire:model="code" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                @error('code') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Category') }}</label>
                <input wire:model="category" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Name') }} <span class="text-red-500">*</span></label>
            <input wire:model="name" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
            @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Description') }}</label>
            <textarea wire:model="description" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"></textarea>
        </div>
        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('products.show', $product) }}" wire:navigate class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">{{ __('Cancel') }}</a>
            <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Save Changes') }}</span>
                <span wire:loading>{{ __('Saving...') }}</span>
            </button>
        </div>
    </form>

    @can('manageSizes', $product)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mt-6">
        <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-4">{{ __('Sizes') }}</h3>
        @if($sizes->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach($sizes as $size)
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm {{ $size->is_active ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 line-through' }}">
                {{ $size->size_label }}
                @if($size->is_active)
                <button wire:click="removeSize('{{ $size->size_label }}')" class="ml-2 text-red-600 hover:text-red-900">&times;</button>
                @endif
            </span>
            @endforeach
        </div>
        @endif
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <div class="flex items-center space-x-2">
                <input wire:model="newSize" type="text" placeholder="{{ __('Add size (e.g., 38)') }}" class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm" wire:keydown.enter.prevent="addSize">
                <button wire:click="addSize" wire:loading.attr="disabled" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">{{ __('Add Size') }}</button>
            </div>
            @error('newSize') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
    </div>
    @endcan
</div>
