<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Services\StockQueryService;
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

describe('sensitive data — purchase cost hidden across all query paths', function () {
    it('purchase_cost is absent from every StockQueryService query result', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        createReceivedPurchaseForIntegration(4);

        $branch = mainBranch();
        $stockService = app(StockQueryService::class);
        $product = Product::first();

        // Branch-scoped availability
        $stock = $stockService->getAvailableStockForBranch($branch);
        expect($stock->count())->toBeGreaterThan(0);
        expect($stock->first()->toArray())->not->toHaveKey('purchase_cost');

        // All-branch availability
        $allStock = $stockService->getAvailableStockForAllBranches();
        expect($allStock->first()->toArray())->not->toHaveKey('purchase_cost');

        // Per-product stock (grouped by branch)
        $productStock = $stockService->getStockForProduct($product);
        $flat = $productStock->flatten();
        expect($flat->count())->toBeGreaterThan(0);
        expect($flat->first()->toArray())->not->toHaveKey('purchase_cost');

        // Barcode/serial search
        $garment = Garment::first();
        $found = $stockService->searchGarments($garment->barcode);
        expect($found->count())->toBeGreaterThan(0);
        expect($found->first()->toArray())->not->toHaveKey('purchase_cost');

        // Paginated availability
        $paginated = $stockService->getPaginatedAvailableStock();
        expect($paginated->items())->not->toBeEmpty();
        expect(collect($paginated->items())->first()->toArray())->not->toHaveKey('purchase_cost');

        // Summary projection contains no cost data
        $summary = $stockService->getStockSummary();
        expect(str_contains(json_encode($summary), 'purchase_cost'))->toBeFalse();

        // Default model serialization hides cost (future API resources inherit this)
        expect(Garment::first()->toArray())->not->toHaveKey('purchase_cost');
    });

    it('purchase total_cost and item unit_cost are hidden from serialization', function () {
        $purchase = createConfirmedPurchaseForIntegration(3, 199.99);

        expect($purchase->toArray())->not->toHaveKey('total_cost');

        $item = $purchase->items->first();
        expect($item->toArray())->not->toHaveKey('unit_cost');

        // Explicit admin-authorized reveal works
        $purchase->withTotalCost();
        expect($purchase->toArray())->toHaveKey('total_cost');

        $item->withUnitCost();
        expect($item->toArray())->toHaveKey('unit_cost');
    });

    it('supplier contact fields and outstanding_balance are hidden from serialization', function () {
        $supplier = Supplier::factory()->create([
            'contact_person' => 'Secret Contact',
            'phone' => '01711111111',
            'email' => 'secret@supplier.test',
            'address' => 'Hidden Address Lane',
            'outstanding_balance' => 4200.50,
        ]);

        $array = $supplier->toArray();

        expect($array)->not->toHaveKey('contact_person');
        expect($array)->not->toHaveKey('phone');
        expect($array)->not->toHaveKey('email');
        expect($array)->not->toHaveKey('address');
        expect($array)->not->toHaveKey('outstanding_balance');

        // Explicit admin-authorized reveal works
        $supplier->withSensitiveInfo();
        $revealed = $supplier->toArray();
        expect($revealed)->toHaveKey('contact_person');
        expect($revealed)->toHaveKey('outstanding_balance');
    });

    it('purchase pages are admin-only so cost data cannot be reached by other roles', function () {
        $purchase = createConfirmedPurchaseForIntegration(5);

        $nonAdmins = [
            supervisor(mainBranch()),
            supervisor(Branch::where('code', 'B1')->first()),
            salesperson(Branch::where('code', 'B1')->first()),
            onlineTeamMember(),
            onlineSupervisor(),
        ];

        foreach ($nonAdmins as $user) {
            expect($this->actingAs($user)->get(route('purchases.show', $purchase))->status())
                ->toBe(403, "purchases.show must be forbidden for {$user->username}");
            expect($this->actingAs($user)->get(route('purchases.index'))->status())
                ->toBe(403, "purchases.index must be forbidden for {$user->username}");
        }
    });

    it('supplier pages are admin-only so supplier info cannot be reached by other roles', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create([
            'contact_person' => 'Hidden Person',
            'phone' => '01722334455',
        ]);

        $nonAdmins = [
            supervisor(mainBranch()),
            supervisor(Branch::where('code', 'B1')->first()),
            salesperson(Branch::where('code', 'B1')->first()),
            onlineTeamMember(),
            onlineSupervisor(),
        ];

        foreach ($nonAdmins as $user) {
            expect($this->actingAs($user)->get(route('suppliers.show', $supplier))->status())
                ->toBe(403, "suppliers.show must be forbidden for {$user->username}");
            expect($this->actingAs($user)->get(route('suppliers.index'))->status())
                ->toBe(403, "suppliers.index must be forbidden for {$user->username}");
        }
    });
});

