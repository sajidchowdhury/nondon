<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Services\GarmentIdentityService;
use App\Modules\Inventory\Services\StockQueryService;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Services\FiscalYearService;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Purchasing\Services\ReceivingService;
use App\Modules\Purchasing\Services\SupplierService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;

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

describe('full purchasing lifecycle — WF-1 end to end', function () {
    it('supplier → purchase → confirm → receive → identities → labels → receipt view → audit', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        // 1. Create supplier via the service
        $supplierService = app(SupplierService::class);
        $supplier = $supplierService->create([
            'code' => 'LIFE-SUP-01',
            'name' => 'Lifecycle Supplier',
            'contact_person' => 'John Contact',
            'phone' => '01712345678',
        ]);
        expect($supplier->exists)->toBeTrue();

        // 2. Create a purchase with line items
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();
        $product->refresh();

        $purchasingService = app(PurchasingService::class);
        $purchase = $purchasingService->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => '2025-08-20',
            'items' => [
                ['product_id' => $product->id, 'ordered_quantity' => 4, 'unit_cost' => 350.00],
            ],
        ]);
        expect($purchase->status)->toBe(Purchase::STATUS_DRAFT);
        expect($purchase->purchase_number)->toStartWith('PO-');

        // 3. Confirm the purchase
        $purchase = $purchasingService->confirmPurchase($purchase);
        expect($purchase->status)->toBe(Purchase::STATUS_CONFIRMED);

        // 4. Receive goods — garment identities generated
        $itemId = $purchase->items->first()->id;
        $receipt = app(ReceivingService::class)->receiveGoods($purchase, [$itemId => 4], $admin);

        expect($receipt->receipt_number)->toStartWith('GR-');
        expect($receipt->total_received_quantity)->toBe(4);

        // 5. Verify garment identities: UUID, barcode, serial, links
        $garments = $receipt->fresh('garments')->garments;
        expect($garments)->toHaveCount(4);

        foreach ($garments as $garment) {
            expect($garment->internal_id)->not->toBeEmpty();
            expect($garment->barcode)->toStartWith('NND-');
            expect($garment->barcode)->toBe('NND-'.$garment->fiscal_year_serial);
            expect($garment->fiscal_year_serial)->toStartWith($fiscalYear->serial_prefix.'-');
            expect($garment->purchase_id)->toBe($purchase->id);
            expect($garment->goods_receipt_id)->toBe($receipt->id);
            expect($garment->current_status)->toBe(Garment::STATUS_AVAILABLE);
        }

        $serials = $garments->pluck('fiscal_year_serial')->toArray();
        expect(count(array_unique($serials)))->toBe(4);

        // 6. Print labels for the receipt via the print sheet endpoint
        $response = $this->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'hang_tag_large',
        ]));
        $response->assertStatus(200);
        // Labels render barcode + text but never cost
        $response->assertSee($garments->first()->barcode);
        $response->assertDontSee('350.00');

        // 7. View the goods receipt
        $viewResponse = $this->get(route('goods-receipts.show', $receipt));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee($receipt->receipt_number);
        $viewResponse->assertSee($supplier->name); // admin sees supplier identity

        // 8. Audit log captured the whole chain
        $actions = AuditLog::query()->pluck('action')->toArray();
        expect($actions)->toContain('supplier.create');
        expect($actions)->toContain('purchase.create');
        expect($actions)->toContain('purchase.confirm');
        expect($actions)->toContain('purchase.receive');
        expect($actions)->toContain('goods_receipt.create');
        expect($actions)->toContain('garment.create');
        expect($actions)->toContain('inventory.movement');
        expect($actions)->toContain('label.print');
    });
});

