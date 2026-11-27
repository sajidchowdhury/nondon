<?php

use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchaseItem;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Catalog\Models\Product;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

/**
 * Helper: create a valid purchase data array.
 */
function validPurchaseData(Supplier $supplier, FiscalYear $fiscalYear, Product $product): array
{
    return [
        'supplier_id' => $supplier->id,
        'fiscal_year_id' => $fiscalYear->id,
        'purchase_date' => '2025-08-15',
        'expected_delivery_date' => '2025-09-01',
        'notes' => 'Test purchase',
        'items' => [
            [
                'product_id' => $product->id,
                'product_size_id' => null,
                'ordered_quantity' => 10,
                'unit_cost' => 250.00,
            ],
        ],
    ];
}

describe('purchase creation', function () {
    it('admin can create a draft purchase with line items', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));

        expect($purchase->status)->toBe(Purchase::STATUS_DRAFT);
        expect($purchase->supplier_id)->toBe($supplier->id);
        expect($purchase->fiscal_year_id)->toBe($fiscalYear->id);
        expect($purchase->items)->toHaveCount(1);
        expect($purchase->items->first()->ordered_quantity)->toBe(10);
        expect($purchase->items->first()->received_quantity)->toBe(0);
        expect($purchase->created_by)->toBe($admin->id);
    });

    it('purchase number is auto-generated and unique', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase1 = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));
        $purchase2 = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));

        expect($purchase1->purchase_number)->not->toBe($purchase2->purchase_number);
        expect($purchase1->purchase_number)->toStartWith('PO-' . $fiscalYear->serial_prefix . '-');
    });

    it('cannot create purchase with inactive supplier', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->inactive()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);

        expect(fn () => $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product)))
            ->toThrow(ValidationException::class);
    });

    it('cannot create purchase in a closed fiscal year', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::factory()->create([
            'serial_prefix' => '24',
            'is_active' => false,
            'is_closed' => true,
        ]);
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);

        expect(fn () => $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product)))
            ->toThrow(ValidationException::class);
    });

    it('cannot create purchase with zero quantity items', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);

        $data = validPurchaseData($supplier, $fiscalYear, $product);
        $data['items'][0]['ordered_quantity'] = 0;

        expect(fn () => $service->createPurchase($data))
            ->toThrow(ValidationException::class);
    });

    it('cannot create purchase with negative unit cost', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);

        $data = validPurchaseData($supplier, $fiscalYear, $product);
        $data['items'][0]['unit_cost'] = -50;

        expect(fn () => $service->createPurchase($data))
            ->toThrow(ValidationException::class);
    });

    it('cannot create purchase with empty items list', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();

        $service = app(PurchasingService::class);

        expect(fn () => $service->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => '2025-08-15',
            'items' => [],
        ]))->toThrow(ValidationException::class);
    });

    it('total_cost is computed correctly from line items', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => '2025-08-15',
            'items' => [
                ['product_id' => $product1->id, 'ordered_quantity' => 10, 'unit_cost' => 250.00],
                ['product_id' => $product2->id, 'ordered_quantity' => 5, 'unit_cost' => 500.00],
            ],
        ]);

        // 10*250 + 5*500 = 2500 + 2500 = 5000
        expect((float) $purchase->total_cost)->toBe(5000.00);
    });

    it('can create purchase with multiple line items', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'purchase_date' => '2025-08-15',
            'items' => [
                ['product_id' => $product1->id, 'ordered_quantity' => 10, 'unit_cost' => 100.00],
                ['product_id' => $product2->id, 'ordered_quantity' => 20, 'unit_cost' => 200.00],
                ['product_id' => $product1->id, 'ordered_quantity' => 5, 'unit_cost' => 150.00],
            ],
        ]);

        expect($purchase->items)->toHaveCount(3);
    });
});

describe('purchase update (draft only)', function () {
    it('admin can update a draft purchase', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));

        $updated = $service->updateDraftPurchase($purchase, [
            'notes' => 'Updated notes',
            'expected_delivery_date' => '2025-10-01',
        ]);

        expect($updated->notes)->toBe('Updated notes');
        expect($updated->expected_delivery_date->format('Y-m-d'))->toBe('2025-10-01');
    });

    it('cannot update a confirmed purchase', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));
        $service->confirmPurchase($purchase);

        expect(fn () => $service->updateDraftPurchase($purchase->fresh(), ['notes' => 'Try update']))
            ->toThrow(ValidationException::class);
    });

    it('updating items replaces the line items', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product1));

        $updated = $service->updateDraftPurchase($purchase, [
            'items' => [
                ['product_id' => $product2->id, 'ordered_quantity' => 20, 'unit_cost' => 300.00],
            ],
        ]);

        expect($updated->items)->toHaveCount(1);
        expect($updated->items->first()->product_id)->toBe($product2->id);
        expect((float) $updated->total_cost)->toBe(6000.00);
    });
});

