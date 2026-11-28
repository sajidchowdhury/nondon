<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Organization\Models\Branch;
use App\Modules\Sales\Http\Livewire\POS\PrintReceipt;
use App\Modules\Sales\Models\SaleInvoice;
use App\Modules\Sales\Services\ReceiptService;
use App\Modules\Sales\Services\SaleService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/**
 * Create a completed SaleInvoice (via SaleService::complete) for a single
 * garment with an optional customer + discount, returning the invoice.
 */
function completedInvoice(Branch $branch, User $salesperson, float $standard = 1000, float $minimum = 500, float $discount = 0, ?Customer $customer = null): SaleInvoice
{
    $product = Product::factory()->create(['name' => 'Test Dress']);
    $size = ProductSize::factory()->create(['product_id' => $product->id, 'size_label' => 'M']);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'product_size_id' => null,
        'standard_price' => $standard,
        'minimum_price' => $minimum,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);
    $garment = Garment::factory()->available()->atBranch($branch)->forProduct($product, $size)->create();
    GarmentCurrentStock::where('garment_id', $garment->id)
        ->update(['current_branch_id' => $branch->id, 'current_status' => Garment::STATUS_AVAILABLE]);

    Auth::login($salesperson);
    $payload = [
        'sale_type' => 'retail',
        'items' => [['garment_id' => $garment->id, 'discount' => $discount]],
        'payments' => [['method' => 'cash', 'amount' => $standard - $discount]],
    ];
    if ($customer) {
        $payload['customer_id'] = $customer->id;
    } else {
        $payload['customer_phone'] = '01711110000';
    }

    return app(SaleService::class)->complete($salesperson, $branch, $payload);
}

/*
|--------------------------------------------------------------------------
| Receipt data + rendering — all three formats
|--------------------------------------------------------------------------
*/
describe('receipt rendering — all three formats', function () {
    it('renders 80mm, 58mm, and A4 without errors for a completed invoice', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);

        $service = app(ReceiptService::class);
        foreach (['80mm', '58mm', 'a4'] as $format) {
            $html = (string) $service->render($invoice, $format);
            expect(strlen($html))->toBeGreaterThan(0, "format {$format} rendered empty");
        }
    });

    it('contains invoice number, branch name, salesperson, line items, totals, and payment breakdown', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson, 1500, 1000, 100);

        $html = (string) app(ReceiptService::class)->render($invoice, '80mm');

        expect($html)->toContain($invoice->invoice_number)
            ->and($html)->toContain($branch->name)
            ->and($html)->toContain($salesperson->full_name)
            ->and($html)->toContain('Test Dress')
            ->and($html)->toContain('Subtotal')
            ->and($html)->toContain('Total')
            ->and($html)->toContain('Cash');
    });

    it('includes @page sizing directives for each format', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);

        $html80 = (string) app(ReceiptService::class)->render($invoice, '80mm');
        $html58 = (string) app(ReceiptService::class)->render($invoice, '58mm');
        $htmlA4 = (string) app(ReceiptService::class)->render($invoice, 'a4');

        expect($html80)->toContain('@page receipt-80mm')
            ->and($html80)->toContain('size: 80mm auto')
            ->and($html58)->toContain('@page receipt-58mm')
            ->and($html58)->toContain('size: 58mm auto')
            ->and($htmlA4)->toContain('@page receipt-a4')
            ->and($htmlA4)->toContain('size: A4');
    });
});

/*
|--------------------------------------------------------------------------
| ADR-010 compliance — no tax/VAT line
|--------------------------------------------------------------------------
*/
describe('ADR-010 — no tax/VAT line', function () {
    it('does not contain VAT, Tax, or ট্যাক্স in any receipt format', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);

        foreach (['80mm', '58mm', 'a4'] as $format) {
            $html = strtolower((string) app(ReceiptService::class)->render($invoice, $format));
            expect($html)->not->toContain('vat')
                ->and($html)->not->toContain('tax')
                ->and($html)->not->toContain('ট্যাক্স')
                ->and($html)->not->toContain('gst', "format {$format} must not contain a tax line");
        }
    });
});

