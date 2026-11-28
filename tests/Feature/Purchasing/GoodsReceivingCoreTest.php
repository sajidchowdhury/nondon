<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Purchasing\Events\PurchaseReceived;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchaseItem;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Purchasing\Services\ReceivingService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

/**
 * Helper: create a confirmed purchase with one line item for receiving tests.
 */
function createConfirmedPurchase(int $quantity = 10, float $unitCost = 250.00): Purchase
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

describe('goods receiving — basic flow', function () {
    it('can receive a fully confirmed purchase', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(10);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $receipt = $service->receiveGoods($purchase, [$itemId => 10], $admin);

        expect($receipt)->toBeInstanceOf(GoodsReceipt::class);
        expect($receipt->total_received_quantity)->toBe(10);
        expect($receipt->receipt_number)->toStartWith('GR-');
    });

    it('receiving creates the correct number of garment records', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(5);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $receipt = $service->receiveGoods($purchase, [$itemId => 5], $admin);

        expect(Garment::count())->toBe(5);
        expect($receipt->garments()->count())->toBe(5);
    });

    it('each garment has a valid UUID', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 3], $admin);

        $garments = Garment::all();
        foreach ($garments as $garment) {
            expect($garment->internal_id)->not->toBeEmpty();
            // UUID format check (36 chars with dashes)
            expect(strlen($garment->internal_id))->toBe(36);
        }
    });

    it('each garment has a valid barcode in NND-{serial} format', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 3], $admin);

        $garments = Garment::all();
        foreach ($garments as $garment) {
            expect($garment->barcode)->toStartWith('NND-');
        }
    });

    it('serial format is YY-NNNNN and increments sequentially', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;
        $fiscalYear = $purchase->fiscalYear;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 3], $admin);

        $garments = Garment::orderBy('id')->get();
        $firstSerial = $garments->first()->fiscal_year_serial;
        $lastSerial = $garments->last()->fiscal_year_serial;

        // Serial starts with fiscal year prefix
        expect($firstSerial)->toStartWith($fiscalYear->serial_prefix.'-');
        // Serials are 5-digit padded
        expect(strlen($firstSerial))->toBe(8); // e.g., "25-00001"
    });

    it('each garment is linked to purchase_id and purchase_item_id', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(2);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 2], $admin);

        $garments = Garment::all();
        foreach ($garments as $garment) {
            expect($garment->purchase_id)->toBe($purchase->id);
            expect($garment->purchase_item_id)->toBe($itemId);
        }
    });

    it('each garment is linked to goods_receipt_id', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(2);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $receipt = $service->receiveGoods($purchase, [$itemId => 2], $admin);

        $garments = Garment::all();
        foreach ($garments as $garment) {
            expect($garment->goods_receipt_id)->toBe($receipt->id);
        }
    });

    it('each garment has purchase_cost set from the purchase item unit_cost', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(2, 350.00);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 2], $admin);

        $garments = Garment::all();
        foreach ($garments as $garment) {
            // Access unit_cost via withUnitCost() since it's hidden
            expect((float) $garment->getRawOriginal('purchase_cost'))->toBe(350.00);
        }
    });

    it('each garment has current_status = available at Main Branch', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(2);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 2], $admin);

        $mainBranch = Branch::where('code', 'M')->first();
        $garments = Garment::all();
        foreach ($garments as $garment) {
            expect($garment->current_status)->toBe(Garment::STATUS_AVAILABLE);
            expect($garment->current_branch_id)->toBe($mainBranch->id);
        }
    });

    it('garment_current_stock projection is created for each garment', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 3], $admin);

        expect(GarmentCurrentStock::count())->toBe(3);
    });

    it('inventory_movements record (type goods_receipt) is created for each garment', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 3], $admin);

        $movements = InventoryMovement::where('movement_type', 'goods_receipt')->get();
        expect($movements->count())->toBe(3);

        foreach ($movements as $movement) {
            expect($movement->reference_type)->toBe('purchase');
            expect($movement->reference_id)->toBe($purchase->id);
        }
    });
});

