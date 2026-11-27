<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Catalog\Services\PriceService;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SystemSetting;
use App\Modules\Purchasing\Services\LabelPrintingService;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Purchasing\Services\ReceivingService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\Types\TypeCode128;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);

    $this->service = app(LabelPrintingService::class);
});

/**
 * Helper: extract the dark-bar rects (x, width) from a base64-encoded SVG
 * produced by generateBarcodeImage().
 */
function extractSvgBars(string $base64Svg): array
{
    $svg = base64_decode($base64Svg);

    preg_match('/<g id="bars"[^>]*>(.*?)<\/g>/s', $svg, $group);

    preg_match_all(
        '/<rect x="([\d.]+)" y="[\d.]+" width="([\d.]+)" height="[\d.]+" \/>/',
        $group[1] ?? '',
        $rects,
        PREG_SET_ORDER,
    );

    return array_map(fn (array $rect) => [
        'x' => (float) $rect[1],
        'width' => (float) $rect[2],
    ], $rects);
}

/**
 * Helper: extract an attribute (e.g. width, height) from the root <svg> tag.
 */
function extractSvgAttribute(string $base64Svg, string $attribute): ?string
{
    $svg = base64_decode($base64Svg);

    preg_match('/<'.'svg[^>]*'.$attribute.'="([\d.]+)"/', $svg, $match);

    return $match[1] ?? null;
}

/**
 * Helper: full purchasing flow ending in a goods receipt with garments.
 */
function createReceivedReceiptForLabels(int $quantity = 3): GoodsReceipt
{
    $admin = adminUser();
    test()->actingAs($admin);

    $supplier = Supplier::factory()->create();
    $fiscalYear = FiscalYear::active()->first();
    $product = Product::factory()->create();

    $service = app(PurchasingService::class);
    $purchase = $service->createPurchase([
        'supplier_id' => $supplier->id,
        'fiscal_year_id' => $fiscalYear->id,
        'purchase_date' => '2025-08-15',
        'items' => [
            ['product_id' => $product->id, 'ordered_quantity' => $quantity, 'unit_cost' => 250],
        ],
    ]);

    $service->confirmPurchase($purchase);
    $itemId = $purchase->items->first()->id;

    return app(ReceivingService::class)->receiveGoods($purchase, [$itemId => $quantity], $admin);
}

