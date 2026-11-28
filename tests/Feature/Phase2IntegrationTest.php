<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Purchasing\Services\ReceivingService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

/**
 * Helper: create a confirmed purchase with the given quantity.
 */
function createConfirmedPurchaseForIntegration(int $quantity = 10, float $unitCost = 250.00): Purchase
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
 * Helper: create a purchase and receive all goods, returning the receipt.
 */
function createReceivedPurchaseForIntegration(int $quantity = 5, float $unitCost = 250.00): GoodsReceipt
{
    $admin = adminUser();
    test()->actingAs($admin);

    $purchase = createConfirmedPurchaseForIntegration($quantity, $unitCost);
    $itemId = $purchase->items->first()->id;

    return app(ReceivingService::class)->receiveGoods($purchase, [$itemId => $quantity], $admin);
}

describe('navigation — role-appropriate Phase 2 menu', function () {
    it('admin sees the full purchasing menu in the navigation bar', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertStatus(200);
        // Admin sees all five Phase 2 module links
        $response->assertSee('Suppliers', false);
        $response->assertSee('Purchases', false);
        $response->assertSee('Goods Receipts', false);
        $response->assertSee('Print Labels', false);
        $response->assertSee('Printer Settings', false);
        // ...with working hrefs
        $response->assertSee(route('suppliers.index'), false);
        $response->assertSee(route('purchases.index'), false);
        $response->assertSee(route('goods-receipts.index'), false);
        $response->assertSee(route('labels.print'), false);
        $response->assertSee(route('settings.printers'), false);
    });

    it('main-branch supervisor sees Receiving (Goods Receipts) and Print Labels only', function () {
        $supervisor = supervisor(mainBranch());

        $response = $this->actingAs($supervisor)->get(route('dashboard'));

        $response->assertStatus(200);
        // Supervisor's purchasing menu: Receiving + Print Labels
        $response->assertSee('Goods Receipts', false);
        $response->assertSee(route('goods-receipts.index'), false);
        $response->assertSee('Print Labels', false);
        $response->assertSee(route('labels.print'), false);
        // No admin-only purchasing links
        $response->assertDontSee(route('suppliers.index'), false);
        $response->assertDontSee(route('purchases.index'), false);
        $response->assertDontSee(route('settings.printers'), false);
    });

    it('non-main-branch supervisor sees only Print Labels in the purchasing menu', function () {
        $supervisor = supervisor(Branch::where('code', 'B1')->first());

        $response = $this->actingAs($supervisor)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Print Labels', false);
        $response->assertSee(route('labels.print'), false);
        // No receiving or admin-only links
        $response->assertDontSee(route('goods-receipts.index'), false);
        $response->assertDontSee(route('suppliers.index'), false);
        $response->assertDontSee(route('purchases.index'), false);
        $response->assertDontSee(route('settings.printers'), false);
    });

    it('salesperson sees no purchasing menu items', function () {
        $salesperson = salesperson(Branch::where('code', 'B1')->first());

        $response = $this->actingAs($salesperson)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee(route('suppliers.index'), false);
        $response->assertDontSee(route('purchases.index'), false);
        $response->assertDontSee(route('goods-receipts.index'), false);
        $response->assertDontSee(route('labels.print'), false);
        $response->assertDontSee(route('settings.printers'), false);
    });

    it('online team member sees no purchasing menu items', function () {
        $member = onlineTeamMember();

        $response = $this->actingAs($member)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee(route('suppliers.index'), false);
        $response->assertDontSee(route('purchases.index'), false);
        $response->assertDontSee(route('goods-receipts.index'), false);
        $response->assertDontSee(route('labels.print'), false);
        $response->assertDontSee(route('settings.printers'), false);
    });

    it('online supervisor sees no purchasing menu items', function () {
        $member = onlineSupervisor();

        $response = $this->actingAs($member)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee(route('suppliers.index'), false);
        $response->assertDontSee(route('purchases.index'), false);
        $response->assertDontSee(route('goods-receipts.index'), false);
        $response->assertDontSee(route('labels.print'), false);
        $response->assertDontSee(route('settings.printers'), false);
    });
});

describe('goods receipts access — receiving staff scope', function () {
    it('main-branch supervisor can view the goods receipt list', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForIntegration(3);

        $supervisor = supervisor(mainBranch());
        $response = $this->actingAs($supervisor)->get(route('goods-receipts.index'));

        $response->assertStatus(200);
        $response->assertSee($receipt->receipt_number);
    });

    it('supplier name is hidden from the receipt list for main-branch supervisor', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForIntegration(3);
        $supplierName = $receipt->purchase->supplier->name;

        $supervisor = supervisor(mainBranch());
        $response = $this->actingAs($supervisor)->get(route('goods-receipts.index'));

        $response->assertStatus(200);
        $response->assertDontSee($supplierName);
        $response->assertDontSee('Supplier');
    });

    it('main-branch supervisor can view receipt detail without supplier name or cost', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForIntegration(3);
        $supplierName = $receipt->purchase->supplier->name;

        $supervisor = supervisor(mainBranch());
        $response = $this->actingAs($supervisor)->get(route('goods-receipts.show', $receipt));

        $response->assertStatus(200);
        // Sees their own workflow's garment identities
        $response->assertSee($receipt->receipt_number);
        $response->assertSee($receipt->garments->first()->barcode);
        // But never supplier identity or purchase cost
        $response->assertDontSee($supplierName);
        $response->assertDontSee('Supplier');
        $response->assertDontSee('Cost');
    });

    it('admin still sees supplier name and cost in receipt detail', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForIntegration(3, 333.33);

        $response = $this->actingAs($admin)->get(route('goods-receipts.show', $receipt));

        $response->assertStatus(200);
        $response->assertSee($receipt->purchase->supplier->name);
        $response->assertSee('333.33');
    });

    it('non-main-branch supervisor is still denied the goods receipt list', function () {
        $admin = adminUser();
        createReceivedPurchaseForIntegration(3);

        $supervisor = supervisor(Branch::where('code', 'B1')->first());
        $response = $this->actingAs($supervisor)->get(route('goods-receipts.index'));

        $response->assertForbidden();
    });
});
