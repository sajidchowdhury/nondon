<?php

use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Services\SupplierService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('supplier list — viewAny', function () {
    it('allows admin to view supplier list', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('suppliers.index'));
        $response->assertStatus(200);
        $response->assertSee('Suppliers');
    });

    it('denies supervisor access to supplier list', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $response = $this->actingAs($supervisor)->get(route('suppliers.index'));
        $response->assertForbidden();
    });

    it('denies salesperson access to supplier list', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $response = $this->actingAs($salesperson)->get(route('suppliers.index'));
        $response->assertForbidden();
    });

    it('denies online team access to supplier list', function () {
        $user = \App\Modules\Identity\Models\User::factory()->onlineChannel()->create();
        $user->assignRole('online_team');
        $response = $this->actingAs($user)->get(route('suppliers.index'));
        $response->assertForbidden();
    });

    it('denies online supervisor access to supplier list', function () {
        $user = \App\Modules\Identity\Models\User::factory()->onlineChannel()->create();
        $user->assignRole('online_supervisor');
        $response = $this->actingAs($user)->get(route('suppliers.index'));
        $response->assertForbidden();
    });

    it('redirects guest to login', function () {
        $response = $this->get(route('suppliers.index'));
        $response->assertRedirect(route('login'));
    });
});

describe('supplier create', function () {
    it('allows admin to view create form', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('suppliers.create'));
        $response->assertStatus(200);
        $response->assertSee('Add Supplier');
    });

    it('denies supervisor access to create form', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $response = $this->actingAs($supervisor)->get(route('suppliers.create'));
        $response->assertForbidden();
    });

    it('allows admin to create a supplier via the service', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create([
            'code' => 'SUP-001',
            'name' => 'ABC Textiles Ltd',
            'contact_person' => 'John Doe',
            'phone' => '+8801712345678',
            'email' => 'john@abctextiles.com',
            'address' => '123 Factory Road, Dhaka',
        ]);

        expect($supplier->code)->toBe('SUP-001');
        expect($supplier->name)->toBe('ABC Textiles Ltd');
        expect($supplier->is_active)->toBeTrue();
        expect($supplier->outstanding_balance)->toBe('0.00');
        expect($supplier->created_by)->toBe($admin->id);
    });

    it('rejects duplicate supplier code', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $service->create(['code' => 'SUP-DUP', 'name' => 'First Supplier']);

        expect(fn () => $service->create(['code' => 'SUP-DUP', 'name' => 'Second Supplier']))
            ->toThrow(ValidationException::class);
    });

    it('can create a supplier with minimal data (code + name only)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-MIN', 'name' => 'Minimal Supplier']);

        expect($supplier->contact_person)->toBeNull();
        expect($supplier->phone)->toBeNull();
        expect($supplier->email)->toBeNull();
        expect($supplier->address)->toBeNull();
    });

    it('allows admin to create supplier via UI', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('suppliers.create'));
        $response->assertStatus(200);
        $response->assertSee('Create Supplier');
    });
});

describe('supplier update', function () {
    it('allows admin to update supplier details', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-UPD', 'name' => 'Original Name']);

        $updated = $service->update($supplier, [
            'name' => 'Updated Name',
            'phone' => '+8801999999999',
        ]);

        expect($updated->name)->toBe('Updated Name');
        expect($updated->phone)->toBe('+8801999999999');
    });

    it('rejects duplicate code on update', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier1 = $service->create(['code' => 'SUP-A', 'name' => 'Supplier A']);
        $service->create(['code' => 'SUP-B', 'name' => 'Supplier B']);

        expect(fn () => $service->update($supplier1, ['code' => 'SUP-B']))
            ->toThrow(ValidationException::class);
    });

    it('allows changing code to a new unique value', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-OLD', 'name' => 'Test']);

        $updated = $service->update($supplier, ['code' => 'SUP-NEW']);
        expect($updated->code)->toBe('SUP-NEW');
    });
});

describe('supplier activate / deactivate', function () {
    it('admin can activate a supplier', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-ACT', 'name' => 'Test']);
        $supplier = $service->deactivate($supplier);
        expect($supplier->is_active)->toBeFalse();

        $supplier = $service->activate($supplier);
        expect($supplier->is_active)->toBeTrue();
    });

    it('admin can deactivate a supplier with zero balance', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-DEACT', 'name' => 'Test']);

        $supplier = $service->deactivate($supplier);
        expect($supplier->is_active)->toBeFalse();
    });

    it('cannot deactivate supplier with non-zero outstanding balance', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-BAL', 'name' => 'Test']);

        // Simulate a balance (Phase 6 will mutate this via payments)
        $supplier->update(['outstanding_balance' => 1500.00]);

        expect(fn () => $service->deactivate($supplier->fresh()))
            ->toThrow(ValidationException::class);
    });

    it('deactivated supplier remains in database', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-PERSIST', 'name' => 'Test']);
        $service->deactivate($supplier);

        // Supplier is deactivated, not deleted — record persists
        expect(Supplier::where('code', 'SUP-PERSIST')->exists())->toBeTrue();
        expect(Supplier::where('code', 'SUP-PERSIST')->first()->is_active)->toBeFalse();
    });
});

