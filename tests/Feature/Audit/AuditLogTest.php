<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Audit\Events\AuditableEvent;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('Auditable trait — automatic logging on model events', function () {
    it('creates audit log entry when a user is created', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branch = Branch::where('code', 'B1')->first();

        $user = User::factory()->forBranch($branch)->create([
            'username' => 'audittest',
            'full_name' => 'Audit Test User',
        ]);

        // Check that an audit log entry was created
        $auditLog = AuditLog::where('action', 'user.create')
            ->where('reference_type', 'user')
            ->where('reference_id', $user->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->user_id)->toBe($admin->id);
        expect($auditLog->category)->toBe('identity');
    });

    it('creates audit log entry when a user is updated', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create(['full_name' => 'Original Name']);

        // Clear audit logs from create
        AuditLog::query()->delete();

        // Update the user
        $user->update(['full_name' => 'Updated Name']);

        $auditLog = AuditLog::where('action', 'user.update')
            ->where('reference_type', 'user')
            ->where('reference_id', $user->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->previous_values['full_name'])->toBe('Original Name');
        expect($auditLog->new_values['full_name'])->toBe('Updated Name');
    });

    it('hides sensitive fields from audit values', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();

        $auditLog = AuditLog::where('action', 'user.create')
            ->where('reference_id', $user->id)
            ->first();

        // password_hash and remember_token should NOT be in the audit values
        expect($auditLog->new_values)->not->toHaveKey('password_hash');
        expect($auditLog->new_values)->not->toHaveKey('remember_token');
    });
});

describe('AuditService — manual logging', function () {
    it('can write an audit log entry', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);

        $log = $service->log('test.action', [
            'category' => 'test',
            'reference_type' => 'test_model',
            'reference_id' => 123,
            'new_values' => ['field' => 'value'],
            'reason' => 'Testing audit log',
        ]);

        expect($log)->toBeInstanceOf(AuditLog::class);
        expect($log->action)->toBe('test.action');
        expect($log->category)->toBe('test');
        expect($log->reference_type)->toBe('test_model');
        expect($log->reference_id)->toBe(123);
        expect($log->new_values)->toBe(['field' => 'value']);
        expect($log->reason)->toBe('Testing audit log');
        expect($log->user_id)->toBe($admin->id);
        expect($log->ip_address)->not->toBeNull();
        expect($log->performed_at)->not->toBeNull();
    });

    it('auto-infers category from action prefix', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);

        $service->log('user.create');
        $service->log('sale.complete');
        $service->log('garment.create');

        $userLog = AuditLog::where('action', 'user.create')->first();
        $saleLog = AuditLog::where('action', 'sale.complete')->first();
        $garmentLog = AuditLog::where('action', 'garment.create')->first();

        expect($userLog->category)->toBe('identity');
        expect($saleLog->category)->toBe('sales');
        expect($garmentLog->category)->toBe('inventory');
    });

    it('can log with explicit user override (for system actions)', function () {
        $admin = adminUser();

        $service = app(AuditService::class);

        $log = $service->log('system.backup', [
            'user_id' => $admin->id,
            'category' => 'system',
        ]);

        expect($log->user_id)->toBe($admin->id);
    });
});

describe('AuditLog — append-only enforcement', function () {
    it('cannot be updated after creation', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);
        $log = $service->log('test.action', ['category' => 'test']);

        expect(fn() => $log->update(['action' => 'modified.action']))
            ->toThrow(\RuntimeException::class);
    });

    it('cannot be deleted', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);
        $log = $service->log('test.action', ['category' => 'test']);

        expect(fn() => $log->delete())
            ->toThrow(\RuntimeException::class);
    });

    it('cannot be force deleted', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);
        $log = $service->log('test.action', ['category' => 'test']);

        expect(fn() => $log->forceDelete())
            ->toThrow(\RuntimeException::class);
    });

    it('save() throws when updating existing record', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);
        $log = $service->log('test.action', ['category' => 'test']);

        $log->action = 'modified';

        expect(fn() => $log->save())
            ->toThrow(\RuntimeException::class);
    });
});

