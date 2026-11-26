<?php

use App\Modules\Organization\Models\FiscalYear;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('fiscal year list — viewAny', function () {
    it('allows admin to view fiscal year list', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('fiscal-years.index'));

        $response->assertStatus(200);
        $response->assertSee('Fiscal Years');
    });

    it('allows supervisor to view fiscal year list', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('fiscal-years.index'));

        $response->assertStatus(200);
    });

    it('denies salesperson access to fiscal year list', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('fiscal-years.index'));

        $response->assertForbidden();
    });
});

describe('fiscal year create', function () {
    it('allows admin to view create form', function () {
        $admin = adminUser();

        $response = $this->actingAs($admin)->get(route('fiscal-years.create'));

        $response->assertStatus(200);
        $response->assertSee('Create Fiscal Year');
    });

    it('denies supervisor access to create form', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('fiscal-years.create'));

        $response->assertForbidden();
    });

    it('allows admin to create a fiscal year with valid start year', function () {
        $admin = adminUser();

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $fy = $service->create(['start_year' => 2027]);

        expect($fy)->toBeInstanceOf(FiscalYear::class);
        expect($fy->name)->toBe('FY 27_28');
        expect($fy->serial_prefix)->toBe('27');
        expect($fy->start_date->format('Y-m-d'))->toBe('2027-07-01');
        expect($fy->end_date->format('Y-m-d'))->toBe('2028-06-30');
        expect($fy->is_active)->toBeFalse();
        expect($fy->is_closed)->toBeFalse();
        expect($fy->current_serial_sequence)->toBe(0);
    });

    it('auto-calculates start date as July 1', function () {
        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $fy = $service->create(['start_year' => 2028]);

        expect($fy->start_date->format('m-d'))->toBe('07-01');
    });

    it('auto-calculates end date as June 30 of next year', function () {
        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $fy = $service->create(['start_year' => 2028]);

        expect($fy->end_date->format('Y-m-d'))->toBe('2029-06-30');
    });

    it('auto-derives serial prefix from start year', function () {
        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $fy = $service->create(['start_year' => 2025]);

        expect($fy->serial_prefix)->toBe('25');
    });

    it('auto-generates name if not provided', function () {
        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $fy = $service->create(['start_year' => 2028]);

        expect($fy->name)->toBe('FY 28_29');
    });

    it('rejects overlapping fiscal years', function () {
        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        // The seeder creates the current fiscal year, so trying to create the same one should fail
        $currentYear = (int) date('Y');
        $adjustment = (date('n') >= 7) ? 0 : -1;
        $startYear = $currentYear + $adjustment;

        expect(fn() => $service->create(['start_year' => $startYear]))
            ->toThrow(ValidationException::class);
    });

    it('rejects start year before 2020', function () {
        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        expect(fn() => $service->create(['start_year' => 2019]))
            ->toThrow(ValidationException::class);
    });

    it('rejects start year after 2100', function () {
        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        expect(fn() => $service->create(['start_year' => 2101]))
            ->toThrow(ValidationException::class);
    });
});

describe('fiscal year close', function () {
    it('allows admin to close a fiscal year', function () {
        $admin = adminUser();

        $fy = FiscalYear::factory()->create([
            'is_active' => false,
            'is_closed' => false,
        ]);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $closed = $service->close($fy);

        expect($closed->is_closed)->toBeTrue();
        expect($closed->is_active)->toBeFalse();
    });

    it('prevents closing an already closed fiscal year', function () {
        $fy = FiscalYear::factory()->create([
            'is_closed' => true,
        ]);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        expect(fn() => $service->close($fy))
            ->toThrow(ValidationException::class);
    });

    it('deactivates fiscal year when closing if it was active', function () {
        $fy = FiscalYear::factory()->create([
            'is_active' => true,
            'is_closed' => false,
        ]);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $closed = $service->close($fy);

        expect($closed->is_active)->toBeFalse();
        expect($closed->is_closed)->toBeTrue();
    });
});