describe('supplier search and list', function () {
    it('returns paginated list of suppliers', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        Supplier::factory()->count(3)->create();

        $paginated = $service->getPaginatedList();
        expect($paginated->count())->toBeGreaterThanOrEqual(3);
    });

    it('searches by code', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        Supplier::factory()->create(['code' => 'FINDME', 'name' => 'Some Name']);
        Supplier::factory()->create(['code' => 'OTHER', 'name' => 'Other Name']);

        $results = $service->search('FINDME');
        expect($results->count())->toBe(1);
        expect($results->first()->code)->toBe('FINDME');
    });

    it('searches by name', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        Supplier::factory()->create(['code' => 'A1', 'name' => 'Special Textiles']);
        Supplier::factory()->create(['code' => 'A2', 'name' => 'Other Company']);

        $results = $service->search('Special');
        expect($results->count())->toBe(1);
        expect($results->first()->name)->toBe('Special Textiles');
    });

    it('searches by phone', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        Supplier::factory()->create(['code' => 'P1', 'name' => 'Phone Test', 'phone' => '+8801712345678']);

        $results = $service->search('8801712345678');
        expect($results->count())->toBe(1);
        expect($results->first()->code)->toBe('P1');
    });

    it('filters by status', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        Supplier::factory()->create(['code' => 'ACT1', 'is_active' => true]);
        Supplier::factory()->create(['code' => 'INA1', 'is_active' => false]);

        $activeResults = $service->getPaginatedList(['status' => 'active']);
        $inactiveResults = $service->getPaginatedList(['status' => 'inactive']);

        expect($activeResults->pluck('code')->contains('ACT1'))->toBeTrue();
        expect($activeResults->pluck('code')->contains('INA1'))->toBeFalse();
        expect($inactiveResults->pluck('code')->contains('INA1'))->toBeTrue();
        expect($inactiveResults->pluck('code')->contains('ACT1'))->toBeFalse();
    });
});

describe('sensitive data restrictions', function () {
    it('contact details are hidden from serialization by default', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create([
            'code' => 'SUP-SENS',
            'name' => 'Sensitive Test',
            'contact_person' => 'Secret Contact',
            'phone' => '+8801711111111',
            'email' => 'secret@test.com',
            'address' => 'Secret Address',
        ]);

        $array = $supplier->toArray();

        // Sensitive fields must NOT appear in default serialization
        expect($array)->not->toHaveKey('contact_person');
        expect($array)->not->toHaveKey('phone');
        expect($array)->not->toHaveKey('email');
        expect($array)->not->toHaveKey('address');
        expect($array)->not->toHaveKey('outstanding_balance');
    });

    it('contact details are visible via withSensitiveInfo()', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create([
            'code' => 'SUP-VIS',
            'name' => 'Visible Test',
            'contact_person' => 'Visible Contact',
            'phone' => '+8801722222222',
        ]);

        $array = $supplier->withSensitiveInfo()->toArray();

        expect($array)->toHaveKey('contact_person');
        expect($array)->toHaveKey('phone');
        expect($array['contact_person'])->toBe('Visible Contact');
    });

    it('non-admin roles get 403 and never see supplier data', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        Supplier::factory()->create([
            'code' => 'SUP-403',
            'name' => 'Hidden Supplier',
            'phone' => '+8801733333333',
        ]);

        $response = $this->actingAs($supervisor)->get(route('suppliers.index'));
        $response->assertForbidden();
        $response->assertDontSee('Hidden Supplier');
        $response->assertDontSee('+8801733333333');
    });
});

describe('auditing', function () {
    it('supplier creation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-AUDIT1', 'name' => 'Audit Test']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier.create',
            'reference_type' => 'supplier',
            'reference_id' => $supplier->id,
        ]);
    });

    it('supplier update is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-AUDIT2', 'name' => 'Original']);

        $service->update($supplier, ['name' => 'Updated']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier.update',
            'reference_type' => 'supplier',
            'reference_id' => $supplier->id,
        ]);
    });

    it('supplier deactivate is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-AUDIT3', 'name' => 'Deactivate Test']);

        $service->deactivate($supplier);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier.deactivate',
            'reference_type' => 'supplier',
            'reference_id' => $supplier->id,
        ]);
    });

    it('supplier activate is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(SupplierService::class);
        $supplier = $service->create(['code' => 'SUP-AUDIT4', 'name' => 'Activate Test']);
        $service->deactivate($supplier);
        $service->activate($supplier->fresh());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier.activate',
            'reference_type' => 'supplier',
            'reference_id' => $supplier->id,
        ]);
    });
});

describe('supplier view — detail', function () {
    it('allows admin to view supplier detail', function () {
        $admin = adminUser();
        $supplier = Supplier::factory()->create(['code' => 'SUP-VIEW', 'name' => 'View Test']);

        $response = $this->actingAs($admin)->get(route('suppliers.show', $supplier));
        $response->assertStatus(200);
        $response->assertSee('View Test');
    });

    it('denies supervisor access to supplier detail', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($supervisor)->get(route('suppliers.show', $supplier));
        $response->assertForbidden();
    });
});

describe('supplier edit — UI access', function () {
    it('allows admin to view edit form', function () {
        $admin = adminUser();
        $supplier = Supplier::factory()->create(['code' => 'SUP-EDIT']);

        $response = $this->actingAs($admin)->get(route('suppliers.edit', $supplier));
        $response->assertStatus(200);
        $response->assertSee('Edit Supplier');
    });

    it('denies supervisor access to edit form', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($supervisor)->get(route('suppliers.edit', $supplier));
        $response->assertForbidden();
    });
});
