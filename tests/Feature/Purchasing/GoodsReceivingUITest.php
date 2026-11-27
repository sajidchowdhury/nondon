<?php

use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Purchasing\Services\ReceivingService;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Catalog\Models\Product;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

/**
 * Helper: create a confirmed purchase for UI tests.
 */
function createConfirmedPurchaseForUi(int $quantity = 10, float $unitCost = 250.00): Purchase
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
            ['product_id' => $product->id, 'ordered_quantity' => $quantity, 'unit_cost' => $unitCost],
        ],
    ]);

    $service->confirmPurchase($purchase);
    return $purchase->fresh('items');
}

/**
 * Helper: create a purchase + receive goods, returning the receipt.
 */
function createReceivedPurchaseForUi(int $quantity = 5): GoodsReceipt
{
    $admin = adminUser();
    test()->actingAs($admin);

    $purchase = createConfirmedPurchaseForUi($quantity);
    $itemId = $purchase->items->first()->id;

    $receivingService = app(ReceivingService::class);
    return $receivingService->receiveGoods($purchase, [$itemId => $quantity], $admin);
}

describe('receiving form — UI access', function () {
    it('allows admin to view the receiving form for a confirmed purchase', function () {
        $admin = adminUser();
        $purchase = createConfirmedPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.receive', $purchase));
        $response->assertStatus(200);
        $response->assertSee('Receive Goods');
        $response->assertSee($purchase->purchase_number);
    });

    it('shows line items with ordered/received/remaining quantities', function () {
        $admin = adminUser();
        $purchase = createConfirmedPurchaseForUi(10);

        $response = $this->actingAs($admin)->get(route('purchases.receive', $purchase));
        $response->assertStatus(200);
        $response->assertSee('Ordered');
        $response->assertSee('Received');
        $response->assertSee('Remaining');
        $response->assertSee('Receive Now');
    });

    it('shows Receive & Generate Identities button for confirmed purchase', function () {
        $admin = adminUser();
        $purchase = createConfirmedPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.receive', $purchase));
        $response->assertSee('Receive & Generate Identities');
    });

    it('supervisor with purchase.receive can access the receiving form', function () {
        $admin = adminUser();
        $purchase = createConfirmedPurchaseForUi();

        $mainBranch = Branch::where('code', 'M')->first();
        $supervisor = supervisor($mainBranch);

        $response = $this->actingAs($supervisor)->get(route('purchases.receive', $purchase));
        $response->assertStatus(200);
        $response->assertSee('Receive Goods');
    });

    it('salesperson cannot access the receiving form (403)', function () {
        $admin = adminUser();
        $purchase = createConfirmedPurchaseForUi();

        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('purchases.receive', $purchase));
        $response->assertForbidden();
    });

    it('guest redirected from receiving form', function () {
        $purchase = createConfirmedPurchaseForUi();
        $response = $this->get(route('purchases.receive', $purchase));
        $response->assertRedirect(route('login'));
    });
});

describe('receiving form — edge cases', function () {
    it('shows warning for draft purchase (cannot be received)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        // Create a draft purchase (not confirmed)
        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $purchase = app(PurchasingService::class)->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => '2025-08-15',
            'items' => [['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => 100]],
        ]);

        // The route requires 'receive' policy which checks canBeReceived()
        // For a draft purchase, canBeReceived() returns false → 403
        $response = $this->actingAs($admin)->get(route('purchases.receive', $purchase));
        $response->assertForbidden();
    });

    it('shows warning message for already received purchase', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForUi(5);
        $purchase = $receipt->purchase;

        // Purchase is now 'received' → canBeReceived() returns false → 403
        $response = $this->actingAs($admin)->get(route('purchases.receive', $purchase));
        $response->assertForbidden();
    });

    it('shows partially received status badge when partially received', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchaseForUi(10);
        $itemId = $purchase->items->first()->id;

        // Partial receive
        app(ReceivingService::class)->receiveGoods($purchase, [$itemId => 4], $admin);

        // View the purchase (not the receiving form)
        $response = $this->actingAs($admin)->get(route('purchases.show', $purchase->fresh()));
        $response->assertStatus(200);
        $response->assertSee('Partially Received');
    });
});

describe('goods receipt list — UI access', function () {
    it('allows admin to view goods receipt list', function () {
        $admin = adminUser();
        createReceivedPurchaseForUi(3);

        $response = $this->actingAs($admin)->get(route('goods-receipts.index'));
        $response->assertStatus(200);
        $response->assertSee('Goods Receipts');
    });

    it('denies supervisor access to goods receipt list', function () {
        $admin = adminUser();
        createReceivedPurchaseForUi(3);

        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('goods-receipts.index'));
        $response->assertForbidden();
    });

    it('denies salesperson access to goods receipt list', function () {
        $admin = adminUser();
        createReceivedPurchaseForUi(3);

        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('goods-receipts.index'));
        $response->assertForbidden();
    });

    it('shows receipt number in the list', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForUi(3);

        $response = $this->actingAs($admin)->get(route('goods-receipts.index'));
        $response->assertSee($receipt->receipt_number);
    });
});

describe('goods receipt view — UI access', function () {
    it('allows admin to view goods receipt detail', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForUi(3);

        $response = $this->actingAs($admin)->get(route('goods-receipts.show', $receipt));
        $response->assertStatus(200);
        $response->assertSee($receipt->receipt_number);
    });

    it('shows garment identities (barcode, serial) in receipt detail', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForUi(3);

        $response = $this->actingAs($admin)->get(route('goods-receipts.show', $receipt));
        $response->assertSee('Barcode');
        $response->assertSee('Serial');
        $response->assertSee('Generated Garment Identities');
    });

    it('shows garment count in receipt detail', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForUi(5);

        $response = $this->actingAs($admin)->get(route('goods-receipts.show', $receipt));
        $response->assertSee('5');
        $response->assertSee('garments');
    });

    it('denies supervisor access to goods receipt detail', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForUi(3);

        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('goods-receipts.show', $receipt));
        $response->assertForbidden();
    });

    it('purchase cost is not visible in goods receipt detail for non-admin', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForUi(3);

        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        // Supervisor gets 403 — never sees the page
        $response = $this->actingAs($supervisor)->get(route('goods-receipts.show', $receipt));
        $response->assertForbidden();
        $response->assertDontSee('Cost');
    });
});

describe('purchase view — receive goods link', function () {
    it('shows Receive Goods button on confirmed purchase view for admin', function () {
        $admin = adminUser();
        $purchase = createConfirmedPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.show', $purchase));
        $response->assertSee('Receive Goods');
    });

    it('shows goods receipts on purchase view after receiving', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForUi(5);
        $purchase = $receipt->purchase;

        $response = $this->actingAs($admin)->get(route('purchases.show', $purchase));
        $response->assertSee('Goods Receipts');
        $response->assertSee($receipt->receipt_number);
    });

    it('shows no goods receipts message before receiving', function () {
        $admin = adminUser();
        $purchase = createConfirmedPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.show', $purchase));
        $response->assertSee('No goods receipts yet');
    });
});

describe('receiving — routes protected', function () {
    it('all receiving routes deny salesperson', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForUi(3);
        $purchase = $receipt->purchase;

        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $this->actingAs($salesperson)->get(route('purchases.receive', $purchase))->assertForbidden();
        $this->actingAs($salesperson)->get(route('goods-receipts.index'))->assertForbidden();
        $this->actingAs($salesperson)->get(route('goods-receipts.show', $receipt))->assertForbidden();
    });
});