describe('multi-receipt partial receiving flow', function () {
    it('partial receipt → partially_received, second receipt → received', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchaseForIntegration(10);
        $itemId = $purchase->items->first()->id;
        $service = app(ReceivingService::class);

        // First receipt: half the items
        $receipt1 = $service->receiveGoods($purchase, [$itemId => 5], $admin);

        expect($purchase->fresh()->status)->toBe(Purchase::STATUS_PARTIALLY_RECEIVED);
        expect($purchase->fresh()->items->first()->received_quantity)->toBe(5);
        expect($receipt1->total_received_quantity)->toBe(5);

        // Still receivable while partially received
        expect($purchase->fresh()->canBeReceived())->toBeTrue();

        // Second receipt: the remaining half
        $receipt2 = $service->receiveGoods($purchase->fresh(), [$itemId => 5], $admin);

        expect($purchase->fresh()->status)->toBe(Purchase::STATUS_RECEIVED);
        expect($purchase->fresh()->items->first()->received_quantity)->toBe(10);
        expect($receipt2->total_received_quantity)->toBe(5);

        // Two distinct receipts with distinct GR numbers
        $receipts = $purchase->fresh()->goodsReceipts;
        expect($receipts)->toHaveCount(2);
        expect($receipts->pluck('receipt_number')->unique()->count())->toBe(2);

        // 10 garments, all unique serials
        $serials = Garment::where('purchase_id', $purchase->id)->pluck('fiscal_year_serial')->toArray();
        expect(count($serials))->toBe(10);
        expect(count(array_unique($serials)))->toBe(10);
    });
});

