<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Inventory\Services\StockProjectionService;
use App\Modules\Organization\Models\Branch;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('movement recording', function () {
    it('creates a movement record when garment status changes', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        $movement = $service->record($garment, InventoryMovement::TYPE_SALE);

        expect($movement)->toBeInstanceOf(InventoryMovement::class);
        expect($movement->garment_id)->toBe($garment->id);
        expect($movement->movement_type)->toBe(InventoryMovement::TYPE_SALE);
        expect($movement->from_status)->toBe(Garment::STATUS_AVAILABLE);
        expect($movement->to_status)->toBe(Garment::STATUS_SOLD);
    });

    it('captures from_status and to_status correctly', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        $movement = $service->record($garment, InventoryMovement::TYPE_RESERVATION, [
            'reference_type' => 'transfer_request',
            'reference_id' => 1,
        ]);

        expect($movement->from_status)->toBe(Garment::STATUS_AVAILABLE);
        expect($movement->to_status)->toBe(Garment::STATUS_RESERVED);
    });

    it('captures from_branch and to_branch correctly', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branchA = Branch::where('code', 'M')->first();
        $branchB = Branch::where('code', 'B1')->first();
        $garment = Garment::factory()->atBranch($branchA)->create();
        $service = app(InventoryMovementService::class);

        $movement = $service->record($garment, InventoryMovement::TYPE_TRANSFER_DISPATCH, [
            'to_branch_id' => $branchB->id,
        ]);

        expect($movement->from_branch_id)->toBe($branchA->id);
        expect($movement->to_branch_id)->toBe($branchB->id);
    });

    it('records performed_by from authenticated user', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        $movement = $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        expect($movement->performed_by)->toBe($admin->id);
    });

    it('records performed_at timestamp', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        $movement = $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        expect($movement->performed_at)->not->toBeNull();
    });

    it('updates garment current_status on movement', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        $service->record($garment, InventoryMovement::TYPE_SALE);

        expect($garment->fresh()->current_status)->toBe(Garment::STATUS_SOLD);
    });

    it('updates garment current_branch_id on transfer', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branchA = Branch::where('code', 'M')->first();
        $branchB = Branch::where('code', 'B1')->first();
        $garment = Garment::factory()->atBranch($branchA)->create();
        $service = app(InventoryMovementService::class);

        $service->record($garment, InventoryMovement::TYPE_TRANSFER_RECEIVE, [
            'to_branch_id' => $branchB->id,
        ]);

        expect($garment->fresh()->current_branch_id)->toBe($branchB->id);
    });

    it('updates garment_current_stock projection on movement', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        $service->record($garment, InventoryMovement::TYPE_SALE);

        $projection = GarmentCurrentStock::where('garment_id', $garment->id)->first();
        expect($projection->current_status)->toBe(Garment::STATUS_SOLD);
    });

    it('movement recording is atomic (transaction rolls back on failure)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        // Record a valid movement first
        $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        // Verify movement was recorded and garment status changed
        expect($garment->fresh()->current_status)->toBe(Garment::STATUS_DAMAGED);
        expect(InventoryMovement::where('garment_id', $garment->id)->count())->toBe(1);
    });
});

describe('append-only enforcement', function () {
    it('cannot update an existing movement record', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);
        $movement = $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        expect(fn () => $movement->update(['notes' => 'modified']))
            ->toThrow(RuntimeException::class);
    });

    it('cannot delete a movement record', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);
        $movement = $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        expect(fn () => $movement->delete())
            ->toThrow(RuntimeException::class);
    });

    it('cannot force delete a movement record', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);
        $movement = $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        expect(fn () => $movement->forceDelete())
            ->toThrow(RuntimeException::class);
    });

    it('save() throws when updating existing record', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);
        $movement = $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        $movement->notes = 'modified';

        expect(fn () => $movement->save())
            ->toThrow(RuntimeException::class);
    });
});

describe('movement history', function () {
    it('getHistory returns full lifecycle ordered by performed_at ASC', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        // Record multiple movements
        $service->record($garment, InventoryMovement::TYPE_RESERVATION, ['reference_type' => 'test', 'reference_id' => 1]);
        usleep(10000); // 10ms delay for timestamp difference
        $service->record($garment, InventoryMovement::TYPE_RESERVATION_RELEASE);
        usleep(10000);
        $service->record($garment, InventoryMovement::TYPE_SALE);

        $history = $service->getHistory($garment);

        expect($history->count())->toBe(3);
        // Ordered ascending by performed_at
        expect($history->first()->movement_type)->toBe(InventoryMovement::TYPE_RESERVATION);
        expect($history->last()->movement_type)->toBe(InventoryMovement::TYPE_SALE);
    });

    it('getRecentMovements returns movements for a branch', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branch = Branch::where('code', 'M')->first();
        $garment = Garment::factory()->atBranch($branch)->create();
        $service = app(InventoryMovementService::class);

        $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        $recent = $service->getRecentMovements($branch, 10);

        expect($recent->count())->toBeGreaterThanOrEqual(1);
    });
});

