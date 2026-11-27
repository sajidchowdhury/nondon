<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Catalog\Services\PriceService;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Picqer\Barcode\Barcode;
use Picqer\Barcode\BarcodeBar;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

/**
 * LabelPrintingService — barcode label generation backend (Session 2.6).
 *
 * Generates Code 128 barcode images and assembles label data for the four
 * supported label formats (WF-2, step 2). Pure backend logic — the printing
 * UI and transport (browser CSS print media) are Session 2.7.
 *
 * Barcode rendering:
 * - Symbology: Code 128 (auto code-set switching) via picqer/php-barcode-generator.
 * - Output: base64-encoded SVG (vector — scales crisply on thermal printers
 *   through the browser print dialog; no GD dependency).
 * - Quiet zones: 10 modules of empty space on both sides of the barcode
 *   (Code 128 minimum per spec: 10x the narrowest bar width).
 *
 * Settings resolution order (per key, prefix `barcode.`):
 *   1. Explicit method parameter (per print job)
 *   2. system_settings DB row (e.g. key `barcode.show_price_on_label`)
 *   3. config/barcode_settings.php default
 *
 * The UI (Session 2.7) writes the DB overrides via the Printer Settings page.
 *
 * NOTE: This service does NOT audit — generating label data is not a
 * business action. The `label.print` audit event is written by the printing
 * UI in Session 2.7 when labels are actually printed (WF-2 step 7).
 */
class LabelPrintingService
{
    /**
     * Quiet zone in modules on each side of the barcode.
     * Code 128 spec minimum: 10x the narrowest bar (module) width.
     */
    protected const QUIET_ZONE_MODULES = 10;

    /**
     * Pixels per module (narrowest bar width) when rendering the SVG.
     */
    protected const WIDTH_FACTOR = 2.0;