describe('barcode image generation', function () {
    it('generateBarcodeImage returns a valid base64-encoded SVG string', function () {
        $image = $this->service->generateBarcodeImage('NND-25-00001');

        expect($image)->toBeString();

        $svg = base64_decode($image, true);
        expect($svg)->toBeString()->not->toBeFalse();
        expect($svg)->toStartWith('<?xml');
        expect($svg)->toContain('<svg');
        expect($svg)->toContain('<g id="bars"');
    });

    it('barcode image contains the correct Code 128 encoding for the input string', function () {
        $barcodeText = 'NND-25-00001';

        $image = $this->service->generateBarcodeImage($barcodeText);

        // Expected bar structure computed independently via the library's
        // Code 128 type — this verifies our service encodes Code 128 for the
        // exact input, including the injected quiet zones.
        // (Width-0 stop-pattern bars are excluded: the renderer skips them.)
        $expected = (new TypeCode128)->getBarcode($barcodeText);
        $expectedDarkBars = array_values(array_filter(
            $expected->getBars(),
            fn ($bar) => $bar->isBar() && $bar->getWidth() > 0,
        ));

        $bars = extractSvgBars($image);
        $widthFactor = 2.0; // LabelPrintingService::WIDTH_FACTOR
        $quietZonePx = 10 * $widthFactor; // QUIET_ZONE_MODULES * WIDTH_FACTOR

        // Total rendered width = encoded modules + both quiet zones
        expect((float) extractSvgAttribute($image, 'width'))
            ->toEqual(($expected->getWidth() + 20) * $widthFactor);

        // Every dark bar of the Code 128 encoding is present, in order
        expect(count($bars))->toBe(count($expectedDarkBars));

        foreach ($bars as $index => $bar) {
            expect($bar['width'])->toEqualWithDelta($expectedDarkBars[$index]->getWidth() * $widthFactor, 0.01);
        }

        // Left quiet zone: first bar starts at exactly the quiet zone width
        expect($bars[0]['x'])->toEqual($quietZonePx);

        // Right quiet zone: last bar ends before the right quiet zone begins
        $last = end($bars);
        $totalWidth = (float) extractSvgAttribute($image, 'width');
        expect($last['x'] + $last['width'])->toBeLessThan($totalWidth - $quietZonePx + 0.01);
    });

    it('rendered barcode bar structure matches the library Code 128 output for the same content', function () {
        $image = $this->service->generateBarcodeImage('NND-25-00001');

        // The plain library SVG (no quiet zones) for the same content
        $librarySvg = (new BarcodeGeneratorSVG)->getBarcode(
            'NND-25-00001',
            BarcodeGeneratorSVG::TYPE_CODE_128,
        );
        preg_match_all('/<rect /', $librarySvg, $libraryRects);

        $bars = extractSvgBars($image);

        // Same number of dark bars as the library renders for Code 128
        expect(count($bars))->toBe(count($libraryRects[0]));
    });

    it('generation is deterministic for the same input', function () {
        expect($this->service->generateBarcodeImage('NND-25-00001'))
            ->toBe($this->service->generateBarcodeImage('NND-25-00001'));

        // Different input produces different output
        expect($this->service->generateBarcodeImage('NND-25-00001'))
            ->not->toBe($this->service->generateBarcodeImage('NND-25-00002'));
    });

    it('height parameter controls the rendered barcode height', function () {
        $image = $this->service->generateBarcodeImage('NND-25-00001', 35);

        expect(extractSvgAttribute($image, 'height'))->toEqual('35');

        $image = $this->service->generateBarcodeImage('NND-25-00001', 60);

        expect(extractSvgAttribute($image, 'height'))->toEqual('60');
    });

    it('rejects an empty barcode string', function () {
        $this->service->generateBarcodeImage('');
    })->throws(InvalidArgumentException::class);

    it('renders a 14-character barcode (NND-25-00001) without error', function () {
        $image = $this->service->generateBarcodeImage('NND-25-00001');

        expect($image)->toBeString();
        expect(strlen($image))->toBeGreaterThan(100);

        $svg = base64_decode($image);
        expect($svg)->toContain('<desc>NND-25-00001</desc>');
    });
});

