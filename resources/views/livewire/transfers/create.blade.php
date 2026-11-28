<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('New Transfer') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Request stock from another branch.') }}</p>
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
        <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
    </div>
    @endif

    @if($searchError)
    <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900 p-3">
        <p class="text-sm text-red-800 dark:text-red-200">{{ $searchError }}</p>
    </div>
    @endif

    <form wire:submit="submit" class="space-y-6">
        {{-- Branches --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Source branch') }} <span class="text-red-500">*</span></label>
                    <select wire:model.live="fromBranchId" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                        <option value="0">{{ __('Select...') }}</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->code }} — {{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Destination branch') }} <span class="text-red-500">*</span></label>
                    <select wire:model.live="toBranchId" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
                        <option value="0">{{ __('Select...') }}</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->code }} — {{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Stock picker --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('Select garments') }}</h2>
            <div class="flex space-x-2 mb-3">
                <input wire:model.live.debounce.300ms="productSearch" wire:input.debounce.300ms="searchStock" type="text" placeholder="{{ __('Search product name, code, or barcode...') }}"
                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white">
            </div>

            @if(! empty($searchResults))
            <div class="border border-gray-200 dark:border-gray-700 rounded-md max-h-60 overflow-y-auto mb-4">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($searchResults as $row)
                    <li class="flex items-center justify-between px-3 py-2">
                        <span class="text-sm text-gray-900 dark:text-white">
                            {{ $row['product_name'] }}
                            @if($row['size_label'])<span class="text-gray-500">— {{ $row['size_label'] }}</span>@endif
                            <span class="text-xs text-gray-400 ml-2">{{ $row['barcode'] }}</span>
                        </span>
                        <button type="button" wire:click="addGarment({{ $row['garment_id'] }})"
                                class="text-sm text-emerald-600 hover:text-emerald-900 dark:text-emerald-400">{{ __('Add') }}</button>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if($selectedCount > 0)
            <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Selected garments') }} ({{ $selectedCount }})</p>
                <ul class="space-y-1 max-h-40 overflow-y-auto">
                    @foreach($selectedGarmentIds as $gid)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-gray-900 dark:text-white">{{ __('Garment') }} #{{ $gid }}</span>
                        <button type="button" wire:click="removeGarment({{ $gid }})" class="text-red-600 hover:text-red-900 dark:text-red-400 text-xs">{{ __('Remove') }}</button>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>

        {{-- Notes --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Notes') }}</label>
            <textarea wire:model="notes" rows="2" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white"></textarea>
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('transfers.index') }}" wire:navigate class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </a>
            <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm disabled:opacity-50">
                <span wire:loading.remove>{{ __('Create Transfer') }}</span>
                <span wire:loading>{{ __('Saving...') }}</span>
            </button>
        </div>
    </form>
</div>