describe('fiscal year activate', function () {
    it('allows admin to activate a fiscal year', function () {
        $fy = FiscalYear::factory()->create([
            'is_active' => false,
            'is_closed' => false,
        ]);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $activated = $service->activate($fy);

        expect($activated->is_active)->toBeTrue();
    });

    it('prevents activating a closed fiscal year', function () {
        $fy = FiscalYear::factory()->create([
            'is_active' => false,
            'is_closed' => true,
        ]);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        expect(fn() => $service->activate($fy))
            ->toThrow(ValidationException::class);
    });

    it('deactivates other fiscal years when activating one', function () {
        // Create two fiscal years
        $fy1 = FiscalYear::factory()->create([
            'name' => 'FY 30_31',
            'start_date' => '2030-07-01',
            'end_date' => '2031-06-30',
            'is_active' => true,
            'is_closed' => false,
        ]);
        $fy2 = FiscalYear::factory()->create([
            'name' => 'FY 31_32',
            'start_date' => '2031-07-01',
            'end_date' => '2032-06-30',
            'is_active' => false,
            'is_closed' => false,
        ]);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        // Activate fy2
        $service->activate($fy2);

        // fy1 should be deactivated
        expect($fy1->fresh()->is_active)->toBeFalse();
        expect($fy2->fresh()->is_active)->toBeTrue();
    });

    it('only one fiscal year can be active at a time', function () {
        $activeCount = FiscalYear::where('is_active', true)->where('is_closed', false)->count();

        expect($activeCount)->toBeLessThanOrEqual(1);
    });
});

describe('getCurrent', function () {
    it('returns the currently active fiscal year', function () {
        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        $current = $service->getCurrent();

        // The seeder should have created an active fiscal year
        expect($current)->not->toBeNull();
        expect($current->is_active)->toBeTrue();
        expect($current->is_closed)->toBeFalse();
    });

    it('returns null if no active fiscal year', function () {
        // Deactivate all fiscal years
        FiscalYear::query()->update(['is_active' => false]);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        expect($service->getCurrent())->toBeNull();
    });
});

describe('serial generation — format', function () {
    it('generates correct format YY-NNNNN', function () {
        $fy = FiscalYear::factory()->create([
            'serial_prefix' => '25',
            'current_serial_sequence' => 0,
        ]);

        expect($fy->generateNextSerial())->toBe('25-00001');
    });

    it('generates correct serial at sequence 1208', function () {
        $fy = FiscalYear::factory()->create([
            'serial_prefix' => '25',
            'current_serial_sequence' => 1208,
        ]);

        expect($fy->generateNextSerial())->toBe('25-01209');
    });

    it('generates first serial of a fiscal year', function () {
        $fy = FiscalYear::factory()->create([
            'serial_prefix' => '26',
            'current_serial_sequence' => 0,
        ]);

        expect($fy->generateNextSerial())->toBe('26-00001');
    });

    it('increments sequence counter', function () {
        $fy = FiscalYear::factory()->create([
            'serial_prefix' => '25',
            'current_serial_sequence' => 5,
        ]);

        $fy->incrementSequence();

        expect($fy->fresh()->current_serial_sequence)->toBe(6);
    });
});

describe('serial generation — concurrency safety', function () {
    it('generateNextSerial with advisory lock produces unique serials', function () {
        $fy = FiscalYear::factory()->create([
            'serial_prefix' => '25',
            'current_serial_sequence' => 0,
            'is_closed' => false,
        ]);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        // Generate 10 serials sequentially within transactions
        $serials = collect();

        for ($i = 0; $i < 10; $i++) {
            DB::transaction(function () use ($service, $fy, &$serials) {
                $serials->push($service->generateNextSerial($fy));
            });
        }

        // All serials should be unique
        expect($serials->unique()->count())->toBe(10);

        // The first should be 25-00001 and the last 25-00010
        expect($serials->first())->toBe('25-00001');
        expect($serials->last())->toBe('25-00010');

        // The sequence counter should be 10
        expect($fy->fresh()->current_serial_sequence)->toBe(10);
    });

    it('rejects serial generation for closed fiscal year', function () {
        $fy = FiscalYear::factory()->create([
            'is_closed' => true,
        ]);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);

        expect(fn() => DB::transaction(fn() => $service->generateNextSerial($fy)))
            ->toThrow(ValidationException::class);
    });
});

