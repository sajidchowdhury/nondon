<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit Branch') }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $branch->display_name }}</p>
        </div>
        <div class="flex space-x-3 mt-4 sm:mt-0">
            @if($branch->is_active)
            <button wire:click="deactivate" wire:loading.attr="disabled" wire:confirm="{{ __('Deactivate this branch?') }}"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                {{ __('Deactivate') }}
            </button>
            @else
            <button wire:click="activate" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                {{ __('Activate') }}
            </button>
            @endif
        </div>
    </div>
    <div class="mb-4"><a href="{{ route('branches.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Back to Branches') }}</a></div>
    @error('is_active') <p class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

    {{-- Branch details form --}}
    <form wire:submit="update" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-6 max-w-2xl">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Code') }} <span class="text-red-500">*</span></label>
                <input wire:model="code" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                @error('code') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input wire:model="name" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Type') }}</label>
            <select wire:model="type" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                <option value="physical">{{ __('Physical Branch') }}</option>
                <option value="main">{{ __('Main Branch') }}</option>
                <option value="online">{{ __('Online Channel') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Address') }}</label>
            <textarea wire:model="address" rows="2" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Phone') }}</label>
            <input wire:model="phone" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
        </div>
        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('branches.index') }}" wire:navigate class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">{{ __('Cancel') }}</a>
            <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Save Changes') }}</span>
                <span wire:loading>{{ __('Saving...') }}</span>
            </button>
        </div>
    </form>

    {{-- Stock locations --}}
    @can('manageStockLocations', $branch)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mt-6">
        <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-4">{{ __('Stock Locations') }}</h3>

        {{-- Existing locations --}}
        @if($stockLocations->isNotEmpty())
        <div class="space-y-2 mb-6">
            @foreach($stockLocations as $location)
            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                <div>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $location->code }} — {{ $location->name }}</span>
                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ $location->type_label }}</span>
                    @if(!$location->is_active)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700">{{ __('Inactive') }}</span>
                    @endif
                </div>
                @if($location->is_active)
                <button wire:click="deactivateLocation({{ $location->id }})" class="text-sm text-red-600 hover:text-red-900">{{ __('Deactivate') }}</button>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        {{-- Add location form --}}
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Add Stock Location') }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Code') }}</label>
                    <input wire:model="locationCode" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                    @error('locationCode') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Name') }}</label>
                    <input wire:model="locationName" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Type') }}</label>
                    <select wire:model="locationType" class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach(\App\Modules\Organization\Models\StockLocation::TYPES as $value => $label)
                        <option value="{{ $value }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button wire:click="addStockLocation" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                    <span wire:loading.remove>{{ __('Add Location') }}</span>
                    <span wire:loading>{{ __('Adding...') }}</span>
                </button>
            </div>
        </div>
    </div>
    @endcan
</div>