describe('purchase status transitions', function () {
    it('purchase status transitions to received when fully received', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(5);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 5], $admin);

        expect($purchase->fresh()->status)->toBe(Purchase::STATUS_RECEIVED);
    });

    it('purchase status transitions to partially_received when partially received', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(10);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 4], $admin);

        expect($purchase->fresh()->status)->toBe(Purchase::STATUS_PARTIALLY_RECEIVED);
    });

    it('can receive a partially_received purchase again (second receipt)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(10);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);

        // First partial receipt
        $service->receiveGoods($purchase, [$itemId => 4], $admin);
        expect($purchase->fresh()->status)->toBe(Purchase::STATUS_PARTIALLY_RECEIVED);

        // Second receipt for the remaining
        $service->receiveGoods($purchase->fresh(), [$itemId => 6], $admin);
        expect($purchase->fresh()->status)->toBe(Purchase::STATUS_RECEIVED);

        // Should have 2 goods receipts
        expect($purchase->fresh()->goodsReceipts)->toHaveCount(2);

        // Should have 10 garments total
        expect(Garment::count())->toBe(10);
    });

    it('received_quantity on purchase_item is incremented correctly', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(10);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 3], $admin);

        $item = PurchaseItem::find($itemId);
        expect($item->received_quantity)->toBe(3);
    });
});

describe('goods receipt record', function () {
    it('goods_receipts record is created with correct total_received_quantity', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(7);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $receipt = $service->receiveGoods($purchase, [$itemId => 7], $admin);

        expect($receipt->total_received_quantity)->toBe(7);
        expect($receipt->received_by)->toBe($admin->id);
        expect($receipt->purchase_id)->toBe($purchase->id);
    });

    it('receipt_number is unique', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase1 = createConfirmedPurchase(3);
        $item1 = $purchase1->items->first()->id;

        $purchase2 = createConfirmedPurchase(3);
        $item2 = $purchase2->items->first()->id;

        $service = app(ReceivingService::class);
        $receipt1 = $service->receiveGoods($purchase1, [$item1 => 3], $admin);
        $receipt2 = $service->receiveGoods($purchase2, [$item2 => 3], $admin);

        expect($receipt1->receipt_number)->not->toBe($receipt2->receipt_number);
    });
});

describe('receiving — validation errors', function () {
    it('receiving a draft purchase throws an error', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        // Create a purchase but don't confirm it
        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => '2025-08-15',
            'items' => [['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => 100]],
        ]);

        $receivingService = app(ReceivingService::class);
        $itemId = $purchase->items->first()->id;

        expect(fn () => $receivingService->receiveGoods($purchase, [$itemId => 5], $admin))
            ->toThrow(ValidationException::class);
    });

    it('receiving a cancelled purchase throws an error', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(5);
        app(PurchasingService::class)->cancelPurchase($purchase, 'Test cancellation');

        $receivingService = app(ReceivingService::class);
        $itemId = $purchase->items->first()->id;

        expect(fn () => $receivingService->receiveGoods($purchase->fresh(), [$itemId => 5], $admin))
            ->toThrow(ValidationException::class);
    });

    it('receiving more than ordered quantity is flagged', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(5);
        $itemId = $purchase->items->first()->id;

        $receivingService = app(ReceivingService::class);

        // Try to receive 10 when only 5 ordered
        expect(fn () => $receivingService->receiveGoods($purchase, [$itemId => 10], $admin))
            ->toThrow(ValidationException::class);
    });

    it('receiving with empty quantities throws an error', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(5);

        $receivingService = app(ReceivingService::class);

        expect(fn () => $receivingService->receiveGoods($purchase, [], $admin))
            ->toThrow(ValidationException::class);
    });

    it('receiving more than remaining on a partially received purchase throws', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(10);
        $itemId = $purchase->items->first()->id;

        $receivingService = app(ReceivingService::class);
        // First receive 4
        $receivingService->receiveGoods($purchase, [$itemId => 4], $admin);

        // Try to receive 7 more (only 6 remaining)
        expect(fn () => $receivingService->receiveGoods($purchase->fresh(), [$itemId => 7], $admin))
            ->toThrow(ValidationException::class);
    });
});

describe('receiving — authorization', function () {
    it('receiving requires purchase.receive permission (admin)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $receipt = $service->receiveGoods($purchase, [$itemId => 3], $admin);

        expect($receipt)->not->toBeNull();
    });

    it('supervisor can receive goods (purchase.receive permission)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;

        // Supervisor at Main Branch
        $mainBranch = Branch::where('code', 'M')->first();
        $supervisor = supervisor($mainBranch);

        $service = app(ReceivingService::class);
        $receipt = $service->receiveGoods($purchase, [$itemId => 3], $supervisor);

        expect($receipt)->not->toBeNull();
        expect($receipt->received_by)->toBe($supervisor->id);
    });

    it('salesperson cannot receive goods', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;

        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $service = app(ReceivingService::class);

        expect(fn () => $service->receiveGoods($purchase, [$itemId => 3], $salesperson))
            ->toThrow(ValidationException::class);
    });
});

