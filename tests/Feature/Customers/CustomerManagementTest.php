<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Customers\Http\Livewire\Customers\CustomerList;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Customer list — viewAny
|--------------------------------------------------------------------------
*/
describe('customer list — viewAny', function () {
    it('allows admin to view customer list', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('customers.index'));
        $response->assertStatus(200);
        $response->assertSee('Customers');
    });

    it('allows supervisor to view customer list', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $response = $this->actingAs($supervisor)->get(route('customers.index'));
        $response->assertStatus(200);
        $response->assertSee('Customers');
    });

    it('allows salesperson to view customer list', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $response = $this->actingAs($salesperson)->get(route('customers.index'));
        $response->assertStatus(200);
        $response->assertSee('Customers');
    });

    it('allows online team to view customer list', function () {
        $user = User::factory()->onlineChannel()->create();
        $user->assignRole('online_team');
        $response = $this->actingAs($user)->get(route('customers.index'));
        $response->assertStatus(200);
    });

    it('allows online supervisor to view customer list', function () {
        $user = User::factory()->onlineChannel()->create();
        $user->assignRole('online_supervisor');
        $response = $this->actingAs($user)->get(route('customers.index'));
        $response->assertStatus(200);
    });

    it('redirects guest to login', function () {
        $response = $this->get(route('customers.index'));
        $response->assertRedirect(route('login'));
    });
});

/*
|--------------------------------------------------------------------------
| Customer create — service
|--------------------------------------------------------------------------
*/
describe('customer create — service', function () {
    it('creates a customer with phone and optional name via the service', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(CustomerService::class);
        $customer = $service->create([
            'phone' => '01712345678',
            'name' => 'Test Customer',
        ], $admin);

        expect($customer->phone)->toBe('01712345678')
            ->and($customer->name)->toBe('Test Customer')
            ->and($customer->is_active)->toBeTrue()
            ->and($customer->is_online_customer)->toBeFalse()
            ->and($customer->created_by)->toBe($admin->id);

        $this->assertDatabaseHas('customers', ['phone' => '01712345678']);
    });

    it('creates a walk-in customer with phone only (name optional)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(CustomerService::class);
        $customer = $service->create(['phone' => '01799999999'], $admin);

        expect($customer->name)->toBeNull()
            ->and($customer->phone)->toBe('01799999999');
    });

    it('rejects empty phone', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(CustomerService::class);
        $service->create(['phone' => '   '], $admin);
    })->throws(ValidationException::class, 'Customer phone is required.');

    it('detects duplicate phone and returns the existing id', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(CustomerService::class);
        $existing = $service->create(['phone' => '01712345678', 'name' => 'First'], $admin);

        try {
            $service->create(['phone' => '01712345678', 'name' => 'Second'], $admin);
            $this->fail('Expected ValidationException for duplicate phone.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('phone')
                ->and($e->errors())->toHaveKey('existing_customer_id')
                ->and((int) $e->errors()['existing_customer_id'][0])->toBe($existing->id);
        }
    });

    it('normalizes phone variants to the same local format (duplicate detection across formats)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(CustomerService::class);
        $service->create(['phone' => '01712 345678'], $admin);

        // +880 and spaces and dashes should normalize to the same record.
        try {
            $service->create(['phone' => '+8801712-345678'], $admin);
            $this->fail('Expected duplicate detection after normalization.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('phone');
        }
    });

    it('marks the customer as online when the creator is online channel staff', function () {
        $onlineTeam = User::factory()->onlineChannel()->create();
        $onlineTeam->assignRole('online_team');
        $this->actingAs($onlineTeam);

        $service = app(CustomerService::class);
        $customer = $service->create(['phone' => '01911112222'], $onlineTeam);

        expect($customer->is_online_customer)->toBeTrue();
    });
});

describe('customer create — UI', function () {
    it('allows admin to view the create form', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('customers.create'));
        $response->assertStatus(200);
        $response->assertSee('Add Customer');
    });

    it('allows salesperson to view the create form', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $response = $this->actingAs($salesperson)->get(route('customers.create'));
        $response->assertStatus(200);
        $response->assertSee('Add Customer');
    });

    it('redirects guest to login', function () {
        $response = $this->get(route('customers.create'));
        $response->assertRedirect(route('login'));
    });
});

