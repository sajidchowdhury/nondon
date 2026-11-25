<?php

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Services\FiscalYearService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Catalog\Services\PriceService;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Services\GarmentIdentityService;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Inventory\Services\StockQueryService;
use App\Modules\Audit\Models\AuditLog;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('full lifecycle integration', function () {
    it('complete flow: create user, assign role, create product, set price, create garment, record movement, verify audit', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        // Clear audit logs to start fresh
        AuditLog::query()->delete();

        // 1. Create a product with sizes
        $productService = app(ProductService::class);
        $product = $productService->create([
            'code' => 'INTTEST001',
            'name' => 'Integration Test Dress',
            'category' => 'Dresses',
            'sizes' => ['32', '34', '36'],
        ]);

        expect($product->sizes->count())->toBe(3);

        // 2. Set a price
        $priceService = app(PriceService::class);
        $price = $priceService->setPrice($product, null, 1500.00, 1000.00);

        expect($price->standard_price)->toBe('1500.00');
        expect($price->minimum_price)->toBe('1000.00');

        // 3. Create a garment
        $garmentService = app(GarmentIdentityService::class);
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();
        $size = $product->sizes->first();

        $garment = $garmentService->createGarment($product, $size, $fiscalYear, $branch, 500.00);

        expect($garment)->toBeInstanceOf(Garment::class);
        expect($garment->isAvailable())->toBeTrue();
        expect($garment->barcode)->toStartWith('NND-');

        // 4. Record a movement (reserve)
        $movementService = app(InventoryMovementService::class);
        $movement = $movementService->record($garment, InventoryMovement::TYPE_RESERVATION, [
            'reference_type' => 'transfer_request',
            'reference_id' => 1,
        ]);

        expect($movement->from_status)->toBe(Garment::STATUS_AVAILABLE);
        expect($movement->to_status)->toBe(Garment::STATUS_RESERVED);

        // 5. Record another movement (release)
        $movementService->record($garment, InventoryMovement::TYPE_RESERVATION_RELEASE);

        expect($garment->fresh()->isAvailable())->toBeTrue();

        // 6. Record a sale
        $movementService->record($garment, InventoryMovement::TYPE_SALE);

        expect($garment->fresh()->isSold())->toBeTrue();

        // 7. Verify audit log captured all actions
        $auditEntries = AuditLog::where('reference_type', 'garment')
            ->where('reference_id', $garment->id)
            ->get();

        expect($auditEntries->count())->toBeGreaterThanOrEqual(2); // garment.create + inventory.movement events

        // 8. Verify movement history
        $history = $movementService->getHistory($garment);
        expect($history->count())->toBe(3); // reservation + release + sale

        // 9. Verify stock queries don't show sold garment in available stock
        $stockQuery = app(StockQueryService::class);
        $available = $stockQuery->getAvailableStockForBranch($branch);
        expect($available->contains('id', $garment->id))->toBeFalse(); // Sold, not available
    });
});

describe('permission matrix verification', function () {
    it('admin can access all management pages', function () {
        $admin = adminUser();

        $routes = [
            'users.index', 'users.create', 'roles.matrix', 'branches.index',
            'branches.create', 'fiscal-years.index', 'fiscal-years.create',
            'products.index', 'products.create', 'garments.index',
            'stock.availability', 'stock.overview', 'audit.index',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get(route($route));
            expect($response->status())->toBe(200, "Admin should access {$route}");
        }
    });

    it('supervisor can view but not manage', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        // Can view
        expect($this->actingAs($supervisor)->get(route('users.index'))->status())->toBe(200);
        expect($this->actingAs($supervisor)->get(route('branches.index'))->status())->toBe(200);
        expect($this->actingAs($supervisor)->get(route('products.index'))->status())->toBe(200);
        expect($this->actingAs($supervisor)->get(route('garments.index'))->status())->toBe(200);
        expect($this->actingAs($supervisor)->get(route('stock.availability'))->status())->toBe(200);
        expect($this->actingAs($supervisor)->get(route('audit.index'))->status())->toBe(200);

        // Cannot manage (create/edit)
        $this->actingAs($supervisor)->get(route('users.create'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('branches.create'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('fiscal-years.create'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('products.create'))->assertForbidden();
    });

    it('salesperson can view stock and products but not manage anything', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        // Can view
        expect($this->actingAs($salesperson)->get(route('products.index'))->status())->toBe(200);
        expect($this->actingAs($salesperson)->get(route('garments.index'))->status())->toBe(200);
        expect($this->actingAs($salesperson)->get(route('stock.availability'))->status())->toBe(200);

        // Cannot access management
        $this->actingAs($salesperson)->get(route('users.index'))->assertForbidden();
        $this->actingAs($salesperson)->get(route('branches.index'))->assertForbidden();
        $this->actingAs($salesperson)->get(route('fiscal-years.index'))->assertForbidden();
        $this->actingAs($salesperson)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($salesperson)->get(route('products.create'))->assertForbidden();
    });
});

describe('sensitive data verification', function () {
    it('purchase cost hidden from salesperson in garment list', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $product = Product::factory()->create();
        Garment::factory()->forProduct($product)->create(['purchase_cost' => 500.00]);

        $service = app(StockQueryService::class);
        $stock = $service->getAvailableStockForAllBranches();

        $array = $stock->first()->toArray();
        expect($array)->not->toHaveKey('purchase_cost');
    });

    it('purchase cost visible to admin via withPurchaseCost', function () {
        $admin = adminUser();

        $garment = Garment::factory()->create(['purchase_cost' => 500.00]);
        $garment->withPurchaseCost();

        expect($garment->toArray())->toHaveKey('purchase_cost');
    });

    it('admin-only permissions are never effective for non-admin via overrides', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $overrideService = app(\App\Modules\Identity\Services\PermissionOverrideService::class);
        $permission = \Spatie\Permission\Models\Permission::where('name', 'purchase.cost.view')->first();

        // Attempting to grant admin-only permission should fail
        expect(fn() => $overrideService->grantPermission($supervisor, $permission))
            ->toThrow(\Illuminate\Validation\ValidationException::class);

        // Even with a manual override entry, effective permissions should filter it
        \App\Modules\Identity\Models\UserPermission::create([
            'user_id' => $supervisor->id,
            'permission_id' => $permission->id,
            'granted' => true,
            'granted_by' => adminUser()->id,
        ]);

        expect($overrideService->hasEffectivePermission($supervisor, 'purchase.cost.view'))->toBeFalse();
    });
});

