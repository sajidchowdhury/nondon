<?php

/*
|--------------------------------------------------------------------------
| Barcode Settings
|--------------------------------------------------------------------------
|
| Configuration defaults for barcode label generation (Session 2.6).
|
| These values are the application defaults. They can be overridden at
| runtime via the `system_settings` database table (key prefix `barcode.`)
| — LabelPrintingService resolves settings in this order:
|
|   1. Explicit parameter passed to the service method (per print job)
|   2. Database override (system_settings row, e.g. key `barcode.default_label_format`)
|   3. This config file (defaults below)
|
| The Printer Settings UI (Session 2.7) writes the database overrides.
|
| Supported printers (per BRD §14): Xprinter XP-420B, G-Printer GP-3120TUC,
| Rongta RP400H. Printing transport is browser CSS print media (ADR-019).
|
*/

return [

    // One of: hang_tag_small, hang_tag_large, sticker_small, sticker_large
    'default_label_format' => env('NONDON_DEFAULT_LABEL_FORMAT', 'hang_tag_small'),

    // Printer name/model used by the browser print dialog by default
    'default_printer' => env('NONDON_DEFAULT_PRINTER', 'Xprinter XP-420B'),

    // Whether to include the standard price on labels by default
    'show_price_on_label' => env('NONDON_SHOW_PRICE_ON_LABEL', false),

    // Barcode symbology — Code 128 (per WF-2: "Code 128 or similar")
    'barcode_symbology' => env('NONDON_BARCODE_SYMBLOGY', 'CODE128'),

];