/*
|--------------------------------------------------------------------------
| findOrCreateByPhone — the POS helper
|--------------------------------------------------------------------------
*/
describe('findOrCreateByPhone', function () {
    it('returns the existing customer instead of duplicating', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(CustomerService::class);
        $first = $service->findOrCreateByPhone('01712345678', $admin, 'Alice');

        $second = $service->findOrCreateByPhone('01712345678', $admin, 'Alice Duplicate');

        expect($second->id)->toBe($first->id)
            ->and(Customer::where('phone', '01712345678')->count())->toBe(1);
    });

    it('creates a new customer when the phone is unknown', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(CustomerService::class);
        $customer = $service->findOrCreateByPhone('01755556666', $admin);

        expect($customer)->toBeInstanceOf(Customer::class)
            ->and($customer->exists)->toBeTrue();
    });

    it('resolves a merged duplicate back to the primary, never the duplicate', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(CustomerService::class);
        $primary = $service->create(['phone' => '01777778888', 'name' => 'Primary'], $admin);
        $duplicate = Customer::factory()->create([
            'phone' => '01777770000',
            'is_active' => false,
            'merged_into_id' => $primary->id,
        ]);

        // Lookup of the duplicate phone returns the primary, not the duplicate.
        $resolved = $service->findOrCreateByPhone('01777770000', $admin);
        expect($resolved->id)->toBe($primary->id)
            ->and($resolved->is_active)->toBeTrue();
    });
});

/*
|--------------------------------------------------------------------------
| Customer edit — admin only
|--------------------------------------------------------------------------
*/
describe('customer edit', function () {
    it('allows admin to view the edit form', function () {
        $admin = adminUser();
        $customer = Customer::factory()->create(['phone' => '01711114444']);
        $response = $this->actingAs($admin)->get(route('customers.edit', $customer));
        $response->assertStatus(200);
        $response->assertSee('Edit Customer');
    });

    it('denies supervisor access to the edit form (403)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $customer = Customer::factory()->create(['phone' => '01711115555']);
        $response = $this->actingAs($supervisor)->get(route('customers.edit', $customer));
        $response->assertForbidden();
    });

    it('denies salesperson access to the edit form (403)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $customer = Customer::factory()->create(['phone' => '01711116666']);
        $response = $this->actingAs($salesperson)->get(route('customers.edit', $customer));
        $response->assertForbidden();
    });

    it('allows admin to update a customer via the service', function () {
        $admin = adminUser();
        $this->actingAs($admin);
        $customer = Customer::factory()->create(['phone' => '01722223333', 'name' => 'Old']);

        $service = app(CustomerService::class);
        $updated = $service->update($customer, ['name' => 'New Name'], $admin);

        expect($updated->name)->toBe('New Name')
            ->and($updated->phone)->toBe('01722223333');
    });

    it('rejects updating phone to an existing phone (duplicate)', function () {
        $admin = adminUser();
        $this->actingAs($admin);
        $service = app(CustomerService::class);
        $first = $service->create(['phone' => '01733330001'], $admin);
        $second = $service->create(['phone' => '01733330002'], $admin);

        try {
            $service->update($second, ['phone' => '01733330001'], $admin);
            $this->fail('Expected duplicate-phone ValidationException.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('phone');
        }
    });

    it('throws AuthorizationException when a non-admin calls update via the service', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $this->actingAs($salesperson);
        $customer = Customer::factory()->create(['phone' => '01744440001']);

        $service = app(CustomerService::class);
        try {
            $service->update($customer, ['name' => 'Hacked'], $salesperson);
            $this->fail('Expected AuthorizationException.');
        } catch (AuthorizationException $e) {
            expect(true)->toBeTrue();
        }
    });

    it('allows admin to deactivate a customer via the service', function () {
        $admin = adminUser();
        $this->actingAs($admin);
        $customer = Customer::factory()->create(['phone' => '01744440002']);

        $service = app(CustomerService::class);
        $deactivated = $service->deactivate($customer, $admin);

        expect($deactivated->is_active)->toBeFalse();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_active' => false]);
    });
});