describe('AuditService — query and filter', function () {
    beforeEach(function () {
        // Create test audit entries
        $this->admin = adminUser();
        $this->branch = Branch::where('code', 'B1')->first();
        $this->service = app(AuditService::class);

        // Clear any audit entries produced by the global beforeEach seeders
        // (RolePermissionSeeder, BranchSeeder, FiscalYearSeeder) and by the
        // adminUser() factory call above (the Auditable trait fires a
        // 'user.create' event whenever a User is persisted). Starting from
        // a clean slate keeps the count-based assertions below deterministic.
        AuditLog::query()->delete();
    });

    it('filters by user', function () {
        $this->actingAs($this->admin);

        $user1 = User::factory()->forBranch($this->branch)->create();
        $user2 = User::factory()->forBranch($this->branch)->create();

        $this->actingAs($user1);
        $this->service->log('user1.action', ['category' => 'test']);

        $this->actingAs($user2);
        $this->service->log('user2.action', ['category' => 'test']);

        $results = $this->service->query(['user_id' => $user1->id])->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->action)->toBe('user1.action');
    });

    it('filters by category', function () {
        $this->actingAs($this->admin);

        $this->service->log('test.one', ['category' => 'inventory']);
        $this->service->log('test.two', ['category' => 'sales']);
        $this->service->log('test.three', ['category' => 'inventory']);

        $results = $this->service->query(['category' => 'inventory'])->get();

        expect($results)->toHaveCount(2);
    });

    it('filters by action', function () {
        $this->actingAs($this->admin);

        $this->service->log('user.create', ['category' => 'identity']);
        $this->service->log('user.update', ['category' => 'identity']);
        $this->service->log('user.create', ['category' => 'identity']);

        $results = $this->service->query(['action' => 'user.create'])->get();

        expect($results)->toHaveCount(2);
    });

    it('filters by reference', function () {
        $this->actingAs($this->admin);

        $this->service->log('test.action', [
            'category' => 'test',
            'reference_type' => 'garment',
            'reference_id' => 100,
        ]);
        $this->service->log('test.action', [
            'category' => 'test',
            'reference_type' => 'garment',
            'reference_id' => 200,
        ]);

        $results = $this->service->query([
            'reference_type' => 'garment',
            'reference_id' => 100,
        ])->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->reference_id)->toBe(100);
    });

    it('filters by date range', function () {
        $this->actingAs($this->admin);

        // Create entries with specific dates
        AuditLog::create([
            'action' => 'past.action',
            'category' => 'test',
            'performed_at' => now()->subDays(5),
            'user_id' => $this->admin->id,
        ]);
        AuditLog::create([
            'action' => 'today.action',
            'category' => 'test',
            'performed_at' => now(),
            'user_id' => $this->admin->id,
        ]);

        $todayResults = $this->service->query(['date_from' => now()->toDateString()])->get();
        expect($todayResults)->toHaveCount(1);
        expect($todayResults->first()->action)->toBe('today.action');
    });

    it('returns results ordered by most recent first', function () {
        $this->actingAs($this->admin);

        $this->service->log('first.action', ['category' => 'test']);
        sleep(0.01); // Ensure timestamp difference
        $this->service->log('second.action', ['category' => 'test']);

        $results = $this->service->query()->limit(2)->get();

        expect($results->first()->action)->toBe('second.action');
        expect($results->last()->action)->toBe('first.action');
    });
});

describe('AuditService — forUser, forBranch, forReference', function () {
    it('forUser returns entries for a specific user', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);
        $service->log('user.test1', ['category' => 'test']);
        $service->log('user.test2', ['category' => 'test']);

        $results = $service->forUser($admin);

        expect($results->count())->toBeGreaterThanOrEqual(2);
        expect($results->every(fn ($log) => $log->user_id === $admin->id))->toBeTrue();
    });

    it('forReference returns entries for a specific entity', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);
        $service->log('test.create', [
            'category' => 'test',
            'reference_type' => 'garment',
            'reference_id' => 999,
        ]);
        $service->log('test.update', [
            'category' => 'test',
            'reference_type' => 'garment',
            'reference_id' => 999,
        ]);
        $service->log('other.create', [
            'category' => 'test',
            'reference_type' => 'garment',
            'reference_id' => 888,
        ]);

        $results = $service->forReference('garment', 999);

        expect($results)->toHaveCount(2);
        expect($results->every(fn ($log) => $log->reference_id === 999))->toBeTrue();
    });
});

describe('AuditableEvent — event dispatching', function () {
    it('event is dispatched and listener writes audit log', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        // Dispatch an AuditableEvent
        event(new AuditableEvent('custom.action', [
            'category' => 'test',
            'reference_type' => 'test_model',
            'reference_id' => 42,
            'new_values' => ['key' => 'value'],
        ]));

        $log = AuditLog::where('action', 'custom.action')->first();

        expect($log)->not->toBeNull();
        expect($log->category)->toBe('test');
        expect($log->reference_type)->toBe('test_model');
        expect($log->reference_id)->toBe(42);
        expect($log->new_values)->toBe(['key' => 'value']);
    });
});

describe('AuditService — logDataAccess (sensitive data)', function () {
    it('logs sensitive data access', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);
        $service->logDataAccess('purchase_cost', 'garment', 123);

        $log = AuditLog::where('action', 'sensitive_data.view')
            ->where('reference_type', 'garment')
            ->where('reference_id', 123)
            ->first();

        expect($log)->not->toBeNull();
        expect($log->category)->toBe('security');
        expect($log->new_values['data_type'])->toBe('purchase_cost');
        expect($log->user_id)->toBe($admin->id);
    });
});

describe('AuditLog — JSONB casts', function () {
    it('previous_values and new_values are cast to arrays', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);
        $log = $service->log('test.action', [
            'category' => 'test',
            'previous_values' => ['old' => 'value'],
            'new_values' => ['new' => 'value'],
        ]);

        $log = $log->fresh();

        expect($log->previous_values)->toBe(['old' => 'value']);
        expect($log->new_values)->toBe(['new' => 'value']);
        expect(is_array($log->previous_values))->toBeTrue();
        expect(is_array($log->new_values))->toBeTrue();
    });
});

describe('Auditable trait — auditCustomAction method', function () {
    it('can manually dispatch custom audit actions', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();

        // Clear audit logs from create
        AuditLog::query()->delete();

        // Dispatch a custom action
        $user->auditCustomAction('activate', null, ['is_active' => true], 'Account activated');

        $log = AuditLog::where('action', 'user.activate')->first();

        expect($log)->not->toBeNull();
        expect($log->reference_id)->toBe($user->id);
        expect($log->new_values)->toBe(['is_active' => true]);
        expect($log->reason)->toBe('Account activated');
    });
});

describe('AuditService — captures request context', function () {
    it('captures IP address from request', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(AuditService::class);
        $log = $service->log('test.action', ['category' => 'test']);

        expect($log->ip_address)->not->toBeNull();
        expect($log->user_agent)->not->toBeNull();
    });
});