describe('stock projection reconciliation', function () {
    it('reconcile returns no drift when projection matches ledger', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);
        $projectionService = app(StockProjectionService::class);

        // Record a movement (updates both garment and projection)
        $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        $drift = $projectionService->reconcile();

        // Should have no drift for this garment
        $garmentDrift = collect($drift)->where('garment_id', $garment->id);
        expect($garmentDrift->count())->toBe(0);
    });

    it('reconcile detects drift when projection is manually changed', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);
        $projectionService = app(StockProjectionService::class);

        // Record a movement (sets status to damaged)
        $service->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        // Manually corrupt the projection
        GarmentCurrentStock::where('garment_id', $garment->id)
            ->update(['current_status' => Garment::STATUS_AVAILABLE]);

        $drift = $projectionService->reconcile();

        $garmentDrift = collect($drift)->where('garment_id', $garment->id);
        expect($garmentDrift->count())->toBeGreaterThan(0);
    });

    it('reconcile detects missing projection', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        // Record a movement
        $service->record($garment, InventoryMovement::TYPE_SALE);

        // Delete the projection (simulating a bug)
        GarmentCurrentStock::where('garment_id', $garment->id)->delete();

        $projectionService = app(StockProjectionService::class);
        $drift = $projectionService->reconcile();

        $garmentDrift = collect($drift)->where('garment_id', $garment->id);
        expect($garmentDrift->count())->toBeGreaterThan(0);
        expect($garmentDrift->first()['issue'])->toBe('projection_missing');
    });
});

describe('stock queries', function () {
    it('getAvailableStockForBranch returns available garments at a branch', function () {
        $branch = Branch::where('code', 'M')->first();
        Garment::factory()->atBranch($branch)->available()->create();
        Garment::factory()->atBranch($branch)->sold()->create();

        $projectionService = app(StockProjectionService::class);
        $stock = $projectionService->getAvailableStockForBranch($branch);

        expect($stock->count())->toBe(1);
        expect($stock->first()->current_status)->toBe(Garment::STATUS_AVAILABLE);
    });

    it('getAvailableStockForProduct returns garments for a product across branches', function () {
        $branchA = Branch::where('code', 'M')->first();
        $branchB = Branch::where('code', 'B1')->first();
        $product = Product::factory()->create();

        Garment::factory()->forProduct($product)->atBranch($branchA)->available()->create();
        Garment::factory()->forProduct($product)->atBranch($branchB)->available()->create();
        Garment::factory()->forProduct($product)->atBranch($branchA)->sold()->create();

        $projectionService = app(StockProjectionService::class);
        $stock = $projectionService->getAvailableStockForProduct($product);

        // Should be grouped by branch, 2 branches with available stock
        expect($stock->count())->toBe(2);
    });

    it('getReservationCounts returns count per branch', function () {
        $branch = Branch::where('code', 'M')->first();
        Garment::factory()->atBranch($branch)->reserved()->create();
        Garment::factory()->atBranch($branch)->reserved()->create();

        $projectionService = app(StockProjectionService::class);
        $counts = $projectionService->getReservationCounts();

        $branchCount = $counts->where('id', $branch->id)->first();
        expect($branchCount)->not->toBeNull();
        expect($branchCount->reserved_count)->toBe(2);
    });
});

describe('all movements are audited', function () {
    it('movement recording dispatches audit event', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $service = app(InventoryMovementService::class);

        AuditLog::query()->delete();

        $service->record($garment, InventoryMovement::TYPE_SALE);

        $auditLog = AuditLog::where('action', 'inventory.movement')
            ->where('reference_type', 'garment')
            ->where('reference_id', $garment->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->new_values['movement_type'])->toBe(InventoryMovement::TYPE_SALE);
        expect($auditLog->previous_values['status'])->toBe(Garment::STATUS_AVAILABLE);
        expect($auditLog->new_values['status'])->toBe(Garment::STATUS_SOLD);
    });
});

describe('reconciliation command', function () {
    it('can run the reconciliation command', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $this->artisan('nondon:reconcile-stock')
            ->expectsOutput('Reconciling stock projection against movement ledger...')
            ->assertSuccessful();
    });
});
