<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Purchasing\Http\Livewire\Labels\PrintLabels;
use App\Modules\Purchasing\Http\Livewire\Settings\PrinterSettings;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SystemSetting;
use App\Modules\Purchasing\Services\LabelPrintingService;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Purchasing\Services\ReceivingService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

/**
 * Helper: create a goods receipt with garment identities for label tests.
 */
function createLabelReceipt(int $quantity = 3): GoodsReceipt
{
    $admin = adminUser();
    test()->actingAs($admin);

    $supplier = Supplier::factory()->create();
    $fiscalYear = FiscalYear::active()->first();
    $product = Product::factory()->create();

    $purchase = app(PurchasingService::class)->createPurchase([
        'supplier_id' => $supplier->id,
        'fiscal_year_id' => $fiscalYear->id,
        'purchase_date' => '2025-08-15',
        'items' => [
            ['product_id' => $product->id, 'ordered_quantity' => $quantity, 'unit_cost' => 250.00],
        ],
    ]);

    app(PurchasingService::class)->confirmPurchase($purchase);

    $itemId = $purchase->items->first()->id;

    return app(ReceivingService::class)->receiveGoods(
        $purchase->fresh('items'),
        [$itemId => $quantity],
        $admin
    );
}

describe('print labels page — access', function () {
    it('allows admin to access the print labels page', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('labels.print'));
        $response->assertStatus(200);
        $response->assertSee('Print Labels');
    });

    it('shows the receipt selector and garment range selector', function () {
        $admin = adminUser();
        $receipt = createLabelReceipt(2);

        $response = $this->actingAs($admin)->get(route('labels.print'));
        $response->assertStatus(200);
        $response->assertSee('By Goods Receipt');
        $response->assertSee('By Garment ID Range');
        $response->assertSee($receipt->receipt_number);
    });

    it('pre-selects the receipt when ?receipt={id} is provided', function () {
        $receipt = createLabelReceipt(2);
        $admin = adminUser();

        // The full-page component receives the ?receipt query parameter in mount()
        Livewire::actingAs($admin)
            ->test(PrintLabels::class, ['receipt' => $receipt->id])
            ->assertSet('selectionMode', 'receipt')
            ->assertSet('receiptId', $receipt->id);

        $response = $this->actingAs($admin)->get(route('labels.print', ['receipt' => $receipt->id]));
        $response->assertStatus(200);
    });

    it('allows supervisor to access the print labels page', function () {
        $receipt = createLabelReceipt(2);
        $supervisor = supervisor(Branch::where('code', 'M')->first());

        $response = $this->actingAs($supervisor)->get(route('labels.print'));
        $response->assertStatus(200);
        $response->assertSee('Print Labels');
    });

    it('denies salesperson access to the print labels page (403)', function () {
        createLabelReceipt(2);
        $salesperson = salesperson(Branch::where('code', 'B1')->first());

        $response = $this->actingAs($salesperson)->get(route('labels.print'));
        $response->assertForbidden();
    });

    it('redirects guest to login', function () {
        $receipt = createLabelReceipt(2);
        // The helper authenticates as admin to build the receipt; log out
        // so this test exercises the guest path.
        auth()->logout();
        $this->assertGuest();

        $response = $this->get(route('labels.print'));
        $response->assertRedirect(route('login'));
    });
});

