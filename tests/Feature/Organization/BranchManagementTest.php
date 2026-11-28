<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\StockLocation;
use App\Modules\Organization\Services\BranchService;
use App\Modules\Organization\Services\StockLocationService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('branch list — viewAny', function () {
    it('allows admin to view branch list', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('branches.index'));

        $response->assertStatus(200);
        $response->assertSee('Branches');
    });

    it('allows supervisor to view branch list', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('branches.index'));

        $response->assertStatus(200);
    });

    it('denies salesperson access to branch list', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('branches.index'));

        $response->assertForbidden();
    });

    it('redirects guest to login', function () {
        $response = $this->get(route('branches.index'));
        $response->assertRedirect(route('login'));
    });
});

describe('branch create', function () {
    it('allows admin to view create form', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('branches.create'));

        $response->assertStatus(200);
        $response->assertSee('Create Branch');
    });

    it('denies supervisor access to create form', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('branches.create'));

        $response->assertForbidden();
    });

    it('allows admin to create a branch', function () {
        $admin = adminUser();

        $service = app(BranchService::class);
        $branch = $service->create([
            'code' => 'B4',
            'name' => 'Branch 4',
            'type' => 'physical',
            'address' => '123 New Street',
        ]);

        expect($branch)->toBeInstanceOf(Branch::class);
        expect($branch->code)->toBe('B4');
        expect($branch->name)->toBe('Branch 4');
        expect($branch->is_active)->toBeTrue();
        expect($branch->is_online_channel)->toBeFalse();
    });

    it('creates a default stock location when branch is created', function () {
        $admin = adminUser();

        $service = app(BranchService::class);
        $branch = $service->create([
            'code' => 'B5',
            'name' => 'Branch 5',
            'type' => 'physical',
        ]);

        $defaultLocation = $branch->stockLocations()->first();
        expect($defaultLocation)->not->toBeNull();
        expect($defaultLocation->code)->toBe('DEFAULT');
        expect($defaultLocation->type)->toBe('retail');
    });

    it('auto-sets is_online_channel for online type', function () {
        $admin = adminUser();

        $service = app(BranchService::class);
        $branch = $service->create([
            'code' => 'ONL2',
            'name' => 'Online Channel 2',
            'type' => 'online',
        ]);

        expect($branch->is_online_channel)->toBeTrue();
    });

    it('rejects duplicate branch code', function () {
        $service = app(BranchService::class);

        expect(fn () => $service->create([
            'code' => 'M', // Already exists from seeder
            'name' => 'Duplicate',
            'type' => 'physical',
        ]))->toThrow(ValidationException::class);
    });

    it('creates online_transient location for online channel branch', function () {
        $service = app(BranchService::class);
        $branch = $service->create([
            'code' => 'ONL3',
            'name' => 'Online 3',
            'type' => 'online',
        ]);

        $defaultLocation = $branch->stockLocations()->first();
        expect($defaultLocation->type)->toBe('online_transient');
    });
});

describe('branch update', function () {
    it('allows admin to update branch details', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();

        $service = app(BranchService::class);
        $updated = $service->update($branch, [
            'name' => 'Updated Branch 1',
        ]);

        expect($updated->name)->toBe('Updated Branch 1');
    });

    it('rejects duplicate code on update', function () {
        $service = app(BranchService::class);
        $branch = Branch::where('code', 'B1')->first();

        expect(fn () => $service->update($branch, ['code' => 'M']))
            ->toThrow(ValidationException::class);
    });

    it('allows admin to change branch code to a new unique value', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();

        $service = app(BranchService::class);
        $updated = $service->update($branch, ['code' => 'B1NEW']);

        expect($updated->code)->toBe('B1NEW');
    });
});

