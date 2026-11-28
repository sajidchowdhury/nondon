<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Inventory\Services\MovementHistoryService;
use App\Modules\Inventory\Services\StockQueryService;
use App\Modules\Organization\Models\Branch;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('StockQueryService — available stock', function () {
    it('getAvailableStockForBranch returns only available garments at a branch', function () {
        $branch = Branch::where('code', 'M')->first();
        Garment::factory()->atBranch($branch)->available()->create();
        Garment::factory()->atBranch($branch)->sold()->create();

        $service = app(StockQueryService::class);
        $stock = $service->getAvailableStockForBranch($branch);

        expect($stock->count())->toBe(1);
        expect($stock->first()->current_status)->toBe(Garment::STATUS_AVAILABLE);
    });

    it('getAvailableStockForAllBranches returns available garments grouped by branch', function () {
        $branchA = Branch::where('code', 'M')->first();
        $branchB = Branch::where('code', 'B1')->first();
        Garment::factory()->atBranch($branchA)->available()->create();
        Garment::factory()->atBranch($branchB)->available()->create();
        Garment::factory()->atBranch($branchA)->sold()->create();

        $service = app(StockQueryService::class);
        $stock = $service->getAvailableStockForAllBranches();

        expect($stock->count())->toBe(2); // Only available garments
    });

    it('getStockForProduct returns available garments for a product across branches', function () {
        $branchA = Branch::where('code', 'M')->first();
        $branchB = Branch::where('code', 'B1')->first();
        $product = Product::factory()->create();

        Garment::factory()->forProduct($product)->atBranch($branchA)->available()->create();
        Garment::factory()->forProduct($product)->atBranch($branchB)->available()->create();
        Garment::factory()->forProduct($product)->atBranch($branchA)->sold()->create();

        $service = app(StockQueryService::class);
        $stock = $service->getStockForProduct($product);

        // Grouped by branch, 2 branches
        expect($stock->count())->toBe(2);
    });
});

describe('StockQueryService — search', function () {
    it('searchGarments by barcode', function () {
        $product = Product::factory()->create(['name' => 'Test Dress']);
        $garment = Garment::factory()->forProduct($product)->create([
            'barcode' => 'NND-25-TEST01',
        ]);

        $service = app(StockQueryService::class);
        $results = $service->searchGarments('NND-25-TEST01');

        expect($results->count())->toBe(1);
        expect($results->first()->barcode)->toBe('NND-25-TEST01');
    });

    it('searchGarments by serial', function () {
        $garment = Garment::factory()->create([
            'fiscal_year_serial' => '25-TEST99',
        ]);

        $service = app(StockQueryService::class);
        $results = $service->searchGarments('25-TEST99');

        expect($results->count())->toBeGreaterThanOrEqual(1);
    });

    it('searchGarments by product name', function () {
        $product = Product::factory()->create(['name' => 'Special Summer Dress']);
        Garment::factory()->forProduct($product)->create();

        $service = app(StockQueryService::class);
        $results = $service->searchGarments('Special Summer');

        expect($results->count())->toBeGreaterThanOrEqual(1);
    });
});

describe('StockQueryService — purchase cost never exposed', function () {
    it('purchase_cost is never in available stock results', function () {
        $branch = Branch::where('code', 'M')->first();
        Garment::factory()->atBranch($branch)->available()->create(['purchase_cost' => 500.00]);

        $service = app(StockQueryService::class);
        $stock = $service->getAvailableStockForBranch($branch);

        $array = $stock->first()->toArray();
        expect($array)->not->toHaveKey('purchase_cost');
    });

    it('purchase_cost is never in search results', function () {
        Garment::factory()->create([
            'barcode' => 'NND-SEARCH-01',
            'purchase_cost' => 500.00,
        ]);

        $service = app(StockQueryService::class);
        $results = $service->searchGarments('NND-SEARCH-01');

        $array = $results->first()->toArray();
        expect($array)->not->toHaveKey('purchase_cost');
    });

    it('purchase_cost is never in all-branches results', function () {
        $branch = Branch::where('code', 'M')->first();
        Garment::factory()->atBranch($branch)->available()->create(['purchase_cost' => 500.00]);

        $service = app(StockQueryService::class);
        $stock = $service->getAvailableStockForAllBranches();

        $array = $stock->first()->toArray();
        expect($array)->not->toHaveKey('purchase_cost');
    });
});