/*
|--------------------------------------------------------------------------
| Sensitive data — purchase_cost never on a receipt
|--------------------------------------------------------------------------
*/
describe('sensitive data — purchase_cost hidden', function () {
    it('does not expose purchase_cost on any receipt format', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        // Create a garment WITH a purchase_cost — it must never render.
        $product = Product::factory()->create(['name' => 'Costly Dress']);
        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'product_size_id' => null,
            'standard_price' => 1000,
            'minimum_price' => 500,
            'effective_from' => now()->subDay(),
            'effective_to' => null,
        ]);
        $garment = Garment::factory()->available()->atBranch($branch)->forProduct($product)->create([
            'purchase_cost' => 250.00,
        ]);
        GarmentCurrentStock::where('garment_id', $garment->id)
            ->update(['current_branch_id' => $branch->id, 'current_status' => Garment::STATUS_AVAILABLE]);
        Auth::login($salesperson);
        $invoice = app(SaleService::class)->complete($salesperson, $branch, [
            'sale_type' => 'retail',
            'items' => [['garment_id' => $garment->id]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
            'customer_phone' => '01722220000',
        ]);

        foreach (['80mm', '58mm', 'a4'] as $format) {
            $html = (string) app(ReceiptService::class)->render($invoice, $format);
            expect($html)->not->toContain('250.00', "format {$format} must not show purchase_cost")
                ->and($html)->not->toContain('purchase_cost');
        }
    });
});

/*
|--------------------------------------------------------------------------
| Return-policy footer uses the sale-time snapshot
|--------------------------------------------------------------------------
*/
describe('return-policy footer', function () {
    it('shows the snapshot return_period_days (default 7)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);

        $html = (string) app(ReceiptService::class)->render($invoice, '80mm');
        expect($html)->toContain('7')
            ->and($html)->toContain('days');
    });

    it('reflects a custom snapshot return_period_days', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);
        // Mutate the snapshot to a custom value (simulating a future policy change).
        $invoice->update(['return_policy_snapshot' => ['return_period_days' => 14]]);

        $html = (string) app(ReceiptService::class)->render($invoice->fresh(), '80mm');
        expect($html)->toContain('14 days')
            ->and($html)->not->toContain('7 days');
    });
});

/*
|--------------------------------------------------------------------------
| Walk-in sale (no customer) renders without a customer block
|--------------------------------------------------------------------------
*/
describe('walk-in sale', function () {
    it('renders without a customer block when no customer is linked', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $product = Product::factory()->create(['name' => 'Walkin Dress']);
        ProductPrice::factory()->create([
            'product_id' => $product->id, 'product_size_id' => null,
            'standard_price' => 1000, 'minimum_price' => 500,
            'effective_from' => now()->subDay(), 'effective_to' => null,
        ]);
        $garment = Garment::factory()->available()->atBranch($branch)->forProduct($product)->create();
        GarmentCurrentStock::where('garment_id', $garment->id)
            ->update(['current_branch_id' => $branch->id, 'current_status' => Garment::STATUS_AVAILABLE]);
        Auth::login($salesperson);
        $invoice = app(SaleService::class)->complete($salesperson, $branch, [
            'sale_type' => 'retail',
            'items' => [['garment_id' => $garment->id]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
            // no customer_id, no customer_phone → null customer (true walk-in)
        ]);

        expect($invoice->customer_id)->toBeNull();

        $html = (string) app(ReceiptService::class)->render($invoice, '80mm');
        expect($html)->toContain($invoice->invoice_number)
            ->and($html)->toContain('Walkin Dress')
            ->and($html)->not->toContain('Customer'); // no customer block
    });
});