    public function __construct(
        protected PriceService $priceService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Label Formats
    |--------------------------------------------------------------------------
    */

    /**
     * The four supported label formats with their physical dimensions
     * and layout specs (docs/13 Session 2.6, deliverable 3).
     *
     * `layout` drives the Session 2.7 print view:
     * - compact:  barcode + serial + product name
     * - standard: barcode + serial + product name + size + optional price
     */
    public function getLabelFormats(): array
    {
        return [
            'hang_tag_small' => [
                'key' => 'hang_tag_small',
                'name' => 'Hang Tag 50×30mm',
                'width_mm' => 50,
                'height_mm' => 30,
                'barcode_height_px' => 40,
                'font_size_pt' => 7,
                'layout' => 'compact',
            ],
            'hang_tag_large' => [
                'key' => 'hang_tag_large',
                'name' => 'Hang Tag 75×50mm',
                'width_mm' => 75,
                'height_mm' => 50,
                'barcode_height_px' => 50,
                'font_size_pt' => 9,
                'layout' => 'standard',
            ],
            'sticker_small' => [
                'key' => 'sticker_small',
                'name' => 'Sticker 1"×1.5" (25.4×38.1mm)',
                'width_mm' => 25.4,
                'height_mm' => 38.1,
                'barcode_height_px' => 35,
                'font_size_pt' => 6,
                'layout' => 'compact',
            ],
            'sticker_large' => [
                'key' => 'sticker_large',
                'name' => 'Sticker 1"×2.625" (25.4×66.7mm)',
                'width_mm' => 25.4,
                'height_mm' => 66.7,
                'barcode_height_px' => 45,
                'font_size_pt' => 8,
                'layout' => 'standard',
            ],
        ];
    }

    /**
     * Get a single label format spec by key.
     *
     * @throws ValidationException when the format key is unknown
     */
    public function getLabelFormat(string $key): array
    {
        $formats = $this->getLabelFormats();

        if (! isset($formats[$key])) {
            throw ValidationException::withMessages([
                'format' => __('Unknown label format: :format', ['format' => $key]),
            ]);
        }

        return $formats[$key];
    }

    /*
    |--------------------------------------------------------------------------
    | System Settings Integration (data model — UI is Session 2.7)
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve a barcode setting: system_settings DB override → config default.
     *
     * @param  string  $key  Setting key without the `barcode.` prefix,
     *                       e.g. 'default_label_format', 'default_printer'
     */
    public function getSetting(string $key, ?string $default = null): ?string
    {
        $override = SystemSetting::getValue('barcode.'.$key);

        if ($override !== null) {
            return $override;
        }

        return config('barcode_settings.'.$key, $default);
    }

    /**
     * Resolve the show-price flag:
     * explicit per-print-job parameter → DB override → config default.
     */
    public function resolveShowPrice(?bool $showPrice = null): bool
    {
        if ($showPrice !== null) {
            return $showPrice;
        }

        $dbValue = SystemSetting::getValue('barcode.show_price_on_label');

        if ($dbValue !== null) {
            return filter_var($dbValue, FILTER_VALIDATE_BOOL);
        }

        return (bool) config('barcode_settings.show_price_on_label', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Barcode Image Generation
    |--------------------------------------------------------------------------
    */

    /**
     * Render a Code 128 barcode image for the given barcode string.
     *
     * Returns a base64-encoded SVG string (no `data:` prefix) with 10-module
     * quiet zones on both sides. For direct HTML embedding, wrap with
     * `data:image/svg+xml;base64,` (generateLabelData does this for you).
     *
     * @param  string  $barcode  The barcode content to encode (e.g. 'NND-25-00001')
     * @param  int  $height  Rendered image height in pixels (default 60)
     * @return string Base64-encoded SVG
     *
     * @throws InvalidArgumentException when the barcode string is empty
     */
    public function generateBarcodeImage(string $barcode, int $height = 60): string
    {
        if (trim($barcode) === '') {
            throw new InvalidArgumentException('Barcode string cannot be empty.');
        }

        // Build the Code 128 (auto code-set) bar structure
        $barcodeData = (new TypeCode128)->getBarcode($barcode);

        // Inject quiet zones (light bars) on both sides so scanners can
        // find the symbol boundaries. Light bars are skipped by the renderer
        // but shift the drawn bars and are included in the total width.
        $padded = new Barcode($barcodeData->getBarcode());
        $padded->addBar(new BarcodeBar(self::QUIET_ZONE_MODULES, $barcodeData->getHeight(), false));
        foreach ($barcodeData->getBars() as $bar) {
            $padded->addBar($bar);
        }
        $padded->addBar(new BarcodeBar(self::QUIET_ZONE_MODULES, $barcodeData->getHeight(), false));

        $totalWidth = $padded->getWidth() * self::WIDTH_FACTOR;
        $svg = (new SvgRenderer)->render($padded, $totalWidth, $height);

        return base64_encode($svg);
    }

    /**
     * Barcode image as an HTML-embeddable data URI for a label format.
     */
    protected function generateBarcodeImageSrc(string $barcode, int $height): string
    {
        return 'data:image/svg+xml;base64,'.$this->generateBarcodeImage($barcode, $height);
    }

    /*
    |--------------------------------------------------------------------------
    | Label Data Assembly
    |--------------------------------------------------------------------------
    */

    /**
     * Assemble the label data for a single garment.
     *
     * Returns: garment_id, internal_id, barcode, barcode_image (data URI),
     * serial, product_name, size_label (null when the product has no size),
     * format (the full format spec). standard_price is included only when
     * the show-price flag resolves to true — labels never include cost.
     *
     * @param  string  $format  One of the getLabelFormats() keys
     * @param  bool|null  $showPrice  Per-print-job override of show_price_on_label
     *
     * @throws ValidationException when the format key is unknown
     */
    public function generateLabelData(Garment $garment, string $format, ?bool $showPrice = null): array
    {
        $formatSpec = $this->getLabelFormat($format);

        $product = $garment->product;
        $size = $garment->productSize;

        $data = [
            'garment_id' => $garment->id,
            'internal_id' => $garment->internal_id,
            'barcode' => $garment->barcode,
            'barcode_image' => $this->generateBarcodeImageSrc(
                $garment->barcode,
                $formatSpec['barcode_height_px'],
            ),
            'serial' => $garment->fiscal_year_serial,
            'product_name' => $product?->name,
            'size_label' => $size?->size_label,
            'format' => $formatSpec,
        ];

        if ($this->resolveShowPrice($showPrice)) {
            $price = $this->priceService->getCurrentPrice($product, $size);
            $data['standard_price'] = $price?->standard_price;
        }

        return $data;
    }

    /**
     * Generate label data for every garment created in a goods receipt.
     *
     * @param  string  $format  One of the getLabelFormats() keys
     * @param  bool|null  $showPrice  Per-print-job override of show_price_on_label
     * @return Collection<int, array> One label data array per garment, ordered by garment id
     *
     * @throws ValidationException when the format key is unknown
     */
    public function generateLabelsForReceipt(GoodsReceipt $receipt, string $format, ?bool $showPrice = null): Collection
    {
        // Validate the format even for an empty receipt so callers fail fast
        $this->getLabelFormat($format);

        return $receipt->garments()
            ->orderBy('id')
            ->get()
            ->map(fn (Garment $garment) => $this->generateLabelData($garment, $format, $showPrice))
            ->values();
    }

    /**
     * Generate label data for a range of garments by ID (inclusive).
     *
     * @param  int  $fromGarmentId  First garment ID in the range
     * @param  int  $toGarmentId  Last garment ID in the range
     * @param  string  $format  One of the getLabelFormats() keys
     * @param  bool|null  $showPrice  Per-print-job override of show_price_on_label
     * @return Collection<int, array> Ordered by garment id ascending
     *
     * @throws ValidationException when the format key is unknown
     */
    public function generateLabelsForRange(int $fromGarmentId, int $toGarmentId, string $format, ?bool $showPrice = null): Collection
    {
        $this->getLabelFormat($format);

        // Accept reversed ranges by normalizing (from > to)
        [$from, $to] = [min($fromGarmentId, $toGarmentId), max($fromGarmentId, $toGarmentId)];

        return Garment::query()
            ->whereBetween('id', [$from, $to])
            ->orderBy('id')
            ->get()
            ->map(fn (Garment $garment) => $this->generateLabelData($garment, $format, $showPrice))
            ->values();
    }
}