describe('StockQueryService — reservation counts', function () {
    it('getReservationCounts returns count per branch', function () {
        $branch = Branch::where('code', 'M')->first();
        Garment::factory()->atBranch($branch)->reserved()->create();
        Garment::factory()->atBranch($branch)->reserved()->create();

        $service = app(StockQueryService::class);
        $counts = $service->getReservationCounts();

        $branchCount = $counts->where('id', $branch->id)->first();
        expect($branchCount)->not->toBeNull();
        expect($branchCount->reserved_count)->toBe(2);
    });

    it('branches with no reservations are not included', function () {
        $branch = Branch::where('code', 'B2')->first();
        Garment::factory()->atBranch($branch)->available()->create();

        $service = app(StockQueryService::class);
        $counts = $service->getReservationCounts();

        $branchCount = $counts->where('id', $branch->id)->first();
        expect($branchCount)->toBeNull();
    });
});

describe('MovementHistoryService — garment lifecycle', function () {
    it('getGarmentHistory returns movements in chronological order', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $movementService = app(InventoryMovementService::class);
        $historyService = app(MovementHistoryService::class);

        // Record multiple movements
        $movementService->record($garment, InventoryMovement::TYPE_RESERVATION, ['reference_type' => 'test', 'reference_id' => 1]);
        usleep(10000);
        $movementService->record($garment, InventoryMovement::TYPE_RESERVATION_RELEASE);
        usleep(10000);
        $movementService->record($garment, InventoryMovement::TYPE_SALE);

        $history = $historyService->getGarmentHistory($garment);

        expect($history->count())->toBe(3);
        // Chronological order (ASC)
        expect($history->first()['movement_type'])->toBe(InventoryMovement::TYPE_RESERVATION);
        expect($history->last()['movement_type'])->toBe(InventoryMovement::TYPE_SALE);
    });

    it('history entries include human-readable descriptions', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $movementService = app(InventoryMovementService::class);
        $historyService = app(MovementHistoryService::class);

        $movementService->record($garment, InventoryMovement::TYPE_SALE);

        $history = $historyService->getGarmentHistory($garment);
        $firstEntry = $history->first();

        expect($firstEntry['description'])->toBe('Sold');
        expect($firstEntry['from_status'])->toBe('Available');
        expect($firstEntry['to_status'])->toBe('Sold');
        expect($firstEntry['performed_by'])->toBe($admin->display_name);
    });

    it('history includes branch transitions', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branchA = Branch::where('code', 'M')->first();
        $branchB = Branch::where('code', 'B1')->first();
        $garment = Garment::factory()->atBranch($branchA)->available()->create();
        $movementService = app(InventoryMovementService::class);
        $historyService = app(MovementHistoryService::class);

        $movementService->record($garment, InventoryMovement::TYPE_TRANSFER_RECEIVE, [
            'to_branch_id' => $branchB->id,
        ]);

        $history = $historyService->getGarmentHistory($garment);
        $transferEntry = $history->first();

        expect($transferEntry['from_branch'])->toContain($branchA->code);
        expect($transferEntry['to_branch'])->toContain($branchB->code);
    });
});

describe('stock visibility — UI access', function () {
    it('all authenticated users can view stock availability', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $salesperson = salesperson($branch);

        expect($this->actingAs($admin)->get(route('stock.availability'))->status())->toBe(200);
        expect($this->actingAs($supervisor)->get(route('stock.availability'))->status())->toBe(200);
        expect($this->actingAs($salesperson)->get(route('stock.availability'))->status())->toBe(200);
    });

    it('all authenticated users can view stock overview', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('stock.overview'));
        $response->assertStatus(200);
        $response->assertSee('Stock Overview');
    });

    it('all authenticated users can view reservation board', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('stock.reservations'));
        $response->assertStatus(200);
        $response->assertSee('Reservation Board');
    });

    it('guest redirected from stock availability', function () {
        $response = $this->get(route('stock.availability'));
        $response->assertRedirect(route('login'));
    });
});

describe('garment history — UI access', function () {
    it('authenticated user can view garment history', function () {
        $admin = adminUser();
        $garment = Garment::factory()->available()->create();

        $response = $this->actingAs($admin)->get(route('garments.history', $garment));

        $response->assertStatus(200);
        $response->assertSee('Movement History');
    });

    it('shows lifecycle timeline with movements', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $garment = Garment::factory()->available()->create();
        $movementService = app(InventoryMovementService::class);
        $movementService->record($garment, InventoryMovement::TYPE_DAMAGE_REPORT);

        $response = $this->get(route('garments.history', $garment));

        $response->assertStatus(200);
        $response->assertSee('Lifecycle Timeline');
        $response->assertSee('Damaged');
    });
});