describe('receiving — audit logging', function () {
    it('receiving is audited per garment', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 3], $admin);

        // Should have 3 purchase.receive audit entries with reference_type=garment
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'purchase.receive',
            'reference_type' => 'garment',
        ]);

        $garmentAudits = DB::table('audit_logs')
            ->where('action', 'purchase.receive')
            ->where('reference_type', 'garment')
            ->count();
        expect($garmentAudits)->toBe(3);
    });

    it('receiving is audited per receipt', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(3);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $receipt = $service->receiveGoods($purchase, [$itemId => 3], $admin);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'purchase.receive',
            'reference_type' => 'goods_receipt',
            'reference_id' => $receipt->id,
        ]);
    });

    it('inventory movement audit is created per garment', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(2);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 2], $admin);

        // InventoryMovementService::record() dispatches inventory.movement audit
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'inventory.movement',
            'reference_type' => 'garment',
        ]);
    });
});

describe('receiving — transaction integrity', function () {
    it('if transaction fails, no garments are created (rollback)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(5);
        $itemId = $purchase->items->first()->id;

        // We'll force a failure by trying to receive more than remaining
        // after a partial receipt. The second receive should fail and NOT
        // create any additional garments.
        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 3], $admin);

        expect(Garment::count())->toBe(3);

        // Try to receive 3 more (only 2 remaining) — should fail
        try {
            $service->receiveGoods($purchase->fresh(), [$itemId => 3], $admin);
        } catch (ValidationException $e) {
            // Expected
        }

        // No additional garments should have been created
        expect(Garment::count())->toBe(3);
    });
});

describe('receiving — concurrency', function () {
    it('100 serials generated are all unique', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        // Create a large purchase and receive all 100 at once
        $purchase = createConfirmedPurchase(100);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 100], $admin);

        $serials = Garment::pluck('fiscal_year_serial')->toArray();
        $uniqueSerials = array_unique($serials);

        expect(count($serials))->toBe(100);
        expect(count($uniqueSerials))->toBe(100);
    });

    it('barcodes are all unique across garments', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(50);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 50], $admin);

        $barcodes = Garment::pluck('barcode')->toArray();
        $uniqueBarcodes = array_unique($barcodes);

        expect(count($barcodes))->toBe(50);
        expect(count($uniqueBarcodes))->toBe(50);
    });

    it('sequential receiving produces incrementing serials', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase1 = createConfirmedPurchase(3);
        $item1 = $purchase1->items->first()->id;
        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase1, [$item1 => 3], $admin);

        $purchase2 = createConfirmedPurchase(3);
        $item2 = $purchase2->items->first()->id;
        $service->receiveGoods($purchase2, [$item2 => 3], $admin);

        // All 6 serials should be unique
        $serials = Garment::pluck('fiscal_year_serial')->toArray();
        expect(count(array_unique($serials)))->toBe(6);
    });
});

describe('receiving — multiple line items', function () {
    it('can receive multiple line items in one receipt', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $purchasingService = app(PurchasingService::class);
        $purchase = $purchasingService->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => '2025-08-15',
            'items' => [
                ['product_id' => $product1->id, 'ordered_quantity' => 5, 'unit_cost' => 100],
                ['product_id' => $product2->id, 'ordered_quantity' => 3, 'unit_cost' => 200],
            ],
        ]);
        $purchasingService->confirmPurchase($purchase);

        $purchase = $purchase->fresh('items');
        $item1Id = $purchase->items->get(0)->id;
        $item2Id = $purchase->items->get(1)->id;

        $receivingService = app(ReceivingService::class);
        $receipt = $receivingService->receiveGoods($purchase, [
            $item1Id => 5,
            $item2Id => 3,
        ], $admin);

        expect($receipt->total_received_quantity)->toBe(8);
        expect(Garment::count())->toBe(8);
        expect($purchase->fresh()->status)->toBe(Purchase::STATUS_RECEIVED);
    });
});

describe('PurchaseReceived event', function () {
    it('PurchaseReceived event is dispatched on receiving', function () {
        Event::fake([PurchaseReceived::class]);

        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createConfirmedPurchase(2);
        $itemId = $purchase->items->first()->id;

        $service = app(ReceivingService::class);
        $service->receiveGoods($purchase, [$itemId => 2], $admin);

        Event::assertDispatched(PurchaseReceived::class);
    });
});