describe('purchase confirmation', function () {
    it('admin can confirm a draft purchase', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));

        $confirmed = $service->confirmPurchase($purchase);

        expect($confirmed->status)->toBe(Purchase::STATUS_CONFIRMED);
        expect($confirmed->confirmed_at)->not->toBeNull();
        expect($confirmed->confirmed_by)->toBe($admin->id);
    });

    it('cannot confirm an already-confirmed purchase', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));
        $service->confirmPurchase($purchase);

        expect(fn () => $service->confirmPurchase($purchase->fresh()))
            ->toThrow(ValidationException::class);
    });

    it('cannot confirm a cancelled purchase', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));
        $service->cancelPurchase($purchase, 'No longer needed');

        expect(fn () => $service->confirmPurchase($purchase->fresh()))
            ->toThrow(ValidationException::class);
    });
});

describe('purchase cancellation', function () {
    it('admin can cancel a draft purchase', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));

        $cancelled = $service->cancelPurchase($purchase, 'Duplicate order');

        expect($cancelled->status)->toBe(Purchase::STATUS_CANCELLED);
        expect($cancelled->notes)->toContain('Duplicate order');
    });

    it('admin can cancel a confirmed purchase', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));
        $service->confirmPurchase($purchase);

        $cancelled = $service->cancelPurchase($purchase->fresh(), 'Supplier issue');
        expect($cancelled->status)->toBe(Purchase::STATUS_CANCELLED);
    });

    it('cannot cancel a received purchase', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));
        $service->confirmPurchase($purchase);

        // Manually set status to received (goods receiving is Session 2.4)
        $purchase->fresh()->update(['status' => Purchase::STATUS_RECEIVED]);

        expect(fn () => $service->cancelPurchase($purchase->fresh(), 'Test'))
            ->toThrow(ValidationException::class);
    });
});

describe('purchase list and filtering', function () {
    it('returns paginated list of purchases', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        Purchase::factory()->count(3)->create();

        $service = app(PurchasingService::class);
        $paginated = $service->getPurchaseList();

        expect($paginated->count())->toBeGreaterThanOrEqual(3);
    });

    it('filters by status', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        Purchase::factory()->draft()->create();
        Purchase::factory()->confirmed()->create();
        Purchase::factory()->cancelled()->create();

        $service = app(PurchasingService::class);

        $drafts = $service->getPurchaseList(['status' => 'draft']);
        $confirmed = $service->getPurchaseList(['status' => 'confirmed']);

        expect($drafts->every(fn ($p) => $p->status === 'draft'))->toBeTrue();
        expect($confirmed->every(fn ($p) => $p->status === 'confirmed'))->toBeTrue();
    });

    it('filters by supplier', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();
        Purchase::factory()->create(['supplier_id' => $supplier1->id]);
        Purchase::factory()->create(['supplier_id' => $supplier2->id]);

        $service = app(PurchasingService::class);
        $results = $service->getPurchaseList(['supplier_id' => $supplier1->id]);

        expect($results->every(fn ($p) => $p->supplier_id === $supplier1->id))->toBeTrue();
    });

    it('searches by purchase number', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        Purchase::factory()->create(['purchase_number' => 'PO-25-FINDME']);
        Purchase::factory()->create(['purchase_number' => 'PO-25-OTHER']);

        $service = app(PurchasingService::class);
        $results = $service->getPurchaseList(['search' => 'FINDME']);

        expect($results->count())->toBe(1);
        expect($results->first()->purchase_number)->toBe('PO-25-FINDME');
    });
});

describe('sensitive data restrictions', function () {
    it('total_cost is hidden from serialization by default', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = Purchase::factory()->create(['total_cost' => 5000.00]);

        $array = $purchase->toArray();
        expect($array)->not->toHaveKey('total_cost');
    });

    it('total_cost is visible via withTotalCost()', function () {
        $purchase = Purchase::factory()->create(['total_cost' => 5000.00]);

        $array = $purchase->withTotalCost()->toArray();
        expect($array)->toHaveKey('total_cost');
        expect($array['total_cost'])->toBe('5000.00');
    });

    it('unit_cost is hidden from PurchaseItem serialization by default', function () {
        $item = PurchaseItem::factory()->create(['unit_cost' => 250.00]);

        $array = $item->toArray();
        expect($array)->not->toHaveKey('unit_cost');
    });

    it('unit_cost is visible via withUnitCost()', function () {
        $item = PurchaseItem::factory()->create(['unit_cost' => 250.00]);

        $array = $item->withUnitCost()->toArray();
        expect($array)->toHaveKey('unit_cost');
        expect($array['unit_cost'])->toBe('250.00');
    });
});

