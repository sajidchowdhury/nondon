<div class="space-y-6 max-w-2xl">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Printer Settings') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Configure the default label format, printer and price visibility for barcode labels.') }}
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-md bg-green-50 dark:bg-green-900 p-4">
            <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5">
        <div>
            <label for="defaultFormat" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Default Label Format') }}
            </label>
            <select id="defaultFormat" wire:model="defaultFormat"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                @foreach($formats as $formatOption)
                    <option value="{{ $formatOption['key'] }}">{{ $formatOption['name'] }}</option>
                @endforeach
            </select>
            @error('defaultFormat') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="defaultPrinter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Default Printer') }}
            </label>
            <select id="defaultPrinter" wire:model="defaultPrinter"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                @foreach($printers as $printerOption)
                    <option value="{{ $printerOption }}">{{ $printerOption }}</option>
                @endforeach
            </select>
            @error('defaultPrinter') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        @if($defaultPrinter === 'Other')
            <div>
                <label for="otherPrinter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Printer Name') }}
                </label>
                <input id="otherPrinter" type="text" wire:model="otherPrinter"
                       placeholder="{{ __('e.g. Zebra ZT230') }}"
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                @error('otherPrinter') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model="showPrice"
                       class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 dark:border-gray-600 rounded">
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Show price on label by default') }}</span>
            </label>
        </div>

        <div>
            <label for="symbology" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('Barcode Symbology') }}
            </label>
            <select id="symbology" wire:model="symbology"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-700 dark:text-white text-sm">
                <option value="CODE128">CODE128</option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Code 128 is the supported symbology for garment barcodes.') }}
            </p>
        </div>

        <div>
            <button type="button" wire:click="save"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                {{ __('Save Settings') }}
            </button>
        </div>
    </div>
</div>