describe('permission matrix — all 5 roles', function () {
    it('main branch admin has full purchasing access', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $service = app(PurchasingService::class);

        // Draft purchase — confirm/cancel apply to drafts
        $draft = $service->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => FiscalYear::active()->first()->id,
            'purchase_date' => '2025-08-25',
            'items' => [['product_id' => $product->id, 'ordered_quantity' => 3, 'unit_cost' => 100]],
        ]);

        // Confirmed purchase — receive applies
        $confirmed = $service->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => FiscalYear::active()->first()->id,
            'purchase_date' => '2025-08-25',
            'items' => [['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => 150]],
        ]);
        $service->confirmPurchase($confirmed);

        expect($admin->can('create', Purchase::class))->toBeTrue();
        expect($admin->can('confirm', $draft))->toBeTrue();
        expect($admin->can('cancel', $draft))->toBeTrue();
        expect($admin->can('receive', $confirmed))->toBeTrue();
        expect($admin->can('viewCost', $confirmed))->toBeTrue();
        expect($admin->can('viewAny', Supplier::class))->toBeTrue();
        expect($admin->can('create', Supplier::class))->toBeTrue();
        expect($admin->can('view-goods-receipts'))->toBeTrue();
        expect($admin->can('print-labels'))->toBeTrue();
        expect($admin->can('manage-printer-settings'))->toBeTrue();
        expect($admin->can('view-purchase-cost'))->toBeTrue();
        expect($admin->can('view-supplier-info'))->toBeTrue();
    });

    it('main-branch supervisor can receive and print labels only', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $service = app(PurchasingService::class);

        $draft = $service->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => FiscalYear::active()->first()->id,
            'purchase_date' => '2025-08-25',
            'items' => [['product_id' => $product->id, 'ordered_quantity' => 3, 'unit_cost' => 100]],
        ]);

        $confirmed = $service->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => FiscalYear::active()->first()->id,
            'purchase_date' => '2025-08-25',
            'items' => [['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => 150]],
        ]);
        $service->confirmPurchase($confirmed);

        $supervisor = supervisor(mainBranch());

        expect($supervisor->can('receive', $confirmed))->toBeTrue();
        expect($supervisor->can('print-labels'))->toBeTrue();
        expect($supervisor->can('view-goods-receipts'))->toBeTrue();

        // Explicitly denied everything else — including on a draft where
        // status would not otherwise block the action
        expect($supervisor->can('create', Purchase::class))->toBeFalse();
        expect($supervisor->can('confirm', $draft))->toBeFalse();
        expect($supervisor->can('cancel', $draft))->toBeFalse();
        expect($supervisor->can('viewCost', $confirmed))->toBeFalse();
        expect($supervisor->can('viewAny', Supplier::class))->toBeFalse();
        expect($supervisor->can('create', Supplier::class))->toBeFalse();
        expect($supervisor->can('manage-printer-settings'))->toBeFalse();
        expect($supervisor->can('view-purchase-cost'))->toBeFalse();
        expect($supervisor->can('view-supplier-info'))->toBeFalse();

        // Pages deny non-admins (403)
        $this->actingAs($supervisor);
        expect($this->get(route('purchases.create'))->status())->toBe(403);
        expect($this->get(route('suppliers.create'))->status())->toBe(403);
        expect($this->get(route('purchases.show', $confirmed))->status())->toBe(403);
    });

    it('salesperson has no purchasing access at all', function () {
        $purchase = createConfirmedPurchaseForIntegration(3);
        $salesperson = salesperson(Branch::where('code', 'B1')->first());

        expect($salesperson->can('receive', $purchase))->toBeFalse();
        expect($salesperson->can('create', Purchase::class))->toBeFalse();
        expect($salesperson->can('confirm', $purchase))->toBeFalse();
        expect($salesperson->can('cancel', $purchase))->toBeFalse();
        expect($salesperson->can('viewCost', $purchase))->toBeFalse();
        expect($salesperson->can('viewAny', Supplier::class))->toBeFalse();
        expect($salesperson->can('view-goods-receipts'))->toBeFalse();
        expect($salesperson->can('print-labels'))->toBeFalse();
        expect($salesperson->can('manage-printer-settings'))->toBeFalse();

        $this->actingAs($salesperson);
        expect($this->get(route('purchases.receive', $purchase))->status())->toBe(403);
        expect($this->get(route('labels.print'))->status())->toBe(403);
        expect($this->get(route('goods-receipts.index'))->status())->toBe(403);
    });

    it('online team has no purchasing access', function () {
        $purchase = createConfirmedPurchaseForIntegration(3);
        $member = onlineTeamMember();

        expect($member->can('receive', $purchase))->toBeFalse();
        expect($member->can('create', Purchase::class))->toBeFalse();
        expect($member->can('viewAny', Supplier::class))->toBeFalse();
        expect($member->can('print-labels'))->toBeFalse();
        expect($member->can('view-goods-receipts'))->toBeFalse();

        $this->actingAs($member);
        expect($this->get(route('labels.print'))->status())->toBe(403);
        expect($this->get(route('goods-receipts.index'))->status())->toBe(403);
    });

    it('online supervisor has no purchasing access', function () {
        $purchase = createConfirmedPurchaseForIntegration(3);
        $member = onlineSupervisor();

        expect($member->can('receive', $purchase))->toBeFalse();
        expect($member->can('create', Purchase::class))->toBeFalse();
        expect($member->can('viewAny', Supplier::class))->toBeFalse();
        expect($member->can('print-labels'))->toBeFalse();
        expect($member->can('view-goods-receipts'))->toBeFalse();

        $this->actingAs($member);
        expect($this->get(route('labels.print'))->status())->toBe(403);
        expect($this->get(route('settings.printers'))->status())->toBe(403);
    });
});

describe('branch scope — receiving only at Main Branch', function () {
    it('non-main-branch supervisor cannot receive even with the supervisor role', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchaseForIntegration(4);
        $itemId = $purchase->items->first()->id;

        $b1Supervisor = supervisor(Branch::where('code', 'B1')->first());

        // Policy denies
        expect($b1Supervisor->can('receive', $purchase))->toBeFalse();

        // Service rejects (policy check inside receiveGoods)
        expect(fn () => app(ReceivingService::class)->receiveGoods($purchase, [$itemId => 2], $b1Supervisor))
            ->toThrow(ValidationException::class);

        // No garments were generated
        expect(Garment::where('purchase_id', $purchase->id)->count())->toBe(0);
        expect($purchase->fresh()->status)->toBe(Purchase::STATUS_CONFIRMED);

        // Receiving form denied (403)
        expect($this->actingAs($b1Supervisor)->get(route('purchases.receive', $purchase))->status())->toBe(403);
    });

    it('main-branch supervisor can receive (goods land in Main Branch)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchaseForIntegration(3);
        $itemId = $purchase->items->first()->id;

        $mainSupervisor = supervisor(mainBranch());
        expect($mainSupervisor->can('receive', $purchase))->toBeTrue();

        $receipt = app(ReceivingService::class)->receiveGoods($purchase, [$itemId => 3], $mainSupervisor);
        expect($receipt->exists)->toBeTrue();

        $garments = Garment::where('purchase_id', $purchase->id)->get();
        expect($garments)->toHaveCount(3);
        foreach ($garments as $garment) {
            expect($garment->current_branch_id)->toBe(mainBranch()->id);
        }
    });
});