describe('sensitive data access is audit-logged (Security §4.3)', function () {
    it('admin viewing purchase detail logs sensitive_data.view', function () {
        $admin = adminUser();

        $purchase = createConfirmedPurchaseForIntegration(5);
        AuditLog::query()->delete();

        $this->actingAs($admin)->get(route('purchases.show', $purchase))->assertStatus(200);

        $entry = AuditLog::where('action', 'sensitive_data.view')
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->first();

        expect($entry)->not->toBeNull();
        expect($entry->user_id)->toBe($admin->id);
        expect($entry->category)->toBe('security');
        expect($entry->new_values['data_type'])->toBe('purchase_cost');
    });

    it('admin viewing supplier detail logs sensitive_data.view', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        AuditLog::query()->delete();

        $this->actingAs($admin)->get(route('suppliers.show', $supplier))->assertStatus(200);

        $entry = AuditLog::where('action', 'sensitive_data.view')
            ->where('reference_type', 'supplier')
            ->where('reference_id', $supplier->id)
            ->first();

        expect($entry)->not->toBeNull();
        expect($entry->user_id)->toBe($admin->id);
        expect($entry->new_values['data_type'])->toBe('supplier_info');
    });

    it('admin viewing garment detail logs sensitive_data.view; non-admin does not', function () {
        $admin = adminUser();
        $garment = createReceivedPurchaseForIntegration(2)->garments->first();
        AuditLog::query()->delete();

        // Admin view → logged
        $this->actingAs($admin)->get(route('garments.show', $garment))->assertStatus(200);

        $adminEntry = AuditLog::where('action', 'sensitive_data.view')
            ->where('reference_type', 'garment')
            ->where('reference_id', $garment->id)
            ->where('user_id', $admin->id)
            ->first();

        expect($adminEntry)->not->toBeNull();
        expect($adminEntry->new_values['data_type'])->toBe('purchase_cost');

        // Non-admin view → never logged (cost is never revealed to them)
        $salesperson = salesperson(Branch::where('code', 'B1')->first());
        $this->actingAs($salesperson)->get(route('garments.show', $garment))->assertStatus(200);

        expect(AuditLog::where('action', 'sensitive_data.view')
            ->where('user_id', $salesperson->id)
            ->exists())->toBeFalse();
    });

    it('admin viewing goods receipt detail logs sensitive_data.view', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForIntegration(2);
        AuditLog::query()->delete();

        $this->actingAs($admin)->get(route('goods-receipts.show', $receipt))->assertStatus(200);

        $entry = AuditLog::where('action', 'sensitive_data.view')
            ->where('reference_type', 'goods_receipt')
            ->where('reference_id', $receipt->id)
            ->first();

        expect($entry)->not->toBeNull();
        expect($entry->user_id)->toBe($admin->id);
    });

    it('non-admin roles never receive sensitive_data.view entries from any view', function () {
        $admin = adminUser();
        $receipt = createReceivedPurchaseForIntegration(2);
        $garment = $receipt->garments->first();
        AuditLog::query()->delete();

        // Receiving staff (allowed pages) and other roles browse everything they can
        $mainSupervisor = supervisor(mainBranch());
        $this->actingAs($mainSupervisor)->get(route('goods-receipts.show', $receipt))->assertStatus(200);
        $this->actingAs($mainSupervisor)->get(route('garments.show', $garment))->assertStatus(200);

        $salesperson = salesperson(Branch::where('code', 'B1')->first());
        $this->actingAs($salesperson)->get(route('garments.show', $garment))->assertStatus(200);

        expect(AuditLog::where('action', 'sensitive_data.view')->exists())->toBeFalse();
    });
});