describe('label preview', function () {
    it('loads its garments when a receipt is selected', function () {
        $receipt = createLabelReceipt(3);
        $garment = $receipt->garments()->first();
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrintLabels::class)
            ->set('receiptId', $receipt->id)
            ->call('loadPreview')
            ->assertOk()
            ->assertSee($garment->barcode)
            ->assertSee($garment->fiscal_year_serial);
    });

    it('preview shows barcode image and text fields', function () {
        $receipt = createLabelReceipt(2);
        $garment = $receipt->garments()->first();
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrintLabels::class)
            ->set('receiptId', $receipt->id)
            ->call('loadPreview')
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee($garment->fiscal_year_serial)
            ->assertSee($garment->product->name);
    });

    it('format selector shows all four formats', function () {
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrintLabels::class)
            ->assertOk()
            ->assertSee('Hang Tag 50×30mm')
            ->assertSee('Hang Tag 75×50mm')
            ->assertSee('Sticker 1"×1.5" (25.4×38.1mm)')
            ->assertSee('Sticker 1"×2.625" (25.4×66.7mm)');
    });

    it('label data includes standard_price only when show price is enabled', function () {
        $receipt = createLabelReceipt(2);
        $garment = $receipt->garments()->first();
        ProductPrice::factory()->forProduct($garment->product)->create(['standard_price' => 899.00]);
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrintLabels::class)
            ->set('receiptId', $receipt->id)
            ->set('showPrice', true)
            ->call('loadPreview')
            ->assertOk()
            ->assertSee('899.00')
            ->assertSee('data:image/svg+xml;base64,', false);

        // And without the toggle the price is excluded entirely
        Livewire::actingAs($admin)
            ->test(PrintLabels::class)
            ->set('receiptId', $receipt->id)
            ->set('showPrice', false)
            ->call('loadPreview')
            ->assertOk()
            ->assertDontSee('899.00');
    });

    it('preview updates when the format changes', function () {
        $receipt = createLabelReceipt(2);
        $admin = adminUser();

        $component = Livewire::actingAs($admin)
            ->test(PrintLabels::class)
            ->set('receiptId', $receipt->id)
            ->set('format', 'hang_tag_small')
            ->call('loadPreview');

        expect($component->instance()->previewLabels[0]['format']['key'])->toBe('hang_tag_small');

        $component->set('format', 'sticker_large')
            ->call('loadPreview');

        expect($component->instance()->previewLabels[0]['format']['key'])->toBe('sticker_large');
        expect($component->instance()->previewLabels[0]['format']['width_mm'])->toBe(25.4);
    });

    it('preview never includes purchase cost', function () {
        $receipt = createLabelReceipt(2); // purchase unit cost 250.00
        $admin = adminUser();

        $component = Livewire::actingAs($admin)
            ->test(PrintLabels::class)
            ->set('receiptId', $receipt->id)
            ->set('showPrice', true)
            ->call('loadPreview')
            ->assertDontSee('250.00');

        foreach ($component->instance()->previewLabels as $label) {
            expect($label)->not->toHaveKey('purchase_cost');
            expect($label)->not->toHaveKey('unit_cost');
            expect(json_encode($label))->not->toContain('250.00');
        }
    });

    it('loads garments by ID range', function () {
        $receipt = createLabelReceipt(4);
        $ids = $receipt->garments()->orderBy('id')->pluck('id')->all();
        $admin = adminUser();

        $component = Livewire::actingAs($admin)
            ->test(PrintLabels::class)
            ->set('selectionMode', 'range')
            ->set('fromGarmentId', $ids[0])
            ->set('toGarmentId', $ids[count($ids) - 1])
            ->call('loadPreview')
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64,', false);

        expect(count($component->instance()->previewLabels))->toBe(4);
    });
});

describe('print sheet', function () {
    it('renders the correct @page size for hang tag small', function () {
        $receipt = createLabelReceipt(2);
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'hang_tag_small',
            'show_price' => '0',
        ]));
        $response->assertOk();
        $response->assertSee('@page hang-tag-small', false);
        $response->assertSee('size: 50mm 30mm', false);
    });

    it('renders the correct @page size for hang tag large', function () {
        $receipt = createLabelReceipt(2);
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'hang_tag_large',
            'show_price' => '0',
        ]));
        $response->assertOk();
        $response->assertSee('size: 75mm 50mm', false);
    });

    it('renders the correct @page size for sticker small', function () {
        $receipt = createLabelReceipt(2);
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'sticker_small',
            'show_price' => '0',
        ]));
        $response->assertOk();
        $response->assertSee('size: 25.4mm 38.1mm', false);
    });

    it('renders the correct @page size for sticker large', function () {
        $receipt = createLabelReceipt(2);
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'sticker_large',
            'show_price' => '0',
        ]));
        $response->assertOk();
        $response->assertSee('size: 25.4mm 66.7mm', false);
    });

    it('uses the print layout without navigation or sidebar', function () {
        $receipt = createLabelReceipt(2);
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'hang_tag_small',
            'show_price' => '0',
        ]));
        $response->assertOk();
        // The app layout chrome (nav bar, logout, dashboard link) is absent
        $response->assertDontSee('Logout');
        $response->assertDontSee('Dashboard');
        // The print layout markers are present
        $response->assertSee('@page', false);
        $response->assertSee('label-block', false);
    });

    it('renders barcode images and serials for the receipt garments', function () {
        $receipt = createLabelReceipt(3);
        $garments = $receipt->garments()->orderBy('id')->get();
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'hang_tag_large',
            'show_price' => '0',
        ]));
        $response->assertOk();
        $response->assertSee('data:image/svg+xml;base64,', false);

        foreach ($garments as $garment) {
            $response->assertSee($garment->fiscal_year_serial);
        }
    });

    it('shows the standard price on the sheet when show price is enabled', function () {
        $receipt = createLabelReceipt(2);
        $garment = $receipt->garments()->first();
        ProductPrice::factory()->forProduct($garment->product)->create(['standard_price' => 899.00]);
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'hang_tag_large',
            'show_price' => '1',
        ]));
        $response->assertOk();
        $response->assertSee('899.00');

        // And never shows the purchase cost
        $response->assertDontSee('250.00');
    });

    it('logs a label.print audit event with format and count', function () {
        $receipt = createLabelReceipt(3);
        $admin = adminUser();

        $this->actingAs($admin)->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'hang_tag_small',
            'show_price' => '0',
        ]))->assertOk();

        $log = AuditLog::query()->where('action', 'label.print')->orderBy('id', 'desc')->first();

        expect($log)->not->toBeNull();
        expect($log->category)->toBe('purchasing');
        expect($log->reference_type)->toBe('goods_receipt');
        expect($log->reference_id)->toBe($receipt->id);
        expect($log->new_values['format'])->toBe('hang_tag_small');
        expect($log->new_values['count'])->toBe(3);
    });

    it('denies salesperson access to the print sheet (403)', function () {
        createLabelReceipt(2);
        $salesperson = salesperson(Branch::where('code', 'B1')->first());

        $response = $this->actingAs($salesperson)->get(route('labels.print-sheet', [
            'receipt' => 1,
            'format' => 'hang_tag_small',
        ]));
        $response->assertForbidden();
    });

    it('redirects back to the print page when no selection is provided', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('labels.print-sheet', [
            'format' => 'hang_tag_small',
        ]));
        $response->assertRedirect(route('labels.print'));
    });
});