describe('label data', function () {
    it('generateLabelData returns all required fields', function () {
        $garment = Garment::factory()->create();
        $label = $this->service->generateLabelData($garment, 'hang_tag_small');

        expect($label)->toHaveKeys([
            'garment_id',
            'internal_id',
            'barcode',
            'barcode_image',
            'serial',
            'product_name',
            'size_label',
            'format',
        ]);

        expect($label['garment_id'])->toBe($garment->id);
        expect($label['internal_id'])->toBe($garment->internal_id);
        expect($label['barcode'])->toBe($garment->barcode);
        expect($label['serial'])->toBe($garment->fiscal_year_serial);
        expect($label['product_name'])->toBe($garment->product->name);
        expect($label['format']['key'])->toBe('hang_tag_small');

        // barcode_image is a directly embeddable data URI with valid SVG
        expect($label['barcode_image'])->toStartWith('data:image/svg+xml;base64,');
        $svg = base64_decode(substr($label['barcode_image'], strlen('data:image/svg+xml;base64,')), true);
        expect($svg)->not->toBeFalse();
        expect($svg)->toContain('<svg');

        // Barcode height matches the format spec
        $labelSvgBase64 = substr($label['barcode_image'], strlen('data:image/svg+xml;base64,'));
        expect(extractSvgAttribute($labelSvgBase64, 'height'))
            ->toEqual((string) $label['format']['barcode_height_px']);
    });

    it('label data includes standard_price when show_price is true', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $size = ProductSize::factory()->forProduct($product)->create();
        app(PriceService::class)->setPrice($product, $size, 499.99, 400.00);

        $garment = Garment::factory()->forProduct($product, $size)->create();
        $label = $this->service->generateLabelData($garment, 'hang_tag_large', showPrice: true);

        expect($label)->toHaveKey('standard_price');
        expect((float) $label['standard_price'])->toEqual(499.99);
    });

    it('label data excludes standard_price when show_price is false', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        app(PriceService::class)->setPrice($product, null, 499.99, 400.00);

        $garment = Garment::factory()->forProduct($product)->create();
        $label = $this->service->generateLabelData($garment, 'hang_tag_large', showPrice: false);

        expect($label)->not->toHaveKey('standard_price');
    });

    it('show_price falls back to config default when not overridden', function () {
        $garment = Garment::factory()->create();

        // Config default is false → no price key
        $label = $this->service->generateLabelData($garment, 'hang_tag_large');
        expect($label)->not->toHaveKey('standard_price');

        // Config overridden to true → price key present (null when no price set)
        config(['barcode_settings.show_price_on_label' => true]);
        $label = $this->service->generateLabelData($garment, 'hang_tag_large');
        expect($label)->toHaveKey('standard_price');
        expect($label['standard_price'])->toBeNull();
    });

    it('show_price uses the system_settings database override when present', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->create();
        $product = Product::factory()->create();
        app(PriceService::class)->setPrice($product, null, 250.00, 200.00);

        // Config says false; DB override says true → price key present
        config(['barcode_settings.show_price_on_label' => false]);
        SystemSetting::setValue('barcode.show_price_on_label', '1');

        $label = $this->service->generateLabelData($garment, 'hang_tag_large');
        expect($label)->toHaveKey('standard_price');

        // DB override '0' wins over config true → price key absent
        config(['barcode_settings.show_price_on_label' => true]);
        SystemSetting::setValue('barcode.show_price_on_label', '0');

        $label = $this->service->generateLabelData($garment, 'hang_tag_large');
        expect($label)->not->toHaveKey('standard_price');
    });

    it('garment with no size still works (size_label is null)', function () {
        $garment = Garment::factory()->create(); // product_size_id is null
        $label = $this->service->generateLabelData($garment, 'sticker_small');

        expect($label['size_label'])->toBeNull();
        expect($label['product_name'])->toBe($garment->product->name);
    });

    it('garment with a size includes the correct size_label', function () {
        $product = Product::factory()->create();
        $size = ProductSize::factory()->forProduct($product)->make(['size_label' => '38']);
        $size->save();

        $garment = Garment::factory()->forProduct($product, $size)->create();
        $label = $this->service->generateLabelData($garment, 'sticker_small');

        expect($label['size_label'])->toBe('38');
    });

    it('unknown label format throws ValidationException', function () {
        $garment = Garment::factory()->create();

        $this->service->generateLabelData($garment, 'business_card');
    })->throws(ValidationException::class);

    it('labels never include purchase cost', function () {
        $garment = Garment::factory()->create(); // has purchase_cost
        $label = $this->service->generateLabelData($garment, 'hang_tag_large', showPrice: true);

        // No cost keys anywhere in the label data (standard_price is the
        // selling price — purchase_cost must never appear, even for admin)
        expect(array_keys($label))->each(fn ($key) => expect($key->value)->not->toContain('cost'));
        expect($label)->not->toHaveKey('purchase_cost');

        $json = json_encode($label);
        expect($json)->not->toContain('purchase_cost');
    });
});

describe('receipt and range generation', function () {
    it('generateLabelsForReceipt returns one label per garment in the receipt', function () {
        $receipt = createReceivedReceiptForLabels(3);

        $labels = $this->service->generateLabelsForReceipt($receipt, 'hang_tag_large');

        expect($labels)->toHaveCount(3);

        $garmentBarcodes = $receipt->garments()->orderBy('id')->pluck('barcode')->toArray();
        $labelBarcodes = $labels->pluck('barcode')->toArray();

        // One label per garment, ordered by garment id
        expect($labelBarcodes)->toBe($garmentBarcodes);
        expect($labels->first()['serial'])->toBe($garmentBarcodes[0] ? $receipt->garments()->orderBy('id')->first()->fiscal_year_serial : null);
    });

    it('generateLabelsForRange returns labels for the correct garment range', function () {
        $garments = Garment::factory()->count(5)->create();
        $ids = $garments->pluck('id')->sort()->values();

        $labels = $this->service->generateLabelsForRange($ids[1], $ids[3], 'sticker_large');

        expect($labels)->toHaveCount(3);
        expect($labels->pluck('garment_id')->toArray())->toBe([$ids[1], $ids[2], $ids[3]]);

        // Reversed range is normalized to the same result
        $reversed = $this->service->generateLabelsForRange($ids[3], $ids[1], 'sticker_large');
        expect($reversed->pluck('garment_id')->toArray())->toBe([$ids[1], $ids[2], $ids[3]]);
    });

    it('receipt and range generation validate the format key', function () {
        $receipt = createReceivedReceiptForLabels(1);

        $this->service->generateLabelsForReceipt($receipt, 'invalid_format');
    })->throws(ValidationException::class);

    it('range generation validates the format key', function () {
        Garment::factory()->count(2)->create();

        $this->service->generateLabelsForRange(1, 2, 'invalid_format');
    })->throws(ValidationException::class);
});