describe('fiscal year view', function () {
    it('allows admin to view fiscal year details', function () {
        $admin = adminUser();
        $fy = FiscalYear::factory()->create();

        $response = $this->actingAs($admin)->get(route('fiscal-years.show', $fy));

        $response->assertStatus(200);
        $response->assertSee($fy->name);
    });

    it('shows next serial preview for open fiscal year', function () {
        $admin = adminUser();
        $fy = FiscalYear::factory()->create([
            'serial_prefix' => '25',
            'current_serial_sequence' => 99,
            'is_closed' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('fiscal-years.show', $fy));

        $response->assertStatus(200);
        $response->assertSee('25-00100');
    });

    it('denies salesperson access to fiscal year view', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $fy = FiscalYear::factory()->create();

        $response = $this->actingAs($salesperson)->get(route('fiscal-years.show', $fy));

        $response->assertForbidden();
    });
});

describe('auditing', function () {
    it('fiscal year creation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $fy = $service->create(['start_year' => 2035]);

        $auditLog = \App\Modules\Audit\Models\AuditLog::where('action', 'fiscal_year.create')
            ->where('reference_type', 'fiscal_year')
            ->where('reference_id', $fy->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->user_id)->toBe($admin->id);
    });

    it('fiscal year activation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $fy = FiscalYear::factory()->create(['is_active' => false, 'is_closed' => false]);
        \App\Modules\Audit\Models\AuditLog::query()->delete();

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $service->activate($fy);

        $auditLog = \App\Modules\Audit\Models\AuditLog::where('action', 'fiscal_year.activate')->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->reference_id)->toBe($fy->id);
    });

    it('fiscal year close is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $fy = FiscalYear::factory()->create(['is_closed' => false]);
        \App\Modules\Audit\Models\AuditLog::query()->delete();

        $service = app(\App\Modules\Organization\Services\FiscalYearService::class);
        $service->close($fy);

        $auditLog = \App\Modules\Audit\Models\AuditLog::where('action', 'fiscal_year.close')->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->reference_id)->toBe($fy->id);
    });
});

describe('model helper methods', function () {
    it('isCurrent returns true for active non-closed fiscal year', function () {
        $fy = FiscalYear::factory()->create(['is_active' => true, 'is_closed' => false]);

        expect($fy->isCurrent())->toBeTrue();
    });

    it('isCurrent returns false for closed fiscal year', function () {
        $fy = FiscalYear::factory()->create(['is_active' => true, 'is_closed' => true]);

        expect($fy->isCurrent())->toBeFalse();
    });

    it('garments_received_count returns the serial sequence', function () {
        $fy = FiscalYear::factory()->create(['current_serial_sequence' => 500]);

        expect($fy->garments_received_count)->toBe(500);
    });

    it('containsDate returns true for date within fiscal year', function () {
        $fy = FiscalYear::factory()->create([
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);

        expect($fy->containsDate(\Carbon\Carbon::parse('2025-12-15')))->toBeTrue();
        expect($fy->containsDate(\Carbon\Carbon::parse('2026-01-01')))->toBeTrue();
    });

    it('containsDate returns false for date outside fiscal year', function () {
        $fy = FiscalYear::factory()->create([
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);

        expect($fy->containsDate(\Carbon\Carbon::parse('2025-06-30')))->toBeFalse();
        expect($fy->containsDate(\Carbon\Carbon::parse('2026-07-01')))->toBeFalse();
    });
});