describe('carry-forward — garment identity across fiscal year boundary', function () {
    it('old garments retain serials/barcodes; new FY serials restart from 00001', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $fyService = app(FiscalYearService::class);

        // Current seeded FY (e.g. FY 26_27) is active; create the previous FY 25_26
        $fy2627 = FiscalYear::active()->first();
        $fy2526 = $fyService->create(['start_year' => 2025]);
        expect($fy2526->serial_prefix)->toBe('25');
        expect($fy2526->name)->toBe('FY 25_26');

        // Activate FY 25_26 and create garments in it (serials 25-00001..25-00005)
        $fyService->activate($fy2526);

        $identityService = app(GarmentIdentityService::class);
        $mainBranch = mainBranch();
        $product = Product::factory()->create();
        $size = ProductSize::factory()->forProduct($product)->create();

        $oldGarments = collect();
        for ($i = 0; $i < 5; $i++) {
            $oldGarments->push($identityService->createGarment(
                product: $product,
                size: $size,
                fiscalYear: $fy2526->fresh(),
                branch: $mainBranch,
                purchaseCost: 100.00,
            ));
        }

        expect($oldGarments->pluck('fiscal_year_serial')->toArray())->toBe([
            '25-00001', '25-00002', '25-00003', '25-00004', '25-00005',
        ]);
        $oldBarcodes = $oldGarments->pluck('barcode')->toArray();

        // Close FY 25_26 and reopen FY 26_27
        $fyService->close($fy2526);
        expect($fy2526->fresh()->is_closed)->toBeTrue();

        // Re-fetch before activating — the earlier activation of 25_26 set
        // 26_27 inactive via a mass update, so the cached instance is stale.
        $fyService->activate($fy2627->fresh());
        expect($fy2627->fresh()->is_active)->toBeTrue();

        // Receive new garments in FY 26_27 via the purchasing flow
        $supplier = Supplier::factory()->create();
        $purchase = app(PurchasingService::class)->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fy2627->id,
            'purchase_date' => '2026-08-05',
            'items' => [
                ['product_id' => $product->id, 'product_size_id' => $size->id, 'ordered_quantity' => 3, 'unit_cost' => 200.00],
            ],
        ]);
        app(PurchasingService::class)->confirmPurchase($purchase);
        $itemId = $purchase->items->first()->id;
        app(ReceivingService::class)->receiveGoods($purchase, [$itemId => 3], $admin);

        $newGarments = Garment::where('purchase_id', $purchase->id)->orderBy('id')->get();
        expect($newGarments->pluck('fiscal_year_serial')->toArray())->toBe([
            '26-00001', '26-00002', '26-00003',
        ]);

        // Old garments retain their original serials and barcodes
        foreach ($oldGarments as $old) {
            $fresh = $old->fresh();
            expect($fresh->fiscal_year_serial)->toStartWith('25-');
            expect($fresh->barcode)->toBe('NND-'.$fresh->fiscal_year_serial);
        }
        expect($oldGarments->pluck('barcode')->toArray())->toBe($oldBarcodes);

        // Old garments are NOT renumbered when the new FY started
        $oldSerialsAfter = Garment::whereIn('id', $oldGarments->pluck('id'))->orderBy('id')->pluck('fiscal_year_serial')->toArray();
        expect($oldSerialsAfter)->toBe(['25-00001', '25-00002', '25-00003', '25-00004', '25-00005']);

        // Both old and new garments are queryable in stock availability
        $stock = app(StockQueryService::class)->getAvailableStockForAllBranches();
        foreach ($oldGarments as $old) {
            expect($stock->contains('id', $old->id))->toBeTrue();
        }
        foreach ($newGarments as $new) {
            expect($stock->contains('id', $new->id))->toBeTrue();
        }
    });
});