describe('label formats', function () {
    it('getLabelFormats returns all four formats', function () {
        $formats = $this->service->getLabelFormats();

        expect(array_keys($formats))->toBe([
            'hang_tag_small',
            'hang_tag_large',
            'sticker_small',
            'sticker_large',
        ]);
    });

    it('hang tag small format is 50×30mm', function () {
        $format = $this->service->getLabelFormat('hang_tag_small');

        expect($format['name'])->toBe('Hang Tag 50×30mm');
        expect($format['width_mm'])->toEqual(50);
        expect($format['height_mm'])->toEqual(30);
        expect($format['barcode_height_px'])->toEqual(40);
        expect($format['font_size_pt'])->toEqual(7);
        expect($format['layout'])->toBe('compact');
    });

    it('hang tag large format is 75×50mm', function () {
        $format = $this->service->getLabelFormat('hang_tag_large');

        expect($format['name'])->toBe('Hang Tag 75×50mm');
        expect($format['width_mm'])->toEqual(75);
        expect($format['height_mm'])->toEqual(50);
        expect($format['barcode_height_px'])->toEqual(50);
        expect($format['font_size_pt'])->toEqual(9);
        expect($format['layout'])->toBe('standard');
    });

    it('sticker small format is 25.4×38.1mm', function () {
        $format = $this->service->getLabelFormat('sticker_small');

        expect($format['width_mm'])->toEqual(25.4);
        expect($format['height_mm'])->toEqual(38.1);
        expect($format['barcode_height_px'])->toEqual(35);
        expect($format['font_size_pt'])->toEqual(6);
        expect($format['layout'])->toBe('compact');
    });

    it('sticker large format is 25.4×66.7mm', function () {
        $format = $this->service->getLabelFormat('sticker_large');

        expect($format['width_mm'])->toEqual(25.4);
        expect($format['height_mm'])->toEqual(66.7);
        expect($format['barcode_height_px'])->toEqual(45);
        expect($format['font_size_pt'])->toEqual(8);
        expect($format['layout'])->toBe('standard');
    });
});

describe('system settings integration', function () {
    it('barcode settings config group has all four defaults', function () {
        expect(config('barcode_settings.default_label_format'))->toBe('hang_tag_small');
        expect(config('barcode_settings.default_printer'))->toBe('Xprinter XP-420B');
        expect(config('barcode_settings.show_price_on_label'))->toBeFalse();
        expect(config('barcode_settings.barcode_symbology'))->toBe('CODE128');
    });

    it('getSetting returns the config default without a database override', function () {
        expect($this->service->getSetting('default_label_format'))->toBe('hang_tag_small');
        expect($this->service->getSetting('default_printer'))->toBe('Xprinter XP-420B');
        expect($this->service->getSetting('non_existent', 'fallback'))->toBe('fallback');
    });

    it('getSetting prefers the system_settings database override', function () {
        SystemSetting::setValue('barcode.default_label_format', 'sticker_large');

        expect($this->service->getSetting('default_label_format'))->toBe('sticker_large');

        // Deleting the override falls back to config
        SystemSetting::query()->forKey('barcode.default_label_format')->delete();

        expect($this->service->getSetting('default_label_format'))->toBe('hang_tag_small');
    });

    it('system setting changes are audit-logged', function () {
        SystemSetting::setValue('barcode.default_printer', 'Rongta RP400H');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system_setting.create',
            'reference_id' => SystemSetting::query()->forKey('barcode.default_printer')->first()->id,
        ]);

        $setting = SystemSetting::query()->forKey('barcode.default_printer')->first();
        $setting->update(['value' => 'G-Printer GP-3120TUC']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system_setting.update',
            'reference_id' => $setting->id,
        ]);
    });
});