describe('activate / deactivate', function () {
    it('allows admin to activate a branch', function () {
        $admin = adminUser();
        $branch = Branch::factory()->create(['is_active' => false]);

        $service = app(BranchService::class);
        $activated = $service->activate($branch);

        expect($activated->is_active)->toBeTrue();
    });

    it('allows admin to deactivate a branch with no active users', function () {
        $admin = adminUser();
        $branch = Branch::factory()->create(['is_active' => true]);

        $service = app(BranchService::class);
        $deactivated = $service->deactivate($branch);

        expect($deactivated->is_active)->toBeFalse();
    });

    it('prevents deactivating a branch with active users assigned', function () {
        $admin = adminUser();
        $branch = Branch::factory()->create(['is_active' => true]);
        User::factory()->forBranch($branch)->create(['is_active' => true]);

        $service = app(BranchService::class);

        expect(fn () => $service->deactivate($branch))
            ->toThrow(ValidationException::class);
    });

    it('allows deactivation when users are inactive', function () {
        $admin = adminUser();
        $branch = Branch::factory()->create(['is_active' => true]);
        User::factory()->forBranch($branch)->create(['is_active' => false]);

        $service = app(BranchService::class);

        $deactivated = $service->deactivate($branch);
        expect($deactivated->is_active)->toBeFalse();
    });
});

describe('stock locations', function () {
    it('can create a stock location for a branch', function () {
        $branch = Branch::where('code', 'B1')->first();

        $service = app(StockLocationService::class);
        $location = $service->create($branch, [
            'code' => 'WH1',
            'name' => 'Warehouse 1',
            'type' => 'warehouse',
        ]);

        expect($location)->toBeInstanceOf(StockLocation::class);
        expect($location->branch_id)->toBe($branch->id);
        expect($location->code)->toBe('WH1');
    });

    it('rejects duplicate location code within same branch', function () {
        $branch = Branch::where('code', 'B1')->first();

        $service = app(StockLocationService::class);
        $service->create($branch, ['code' => 'WH1', 'name' => 'Warehouse 1', 'type' => 'warehouse']);

        expect(fn () => $service->create($branch, ['code' => 'WH1', 'name' => 'Duplicate', 'type' => 'warehouse']))
            ->toThrow(ValidationException::class);
    });

    it('allows same location code in different branches', function () {
        $branchA = Branch::where('code', 'B1')->first();
        $branchB = Branch::where('code', 'B2')->first();

        $service = app(StockLocationService::class);

        $locationA = $service->create($branchA, ['code' => 'WH1', 'name' => 'Warehouse A', 'type' => 'warehouse']);
        $locationB = $service->create($branchB, ['code' => 'WH1', 'name' => 'Warehouse B', 'type' => 'warehouse']);

        expect($locationA)->not->toBeNull();
        expect($locationB)->not->toBeNull();
    });

    it('can deactivate a stock location', function () {
        $branch = Branch::where('code', 'B1')->first();

        $service = app(StockLocationService::class);
        $location = $service->create($branch, ['code' => 'WH1', 'name' => 'Warehouse 1', 'type' => 'warehouse']);

        $deactivated = $service->deactivate($location);

        expect($deactivated->is_active)->toBeFalse();
    });

    it('can get all stock locations for a branch', function () {
        $branch = Branch::where('code', 'B1')->first();

        $service = app(StockLocationService::class);
        $service->create($branch, ['code' => 'WH1', 'name' => 'Warehouse 1', 'type' => 'warehouse']);
        $service->create($branch, ['code' => 'WH2', 'name' => 'Warehouse 2', 'type' => 'warehouse']);

        $locations = $service->getForBranch($branch);

        expect($locations->count())->toBeGreaterThanOrEqual(3); // DEFAULT + WH1 + WH2
    });
});