describe('concurrency verification', function () {
    it('generates 100 unique serials in a single large receipt', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchaseForIntegration(100);
        $itemId = $purchase->items->first()->id;

        $receipt = app(ReceivingService::class)->receiveGoods($purchase, [$itemId => 100], $admin);

        $serials = Garment::where('goods_receipt_id', $receipt->id)->pluck('fiscal_year_serial')->toArray();
        $barcodes = Garment::where('goods_receipt_id', $receipt->id)->pluck('barcode')->toArray();
        $internalIds = Garment::where('goods_receipt_id', $receipt->id)->pluck('internal_id')->toArray();

        expect(count($serials))->toBe(100);
        expect(count(array_unique($serials)))->toBe(100);
        expect(count(array_unique($barcodes)))->toBe(100);
        expect(count(array_unique($internalIds)))->toBe(100);
    });

    it('interleaved receipts on different purchases produce no serial collisions', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(ReceivingService::class);

        // Two purchases of 10 units each in the same fiscal year
        $purchaseA = createConfirmedPurchaseForIntegration(10);
        $purchaseB = createConfirmedPurchaseForIntegration(10);

        $itemA = $purchaseA->items->first()->id;
        $itemB = $purchaseB->items->first()->id;

        // Simulate concurrent receiving staff: A(5) → B(5) → A(5) → B(5)
        $receiptA1 = $service->receiveGoods($purchaseA, [$itemA => 5], $admin);
        $receiptB1 = $service->receiveGoods($purchaseB, [$itemB => 5], $admin);
        $receiptA2 = $service->receiveGoods($purchaseA->fresh(), [$itemA => 5], $admin);
        $receiptB2 = $service->receiveGoods($purchaseB->fresh(), [$itemB => 5], $admin);

        // All four receipts exist with distinct numbers
        $receiptNumbers = collect([$receiptA1, $receiptB1, $receiptA2, $receiptB2])
            ->pluck('receipt_number');
        expect($receiptNumbers->unique()->count())->toBe(4);

        // Both purchases fully received
        expect($purchaseA->fresh()->status)->toBe(Purchase::STATUS_RECEIVED);
        expect($purchaseB->fresh()->status)->toBe(Purchase::STATUS_RECEIVED);

        // 20 garments across both purchases — zero serial/barcode collisions
        $serials = Garment::whereIn('purchase_id', [$purchaseA->id, $purchaseB->id])->pluck('fiscal_year_serial')->toArray();
        $barcodes = Garment::whereIn('purchase_id', [$purchaseA->id, $purchaseB->id])->pluck('barcode')->toArray();

        expect(count($serials))->toBe(20);
        expect(count(array_unique($serials)))->toBe(20);
        expect(count(array_unique($barcodes)))->toBe(20);
    });
});