/*
|--------------------------------------------------------------------------
| Print route + policy — only completed, branch-scoped
|--------------------------------------------------------------------------
*/
describe('print route — authorization + completion', function () {
    it('renders the receipt HTML for a completed invoice (200)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);

        $response = $this->actingAs($salesperson)->get(route('sales.receipts.print', ['invoice' => $invoice->id, 'format' => '80mm']));
        $response->assertStatus(200);
        $response->assertSee($invoice->invoice_number);
    });

    it('refuses to print a draft (non-completed) invoice — redirects with an error', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = SaleInvoice::factory()->draft()->forBranch($branch)->bySalesperson($salesperson)->create();
        // Admin passes the view policy (no date/branch restriction) but the
        // controller's isCompleted() check rejects drafts.
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('sales.receipts.print', ['invoice' => $invoice->id]));
        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHas('error');
    });

    it('is 403 for a salesperson viewing another branch\'s completed invoice', function () {
        $b1 = Branch::where('code', 'B1')->first();
        $b2 = Branch::where('code', 'B2')->first();
        $salespersonB1 = salesperson($b1);
        $salespersonB2 = salesperson($b2);
        // Invoice completed at B2 by B2's salesperson.
        $invoice = completedInvoice($b2, $salespersonB2);

        // B1's salesperson cannot view B2's invoice (SaleInvoicePolicy::view — own branch only).
        $response = $this->actingAs($salespersonB1)->get(route('sales.receipts.print', ['invoice' => $invoice->id]));
        $response->assertForbidden();
    });

    it('admin can print any branch\'s completed invoice', function () {
        $b1 = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($b1);
        $invoice = completedInvoice($b1, $salesperson);
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('sales.receipts.print', ['invoice' => $invoice->id, 'format' => 'a4']));
        $response->assertStatus(200);
        $response->assertSee($invoice->invoice_number);
    });

    it('redirects a guest to login', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);

        // completedInvoice logs in the salesperson; reset to a guest before the request.
        $this->app['auth']->guard('web')->logout();

        $response = $this->get(route('sales.receipts.print', ['invoice' => $invoice->id]));
        $response->assertRedirect(route('login'));
    });
});

/*
|--------------------------------------------------------------------------
| ReceiptService — getAvailableFormats
|--------------------------------------------------------------------------
*/
describe('ReceiptService — formats', function () {
    it('returns the three available formats', function () {
        $formats = app(ReceiptService::class)->getAvailableFormats();
        $keys = array_column($formats, 'key');
        expect($keys)->toBe(['80mm', '58mm', 'a4'])
            ->and($formats[0])->toHaveKeys(['key', 'label']);
    });

    it('defaults to 80mm when an unknown format is requested', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);

        $html = (string) app(ReceiptService::class)->render($invoice, 'unknown-format');
        // The 80mm wrapper class is present (the default fallback).
        expect($html)->toContain('receipt-80mm');
    });
});

/*
|--------------------------------------------------------------------------
| PrintReceipt Livewire component
|--------------------------------------------------------------------------
*/
describe('PrintReceipt Livewire component', function () {
    it('renders for a completed invoice owned by the acting user', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);

        Livewire::actingAs($salesperson)
            ->test(PrintReceipt::class, ['invoiceId' => $invoice->id])
            ->assertStatus(200)
            ->assertSee('Print Receipt')
            ->assertSee($invoice->invoice_number)
            ->assertSee('Print');
    });

    it('exposes the print URL with the selected format', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $invoice = completedInvoice($branch, $salesperson);

        Livewire::actingAs($salesperson)
            ->test(PrintReceipt::class, ['invoiceId' => $invoice->id])
            ->set('format', 'a4')
            ->assertSee(route('sales.receipts.print', ['invoice' => $invoice->id, 'format' => 'a4']));
    });

    it('is denied for a salesperson of another branch (policy view)', function () {
        $b1 = Branch::where('code', 'B1')->first();
        $b2 = Branch::where('code', 'B2')->first();
        $salespersonB1 = salesperson($b1);
        $salespersonB2 = salesperson($b2);
        $invoice = completedInvoice($b2, $salespersonB2);

        Livewire::actingAs($salespersonB1)
            ->test(PrintReceipt::class, ['invoiceId' => $invoice->id])
            ->assertForbidden();
    });
});