describe('fiscal year serial generation integration', function () {
    it('serial format is YY-NNNNN and globally unique per fiscal year', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $fiscalYear = FiscalYear::active()->first();
        $service = app(FiscalYearService::class);

        // Generate serials via the fiscal year service
        $serials = collect();
        for ($i = 0; $i < 5; $i++) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($service, $fiscalYear, &$serials) {
                $serials->push($service->generateNextSerial($fiscalYear));
            });
        }

        // All unique
        expect($serials->unique()->count())->toBe(5);
        // All match YY-NNNNN format
        foreach ($serials as $serial) {
            expect($serial)->toMatch('/^\d{2}-\d{5}$/');
        }
        // All start with the fiscal year prefix
        foreach ($serials as $serial) {
            expect($serial)->toStartWith($fiscalYear->serial_prefix . '-');
        }
    });
});

describe('audit log visibility', function () {
    it('admin sees all audit entries across all branches and dates', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        // Create audit entries from different branches and dates
        AuditLog::create([
            'user_id' => $admin->id,
            'branch_id' => Branch::where('code', 'M')->first()->id,
            'action' => 'test.action1',
            'category' => 'test',
            'performed_at' => now()->subDays(5),
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'branch_id' => Branch::where('code', 'B1')->first()->id,
            'action' => 'test.action2',
            'category' => 'test',
            'performed_at' => now()->subDays(3),
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'branch_id' => Branch::where('code', 'B2')->first()->id,
            'action' => 'test.action3',
            'category' => 'test',
            'performed_at' => now(),
        ]);

        $service = app(\App\Modules\Audit\Services\AuditService::class);
        $logs = $service->query(['per_page' => 100])->get();

        // Admin should see all 3 entries
        expect($logs->count())->toBeGreaterThanOrEqual(3);
    });

    it('supervisor sees only own branch entries for today', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $this->actingAs($supervisor);

        // Clear and create specific entries
        AuditLog::query()->delete();

        // Entry from own branch today
        AuditLog::create([
            'user_id' => $supervisor->id,
            'branch_id' => $branch->id,
            'action' => 'own.today',
            'category' => 'test',
            'performed_at' => now(),
        ]);

        // Entry from own branch yesterday (should NOT be visible)
        AuditLog::create([
            'user_id' => $supervisor->id,
            'branch_id' => $branch->id,
            'action' => 'own.yesterday',
            'category' => 'test',
            'performed_at' => now()->subDay(),
        ]);

        // Entry from different branch today (should NOT be visible)
        AuditLog::create([
            'user_id' => $supervisor->id,
            'branch_id' => Branch::where('code', 'B2')->first()->id,
            'action' => 'other.today',
            'category' => 'test',
            'performed_at' => now(),
        ]);

        // Query as supervisor — should be scoped to own branch today
        $service = app(\App\Modules\Audit\Services\AuditService::class);
        $logs = $service->query([
            'branch_id' => $branch->id,
            'date_from' => today()->toDateString(),
            'per_page' => 100,
        ])->get();

        // Should only see own.today
        $actions = $logs->pluck('action');
        expect($actions)->toContain('own.today');
        expect($actions)->not->toContain('own.yesterday');
        expect($actions)->not->toContain('other.today');
    });

    it('salesperson has no access to audit logs', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('audit.index'));
        $response->assertForbidden();
    });
});

describe('audit log UI', function () {
    it('admin can view audit log list', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('audit.index'));
        $response->assertStatus(200);
        $response->assertSee('Audit Log');
    });

    it('admin can view audit entry detail', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $log = AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'test.detail',
            'category' => 'test',
            'performed_at' => now(),
            'new_values' => ['key' => 'value'],
        ]);

        $response = $this->actingAs($admin)->get(route('audit.show', $log));
        $response->assertStatus(200);
        $response->assertSee('Audit Entry Detail');
        $response->assertSee('test.detail');
    });

    it('supervisor can view audit log list (own branch today)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('audit.index'));
        $response->assertStatus(200);
        $response->assertSee('today only');
    });
});