describe('audit completeness — all purchasing actions', function () {
    it('every purchasing action is audited with correct category, reference, and user', function () {
        $admin = adminUser();
        $this->actingAs($admin);
        AuditLog::query()->delete();

        // supplier.create / supplier.update
        $supplierService = app(SupplierService::class);
        $supplier = $supplierService->create([
            'code' => 'AUD-SUP-01',
            'name' => 'Audit Supplier',
            'phone' => '01755566677',
        ]);
        $supplierService->update($supplier, [
            'code' => 'AUD-SUP-01',
            'name' => 'Audit Supplier Updated',
        ]);

        // purchase.create / purchase.confirm / purchase.cancel
        // (while the supplier is still active)
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();
        $purchasingService = app(PurchasingService::class);
        $purchase = $purchasingService->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => '2025-08-22',
            'items' => [
                ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => 80.00],
            ],
        ]);
        $purchasingService->confirmPurchase($purchase);
        $purchasingService->cancelPurchase($purchase, 'Duplicate order');

        // supplier.deactivate (after the purchase actions)
        $supplierService->deactivate($supplier);

        // Second purchase → receive → label.print
        // (a fresh ACTIVE supplier — the first one is deactivated by now)
        $activeSupplier = $supplierService->create([
            'code' => 'AUD-SUP-02',
            'name' => 'Audit Supplier Two',
        ]);
        $purchase2 = $purchasingService->createPurchase([
            'supplier_id' => $activeSupplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => '2025-08-23',
            'items' => [
                ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => 90.00],
            ],
        ]);
        $purchasingService->confirmPurchase($purchase2);
        $item2 = $purchase2->items->first()->id;
        $receipt = app(ReceivingService::class)->receiveGoods($purchase2, [$item2 => 2], $admin);

        $printResponse = $this->get(route('labels.print-sheet', [
            'receipt' => $receipt->id,
            'format' => 'sticker_small',
        ]));
        $printResponse->assertStatus(200);

        // ── Assertions: each action audited with correct metadata ──

        $assertEntry = function (string $action, string $referenceType, int $referenceId) use ($admin) {
            $entry = AuditLog::where('action', $action)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->latest('id')
                ->first();

            expect($entry)->not->toBeNull("audit entry for {$action} ({$referenceType}#{$referenceId}) should exist");
            expect($entry->category)->toBe('purchasing', "{$action} must use the purchasing category");
            expect($entry->user_id)->toBe($admin->id, "{$action} must record the acting user");

            return $entry;
        };

        $assertEntry('supplier.create', 'supplier', $supplier->id);
        // Note: the deactivate() call also fires an automatic model 'updated'
        // event (is_active flip), so target the explicit name-change entry.
        $updateEntry = AuditLog::where('action', 'supplier.update')
            ->where('reference_type', 'supplier')
            ->where('reference_id', $supplier->id)
            ->where('new_values->name', 'Audit Supplier Updated')
            ->first();
        expect($updateEntry)->not->toBeNull('supplier.update entry with the name change should exist');
        expect($updateEntry->category)->toBe('purchasing');
        expect($updateEntry->user_id)->toBe($admin->id);
        $deactivateEntry = $assertEntry('supplier.deactivate', 'supplier', $supplier->id);
        expect($deactivateEntry->new_values)->toHaveKey('is_active', false);

        $assertEntry('purchase.create', 'purchase', $purchase->id);
        $confirmEntry = $assertEntry('purchase.confirm', 'purchase', $purchase->id);
        expect($confirmEntry->new_values)->toHaveKey('status', Purchase::STATUS_CONFIRMED);
        $cancelEntry = $assertEntry('purchase.cancel', 'purchase', $purchase->id);
        expect($cancelEntry->new_values)->toHaveKey('status', Purchase::STATUS_CANCELLED);
        expect($cancelEntry->reason)->toBe('Duplicate order');

        // purchase.receive: per-garment entries + per-receipt summary
        $garmentEntries = AuditLog::where('action', 'purchase.receive')
            ->where('reference_type', 'garment')
            ->where('reference_id', $receipt->garments->first()->id)
            ->get();
        expect($garmentEntries->count())->toBeGreaterThan(0);

        $receiptEntry = AuditLog::where('action', 'purchase.receive')
            ->where('reference_type', 'goods_receipt')
            ->where('reference_id', $receipt->id)
            ->first();
        expect($receiptEntry)->not->toBeNull();
        expect($receiptEntry->new_values['receipt_number'])->toBe($receipt->receipt_number);
        expect($receiptEntry->new_values['total_received'])->toBe(2);

        // label.print
        $labelEntry = AuditLog::where('action', 'label.print')
            ->where('reference_type', 'goods_receipt')
            ->where('reference_id', $receipt->id)
            ->first();
        expect($labelEntry)->not->toBeNull();
        expect($labelEntry->category)->toBe('purchasing');
        expect($labelEntry->user_id)->toBe($admin->id);
        expect($labelEntry->new_values['format'])->toBe('sticker_small');
        expect($labelEntry->new_values['count'])->toBe(2);
    });
});
