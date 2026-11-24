<?php

use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Inventory\Services\GarmentIdentityService;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
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

describe('garment creation via GarmentIdentityService', function () {
    it('can create a garment with product, size, fiscal year, and branch', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $size = ProductSize::factory()->forProduct($product)->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, $size, $fiscalYear, $branch);

        expect($garment)->toBeInstanceOf(Garment::class);
        expect($garment->product_id)->toBe($product->id);
        expect($garment->product_size_id)->toBe($size->id);
        expect($garment->fiscal_year_id)->toBe($fiscalYear->id);
        expect($garment->current_branch_id)->toBe($branch->id);
        expect($garment->current_status)->toBe(Garment::STATUS_AVAILABLE);
    });

    it('generates a valid UUID for internal_id', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        expect($garment->internal_id)->not->toBeNull();
        expect(strlen($garment->internal_id))->toBe(36); // UUID v4 length
        expect(Str::isUuid($garment->internal_id))->toBeTrue();
    });

    it('generates a globally unique barcode', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment1 = $service->createGarment($product, null, $fiscalYear, $branch);
        $garment2 = $service->createGarment($product, null, $fiscalYear, $branch);

        expect($garment1->barcode)->not->toBe($garment2->barcode);
        expect($garment1->barcode)->toStartWith('NND-');
    });

    it('generates fiscal-year serial in YY-NNNNN format', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        expect($garment->fiscal_year_serial)->toMatch('/^\d{2}-\d{5}$/');
        expect($garment->fiscal_year_serial)->toStartWith($fiscalYear->serial_prefix . '-');
    });

    it('barcode embeds the serial', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        expect($garment->barcode)->toBe('NND-' . $garment->fiscal_year_serial);
    });

    it('creates garment with status available', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        expect($garment->current_status)->toBe(Garment::STATUS_AVAILABLE);
        expect($garment->isAvailable())->toBeTrue();
        expect($garment->canBeSold())->toBeTrue();
    });

    it('creates garment current stock projection alongside', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        $projection = GarmentCurrentStock::where('garment_id', $garment->id)->first();

        expect($projection)->not->toBeNull();
        expect($projection->current_status)->toBe(Garment::STATUS_AVAILABLE);
        expect($projection->current_branch_id)->toBe($branch->id);
    });

    it('rejects creating garment in closed fiscal year', function () {
        $product = Product::factory()->create();
        $closedFY = FiscalYear::factory()->create(['is_closed' => true, 'is_active' => false]);
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);

        expect(fn() => $service->createGarment($product, null, $closedFY, $branch))
            ->toThrow(ValidationException::class);
    });

    it('generates sequential serials for multiple garments', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);

        $garment1 = $service->createGarment($product, null, $fiscalYear, $branch);
        $garment2 = $service->createGarment($product, null, $fiscalYear, $branch);
        $garment3 = $service->createGarment($product, null, $fiscalYear, $branch);

        // Serials should be sequential
        $seq1 = (int) substr($garment1->fiscal_year_serial, 3);
        $seq2 = (int) substr($garment2->fiscal_year_serial, 3);
        $seq3 = (int) substr($garment3->fiscal_year_serial, 3);

        expect($seq2)->toBe($seq1 + 1);
        expect($seq3)->toBe($seq2 + 1);
    });
});