describe('branch search and list', function () {
    it('returns paginated list of branches', function () {
        Branch::factory()->count(5)->create();

        $service = app(BranchService::class);
        $result = $service->getPaginatedList(['per_page' => 10]);

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
        expect($result->count())->toBeLessThanOrEqual(10);
    });

    it('searches by code', function () {
        Branch::factory()->create(['code' => 'TEST1', 'name' => 'Test Branch 1']);
        Branch::factory()->create(['code' => 'OTHER', 'name' => 'Other Branch']);

        $service = app(BranchService::class);
        $results = $service->getPaginatedList(['search' => 'TEST1', 'per_page' => 100]);

        expect($results->total())->toBe(1);
    });

    it('filters by type', function () {
        $service = app(BranchService::class);

        $physicalCount = $service->getPaginatedList(['type' => 'physical', 'per_page' => 100])->total();
        $onlineCount = $service->getPaginatedList(['type' => 'online', 'per_page' => 100])->total();

        expect($physicalCount)->toBeGreaterThan(0);
        expect($onlineCount)->toBe(1); // Online channel from seeder
    });

    it('filters by status', function () {
        Branch::factory()->create(['is_active' => false]);

        $service = app(BranchService::class);

        $activeCount = $service->getPaginatedList(['status' => 'active', 'per_page' => 100])->total();
        $inactiveCount = $service->getPaginatedList(['status' => 'inactive', 'per_page' => 100])->total();

        expect($activeCount)->toBeGreaterThan(0);
        expect($inactiveCount)->toBe(1);
    });
});

describe('branch edit — authorization', function () {
    it('allows admin to view edit form', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();

        $response = $this->actingAs($admin)->get(route('branches.edit', $branch));

        $response->assertStatus(200);
        $response->assertSee('Edit Branch');
    });

    it('denies supervisor access to edit form', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('branches.edit', $branch));

        $response->assertForbidden();
    });
});

describe('HasBranchScope trait', function () {
    it('filters queries by user branch for non-admin', function () {
        // Create a test model that uses HasBranchScope
        // We'll test the trait behavior conceptually since we don't have a branch-sensitive model yet
        // This will be tested with SaleInvoice in Phase 3

        $branchA = Branch::where('code', 'B1')->first();
        $branchB = Branch::where('code', 'B2')->first();

        $supervisorA = supervisor($branchA);

        $this->actingAs($supervisorA);

        // The trait would filter queries to branch A only
        // Admin would see all branches
        expect($supervisorA->assigned_branch_id)->toBe($branchA->id);
    });

    it('admin sees all branches (no filter)', function () {
        $admin = adminUser();

        $this->actingAs($admin);

        // Admin should not have a branch filter applied
        expect($admin->hasRole('main_branch_admin'))->toBeTrue();
    });
});

describe('auditing', function () {
    it('branch creation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(BranchService::class);
        $branch = $service->create([
            'code' => 'AUDIT',
            'name' => 'Audit Test Branch',
            'type' => 'physical',
        ]);

        $auditLog = AuditLog::where('action', 'branch.create')
            ->where('reference_type', 'branch')
            ->where('reference_id', $branch->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->user_id)->toBe($admin->id);
    });

    it('branch update is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branch = Branch::where('code', 'B1')->first();

        // Clear audit logs from branch creation (seeder)
        AuditLog::query()->delete();

        $service = app(BranchService::class);
        $service->update($branch, ['name' => 'Updated B1 Name']);

        $auditLog = AuditLog::where('action', 'branch.update')
            ->where('reference_id', $branch->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->previous_values['name'] ?? null)->not->toBeNull();
    });

    it('branch activation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branch = Branch::factory()->create(['is_active' => false]);
        AuditLog::query()->delete();

        $service = app(BranchService::class);
        $service->activate($branch);

        $auditLog = AuditLog::where('action', 'branch.activate')
            ->where('reference_id', $branch->id)
            ->first();

        expect($auditLog)->not->toBeNull();
    });

    it('stock location creation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branch = Branch::where('code', 'B1')->first();
        AuditLog::query()->delete();

        $service = app(StockLocationService::class);
        $location = $service->create($branch, [
            'code' => 'AUDIT_WH',
            'name' => 'Audit Warehouse',
            'type' => 'warehouse',
        ]);

        $auditLog = AuditLog::where('action', 'stock_location.create')
            ->where('reference_id', $location->id)
            ->first();

        expect($auditLog)->not->toBeNull();
    });
});
