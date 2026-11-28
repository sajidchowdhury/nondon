<?php

namespace App\Modules\Purchasing\Http\Livewire\Settings;

use App\Modules\Purchasing\Models\SystemSetting;
use App\Modules\Purchasing\Services\LabelPrintingService;
use Livewire\Component;

class PrinterSettings extends Component
{
    /**
     * Printer models supported by the shops (BRD §14) plus the generic
     * "Other" option for any other printer name.
     *
     * @var list<string>
     */
    public const PRINTERS = [
        'Xprinter XP-420B',
        'G-Printer GP-3120TUC',
        'Rongta RP400H',
        'Other',
    ];

    public string $defaultFormat = '';

    public string $defaultPrinter = '';

    public ?string $otherPrinter = null;

    public bool $showPrice = false;

    public string $symbology = 'CODE128';

    public function mount(): void
    {
        $this->authorize('manage-printer-settings');

        $service = app(LabelPrintingService::class);

        $this->defaultFormat = (string) $service->getSetting(
            'default_label_format',
            (string) config('barcode_settings.default_label_format')
        );
        $this->defaultPrinter = (string) $service->getSetting(
            'default_printer',
            (string) config('barcode_settings.default_printer')
        );
        $this->showPrice = $service->resolveShowPrice();
        $this->symbology = (string) $service->getSetting(
            'barcode_symbology',
            (string) config('barcode_settings.barcode_symbology')
        );

        // A previously saved custom printer name shows via the "Other" option
        if (! in_array($this->defaultPrinter, self::PRINTERS, true)) {
            $this->otherPrinter = $this->defaultPrinter;
            $this->defaultPrinter = 'Other';
        }
    }

    public function save(LabelPrintingService $service): void
    {
        $this->authorize('manage-printer-settings');

        $this->validate([
            'defaultFormat' => ['required', 'string', 'in:'.implode(',', array_keys($service->getLabelFormats()))],
            'defaultPrinter' => ['required', 'string', 'in:'.implode(',', self::PRINTERS)],
            'symbology' => ['required', 'string', 'in:CODE128'],
        ], [
            'defaultFormat.in' => __('The selected label format is not supported.'),
            'defaultPrinter.in' => __('The selected printer is not supported.'),
            'symbology.in' => __('Only the CODE128 symbology is supported.'),
        ]);

        $printer = $this->defaultPrinter;
        if ($printer === 'Other') {
            $this->validate(['otherPrinter' => ['required', 'string', 'max:100']]);

            $printer = trim((string) $this->otherPrinter);
        }

        // Each upsert is audit-logged by the SystemSetting Auditable trait
        // (actions system_setting.create / system_setting.update).
        SystemSetting::setValue('barcode.default_label_format', $this->defaultFormat);
        SystemSetting::setValue('barcode.default_printer', $printer);
        SystemSetting::setValue('barcode.show_price_on_label', $this->showPrice ? '1' : '0');
        SystemSetting::setValue('barcode.barcode_symbology', $this->symbology);

        session()->flash('success', __('Printer settings saved.'));
    }

    public function render(LabelPrintingService $service)
    {
        return view('livewire.settings.printer', [
            'formats' => $service->getLabelFormats(),
            'printers' => self::PRINTERS,
        ])->layout('layouts.app');
    }
}