describe('authorization — policy enforcement', function () {
    it('admin can create purchases', function () {
        $admin = adminUser();
        expect($admin->can('create', Purchase::class))->toBeTrue();
    });

    it('supervisor cannot create purchases', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        expect($supervisor->can('create', Purchase::class))->toBeFalse();
    });

    it('salesperson cannot create purchases', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        expect($salesperson->can('create', Purchase::class))->toBeFalse();
    });

    it('admin can view purchases', function () {
        $admin = adminUser();
        $purchase = Purchase::factory()->create();
        expect($admin->can('view', $purchase))->toBeTrue();
        expect($admin->can('viewAny', Purchase::class))->toBeTrue();
    });

    it('supervisor cannot view purchases', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $purchase = Purchase::factory()->create();
        expect($supervisor->can('view', $purchase))->toBeFalse();
        expect($supervisor->can('viewAny', Purchase::class))->toBeFalse();
    });

    it('admin can confirm a draft purchase', function () {
        $admin = adminUser();
        $purchase = Purchase::factory()->draft()->create();
        expect($admin->can('confirm', $purchase))->toBeTrue();
    });

    it('admin cannot confirm a non-draft purchase', function () {
        $admin = adminUser();
        $purchase = Purchase::factory()->confirmed()->create();
        expect($admin->can('confirm', $purchase))->toBeFalse();
    });

    it('admin can cancel a draft or confirmed purchase', function () {
        $admin = adminUser();
        $draft = Purchase::factory()->draft()->create();
        $confirmed = Purchase::factory()->confirmed()->create();
        expect($admin->can('cancel', $draft))->toBeTrue();
        expect($admin->can('cancel', $confirmed))->toBeTrue();
    });

    it('admin cannot cancel a received purchase', function () {
        $admin = adminUser();
        $purchase = Purchase::factory()->received()->create();
        expect($admin->can('cancel', $purchase))->toBeFalse();
    });

    it('supervisor can receive goods (purchase.receive permission)', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $purchase = Purchase::factory()->confirmed()->create();
        expect($supervisor->can('receive', $purchase))->toBeTrue();
    });

    it('salesperson cannot receive goods', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $purchase = Purchase::factory()->confirmed()->create();
        expect($salesperson->can('receive', $purchase))->toBeFalse();
    });

    it('admin can view purchase cost', function () {
        $admin = adminUser();
        $purchase = Purchase::factory()->create();
        expect($admin->can('viewCost', $purchase))->toBeTrue();
    });

    it('supervisor cannot view purchase cost', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $purchase = Purchase::factory()->create();
        expect($supervisor->can('viewCost', $purchase))->toBeFalse();
    });

    it('purchases can never be deleted', function () {
        $admin = adminUser();
        $purchase = Purchase::factory()->create();
        expect($admin->can('delete', $purchase))->toBeFalse();
    });
});

describe('auditing', function () {
    it('purchase creation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'purchase.create',
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
        ]);
    });

    it('purchase confirmation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));
        $service->confirmPurchase($purchase);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'purchase.confirm',
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
        ]);
    });

    it('purchase cancellation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $supplier = Supplier::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $product = Product::factory()->create();

        $service = app(PurchasingService::class);
        $purchase = $service->createPurchase(validPurchaseData($supplier, $fiscalYear, $product));
        $service->cancelPurchase($purchase, 'Test cancellation');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'purchase.cancel',
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
        ]);
    });
});

describe('purchase item helpers', function () {
    it('isFullyReceived returns true when received equals ordered', function () {
        $item = PurchaseItem::factory()->create([
            'ordered_quantity' => 10,
            'received_quantity' => 10,
        ]);
        expect($item->isFullyReceived())->toBeTrue();
    });

    it('isFullyReceived returns false when received is less than ordered', function () {
        $item = PurchaseItem::factory()->create([
            'ordered_quantity' => 10,
            'received_quantity' => 5,
        ]);
        expect($item->isFullyReceived())->toBeFalse();
    });

    it('remainingQuantity returns correct value', function () {
        $item = PurchaseItem::factory()->create([
            'ordered_quantity' => 10,
            'received_quantity' => 3,
        ]);
        expect($item->remainingQuantity())->toBe(7);
    });
});