describe('printer settings — access', function () {
    it('allows admin to view printer settings', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('settings.printers'));
        $response->assertStatus(200);
        $response->assertSee('Printer Settings');
    });

    it('denies supervisor access to printer settings (403)', function () {
        $supervisor = supervisor(Branch::where('code', 'B1')->first());

        $response = $this->actingAs($supervisor)->get(route('settings.printers'));
        $response->assertForbidden();
    });

    it('denies salesperson access to printer settings (403)', function () {
        $salesperson = salesperson(Branch::where('code', 'B1')->first());

        $response = $this->actingAs($salesperson)->get(route('settings.printers'));
        $response->assertForbidden();
    });
});

describe('printer settings — save', function () {
    it('saves the default label format', function () {
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrinterSettings::class)
            ->set('defaultFormat', 'hang_tag_large')
            ->call('save')
            ->assertOk();

        expect(SystemSetting::getValue('barcode.default_label_format'))->toBe('hang_tag_large');
        expect(app(LabelPrintingService::class)->getSetting('default_label_format'))->toBe('hang_tag_large');
        $this->assertDatabaseHas('system_settings', [
            'key' => 'barcode.default_label_format',
            'value' => 'hang_tag_large',
        ]);
    });

    it('saves the default printer', function () {
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrinterSettings::class)
            ->set('defaultPrinter', 'Rongta RP400H')
            ->call('save')
            ->assertOk();

        expect(SystemSetting::getValue('barcode.default_printer'))->toBe('Rongta RP400H');
    });

    it('saves a custom printer name when Other is selected', function () {
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrinterSettings::class)
            ->set('defaultPrinter', 'Other')
            ->set('otherPrinter', 'Zebra ZT230')
            ->call('save')
            ->assertOk();

        expect(SystemSetting::getValue('barcode.default_printer'))->toBe('Zebra ZT230');
    });

    it('saves the show price on label toggle', function () {
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrinterSettings::class)
            ->set('showPrice', true)
            ->call('save')
            ->assertOk();

        expect(SystemSetting::getValue('barcode.show_price_on_label'))->toBe('1');
        expect(app(LabelPrintingService::class)->resolveShowPrice())->toBeTrue();

        Livewire::actingAs($admin)
            ->test(PrinterSettings::class)
            ->set('showPrice', false)
            ->call('save')
            ->assertOk();

        expect(SystemSetting::getValue('barcode.show_price_on_label'))->toBe('0');
        expect(app(LabelPrintingService::class)->resolveShowPrice())->toBeFalse();
    });

    it('rejects an unknown label format', function () {
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrinterSettings::class)
            ->set('defaultFormat', 'bogus_format')
            ->call('save')
            ->assertHasErrors(['defaultFormat']);

        expect(SystemSetting::getValue('barcode.default_label_format'))->toBeNull();
    });

    it('print page uses the saved default format on next visit', function () {
        $admin = adminUser();

        Livewire::actingAs($admin)
            ->test(PrinterSettings::class)
            ->set('defaultFormat', 'sticker_large')
            ->set('defaultPrinter', 'Xprinter XP-420B')
            ->call('save');

        Livewire::actingAs($admin)
            ->test(PrintLabels::class)
            ->assertSet('format', 'sticker_large');
    });
});