/*
|--------------------------------------------------------------------------
| Customer PII scope — view
|--------------------------------------------------------------------------
*/
describe('customer PII scope — view', function () {
    it('allows admin to view any customer', function () {
        $admin = adminUser();
        $walkin = Customer::factory()->walkIn()->create();
        $online = Customer::factory()->online()->create();

        $this->actingAs($admin)->get(route('customers.show', $walkin))->assertStatus(200);
        $this->actingAs($admin)->get(route('customers.show', $online))->assertStatus(200);
    });

    it('allows branch supervisor to view a walk-in customer', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $walkin = Customer::factory()->walkIn()->create();
        $response = $this->actingAs($supervisor)->get(route('customers.show', $walkin));
        $response->assertStatus(200);
    });

    it('denies branch supervisor access to an online customer (403)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $online = Customer::factory()->online()->create();
        $response = $this->actingAs($supervisor)->get(route('customers.show', $online));
        $response->assertForbidden();
    });

    it('allows online team to view an online customer', function () {
        $onlineTeam = User::factory()->onlineChannel()->create();
        $onlineTeam->assignRole('online_team');
        $online = Customer::factory()->online()->create();
        $response = $this->actingAs($onlineTeam)->get(route('customers.show', $online));
        $response->assertStatus(200);
    });

    it('denies online team access to a walk-in customer (403)', function () {
        $onlineTeam = User::factory()->onlineChannel()->create();
        $onlineTeam->assignRole('online_team');
        $walkin = Customer::factory()->walkIn()->create();
        $response = $this->actingAs($onlineTeam)->get(route('customers.show', $walkin));
        $response->assertForbidden();
    });

    it('excludes merged customers from the list by default; admin can include them', function () {
        $admin = adminUser();
        $this->actingAs($admin);
        $primary = Customer::factory()->create(['phone' => '01766660001']);
        Customer::factory()->mergedInto($primary)->create(['phone' => '01766660002']);

        // Default (include_merged = false): merged excluded.
        Livewire::test(CustomerList::class)
            ->assertSee('01766660001')
            ->assertDontSee('01766660002');

        // Toggle include_merged on: shown.
        Livewire::test(CustomerList::class)
            ->set('includeMerged', true)
            ->assertSee('01766660002');
    });

    it('denies non-admin the include_merged filter (still excluded)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $this->actingAs($supervisor);
        $primary = Customer::factory()->walkIn()->create(['phone' => '01766660003']);
        Customer::factory()->walkIn()->mergedInto($primary)->create(['phone' => '01766660004']);

        // Even with includeMerged set, non-admin never sees merged records.
        Livewire::test(CustomerList::class)
            ->set('includeMerged', true)
            ->assertDontSee('01766660004');
    });
});

/*
|--------------------------------------------------------------------------
| Auditing
|--------------------------------------------------------------------------
*/
describe('auditing', function () {
    it('audits customer creation', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(CustomerService::class);
        $service->create(['phone' => '01788889999', 'name' => 'Audited'], $admin);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer.create',
            'category' => 'customers',
            'reference_type' => 'customer',
        ]);
    });

    it('audits customer update', function () {
        $admin = adminUser();
        $this->actingAs($admin);
        $customer = Customer::factory()->create(['phone' => '01788880001']);

        // Clear audit logs created by the factory/model boot, then update.
        AuditLog::query()->delete();
        $service = app(CustomerService::class);
        $service->update($customer, ['name' => 'Updated Name'], $admin);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer.update',
            'category' => 'customers',
        ]);
    });

    it('audits customer deactivation', function () {
        $admin = adminUser();
        $this->actingAs($admin);
        $customer = Customer::factory()->create(['phone' => '01788880002']);

        AuditLog::query()->delete();
        $service = app(CustomerService::class);
        $service->deactivate($customer, $admin);

        // Deactivation is a state transition. The model's is_active change
        // fires the generic 'customer.update' audit via the Auditable trait;
        // at minimum an audited entry must exist for the deactivate action.
        $count = AuditLog::where('reference_type', 'customer')
            ->whereIn('action', ['customer.update', 'customer.deactivate'])
            ->count();
        expect($count)->toBeGreaterThan(0);
    });
});

/*
|--------------------------------------------------------------------------
| Customers are never deleted
|--------------------------------------------------------------------------
*/
describe('immutability', function () {
    it('never deletes a customer (no delete path; merge marks inactive)', function () {
        $admin = adminUser();
        $this->actingAs($admin);
        $customer = Customer::factory()->create(['phone' => '01799990001']);
        $id = $customer->id;

        // There is no service delete method. Policy::delete returns false.
        expect($admin->can('delete', $customer))->toBeFalse();

        $this->assertDatabaseHas('customers', ['id' => $id]);
    });
});

/*
|--------------------------------------------------------------------------
| Navigation visibility
|--------------------------------------------------------------------------
*/
describe('navigation', function () {
    it('shows the Customers nav link to a selling role', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $response = $this->actingAs($salesperson)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Customers');
    });

    it('shows the Customers nav link to admin', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Customers');
    });
});