describe('uniqueness constraints', function () {
    it('prevents duplicate barcode', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        // Try to create another with the same barcode
        expect(fn() => Garment::create([
            'internal_id' => \Illuminate\Support\Str::uuid(),
            'barcode' => $garment->barcode,
            'fiscal_year_id' => $fiscalYear->id,
            'fiscal_year_serial' => '99-99999',
            'product_id' => $product->id,
            'current_status' => 'available',
            'current_branch_id' => $branch->id,
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('prevents duplicate fiscal_year_serial within same fiscal year', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        // Try to create another with the same serial in the same fiscal year
        expect(fn() => Garment::create([
            'internal_id' => \Illuminate\Support\Str::uuid(),
            'barcode' => 'NND-UNIQUE-DIFFERENT',
            'fiscal_year_id' => $fiscalYear->id,
            'fiscal_year_serial' => $garment->fiscal_year_serial,
            'product_id' => $product->id,
            'current_status' => 'available',
            'current_branch_id' => $branch->id,
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});

describe('lookup methods', function () {
    it('can lookup by barcode', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        $found = $service->lookupByBarcode($garment->barcode);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($garment->id);
    });

    it('can lookup by serial', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        $found = $service->lookupBySerial($garment->fiscal_year_serial);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($garment->id);
    });

    it('can lookup by internal ID', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        $found = $service->lookupById($garment->internal_id);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($garment->id);
    });

    it('returns null for nonexistent barcode', function () {
        $service = app(GarmentIdentityService::class);
        expect($service->lookupByBarcode('NONEXISTENT'))->toBeNull();
    });
});

describe('purchase cost visibility', function () {
    it('purchase_cost is hidden from serialization', function () {
        $garment = Garment::factory()->create(['purchase_cost' => 500.00]);
        $array = $garment->toArray();

        expect($array)->not->toHaveKey('purchase_cost');
    });

    it('purchase_cost visible to admin via withPurchaseCost()', function () {
        $garment = Garment::factory()->create(['purchase_cost' => 500.00]);
        $garment->withPurchaseCost();
        $array = $garment->toArray();

        expect($array)->toHaveKey('purchase_cost');
        expect($array['purchase_cost'])->toBe('500.00');
    });
});

describe('status helper methods', function () {
    it('isAvailable returns true for available garment', function () {
        $garment = Garment::factory()->available()->create();
        expect($garment->isAvailable())->toBeTrue();
    });

    it('isSold returns true for sold garment', function () {
        $garment = Garment::factory()->sold()->create();
        expect($garment->isSold())->toBeTrue();
        expect($garment->isAvailable())->toBeFalse();
    });

    it('canBeSold returns true for available garment', function () {
        $garment = Garment::factory()->available()->create();
        expect($garment->canBeSold())->toBeTrue();
    });

    it('canBeSold returns false for reserved garment', function () {
        $garment = Garment::factory()->reserved()->create();
        expect($garment->canBeSold())->toBeFalse();
    });

    it('isNonSaleable returns true for sold garment', function () {
        $garment = Garment::factory()->sold()->create();
        expect($garment->isNonSaleable())->toBeTrue();
    });
});

describe('status transitions', function () {
    it('can reserve a garment', function () {
        $garment = Garment::factory()->available()->create();

        $garment->reserve('transfer_request', 1);

        expect($garment->fresh()->isReserved())->toBeTrue();
        $projection = GarmentCurrentStock::where('garment_id', $garment->id)->first();
        expect($projection->reservation_reference_type)->toBe('transfer_request');
    });

    it('can release a reservation', function () {
        $garment = Garment::factory()->available()->create();
        $garment->reserve('transfer_request', 1);
        $garment->releaseReservation();

        expect($garment->fresh()->isAvailable())->toBeTrue();
        $projection = GarmentCurrentStock::where('garment_id', $garment->id)->first();
        expect($projection->reservation_reference_type)->toBeNull();
    });

    it('can mark as sold', function () {
        $garment = Garment::factory()->available()->create();
        $garment->markSold();

        expect($garment->fresh()->isSold())->toBeTrue();
    });

    it('can mark as damaged', function () {
        $garment = Garment::factory()->available()->create();
        $garment->markDamaged();

        expect($garment->fresh()->isDamaged())->toBeTrue();
    });

    it('can mark as missing', function () {
        $garment = Garment::factory()->available()->create();
        $garment->markMissing();

        expect($garment->fresh()->isMissing())->toBeTrue();
    });

    it('can relocate to a different branch', function () {
        $garment = Garment::factory()->available()->create();
        $newBranch = Branch::where('code', 'B1')->first();

        $garment->relocate($newBranch->id);

        expect($garment->fresh()->current_branch_id)->toBe($newBranch->id);
    });
});

describe('query scopes', function () {
    it('scopeAvailable returns only available garments', function () {
        Garment::factory()->available()->create();
        Garment::factory()->sold()->create();

        expect(Garment::available()->count())->toBe(1);
    });

    it('scopeForBranch filters by branch', function () {
        $branchA = Branch::where('code', 'M')->first();
        $branchB = Branch::where('code', 'B1')->first();

        Garment::factory()->atBranch($branchA)->create();
        Garment::factory()->atBranch($branchB)->create();

        expect(Garment::forBranch($branchA->id)->count())->toBe(1);
        expect(Garment::forBranch($branchB->id)->count())->toBe(1);
    });

    it('scopeForProduct filters by product', function () {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        Garment::factory()->forProduct($productA)->create();
        Garment::factory()->forProduct($productB)->create();

        expect(Garment::forProduct($productA->id)->count())->toBe(1);
    });
});

describe('bulk create', function () {
    it('can create multiple garments at once', function () {
        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garments = $service->bulkCreate($product, null, $fiscalYear, $branch, 5);

        expect($garments)->toHaveCount(5);

        // All barcodes should be unique
        $barcodes = collect($garments)->pluck('barcode');
        expect($barcodes->unique()->count())->toBe(5);

        // All serials should be unique and sequential
        $serials = collect($garments)->pluck('fiscal_year_serial');
        expect($serials->unique()->count())->toBe(5);
    });
});

describe('authorization', function () {
    it('all authenticated users can view garment list', function () {
        $admin = adminUser();
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $salesperson = salesperson($branch);

        expect($this->actingAs($admin)->get(route('garments.index'))->status())->toBe(200);
        expect($this->actingAs($supervisor)->get(route('garments.index'))->status())->toBe(200);
        expect($this->actingAs($salesperson)->get(route('garments.index'))->status())->toBe(200);
    });

    it('guest redirected to login', function () {
        $response = $this->get(route('garments.index'));
        $response->assertRedirect(route('login'));
    });
});

describe('auditing', function () {
    it('garment creation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $fiscalYear = FiscalYear::active()->first();
        $branch = Branch::where('code', 'M')->first();

        $service = app(GarmentIdentityService::class);
        $garment = $service->createGarment($product, null, $fiscalYear, $branch);

        $auditLog = \App\Modules\Audit\Models\AuditLog::where('action', 'garment.create')
            ->where('reference_type', 'garment')
            ->where('reference_id', $garment->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->user_id)->toBe($admin->id);
    });
});
