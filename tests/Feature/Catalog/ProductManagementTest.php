<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Organization\Models\Branch;
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

describe('product list — viewAny', function () {
    it('allows admin to view product list', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('products.index'));
        $response->assertStatus(200);
        $response->assertSee('Products');
    });

    it('allows supervisor to view product list', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $response = $this->actingAs($supervisor)->get(route('products.index'));
        $response->assertStatus(200);
    });

    it('allows salesperson to view product list (for future POS)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $response = $this->actingAs($salesperson)->get(route('products.index'));
        $response->assertStatus(200);
    });
});

describe('product create', function () {
    it('allows admin to view create form', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('products.create'));
        $response->assertStatus(200);
        $response->assertSee('Create Product');
    });

    it('denies supervisor access to create form', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $response = $this->actingAs($supervisor)->get(route('products.create'));
        $response->assertForbidden();
    });

    it('allows admin to create a product with sizes', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(ProductService::class);
        $product = $service->create([
            'code' => 'DRESS001',
            'name' => 'Summer Dress',
            'description' => 'A beautiful summer dress',
            'category' => 'Dresses',
            'sizes' => ['32', '34', '36'],
        ]);

        expect($product)->toBeInstanceOf(Product::class);
        expect($product->code)->toBe('DRESS001');
        expect($product->name)->toBe('Summer Dress');
        expect($product->sizes->count())->toBe(3);
        expect($product->sizes->pluck('size_label')->toArray())->toBe(['32', '34', '36']);
    });

    it('rejects duplicate product code', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(ProductService::class);
        $service->create(['code' => 'UNIQUE1', 'name' => 'First Product']);

        expect(fn () => $service->create(['code' => 'UNIQUE1', 'name' => 'Duplicate']))
            ->toThrow(ValidationException::class);
    });

    it('can create a product without sizes', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(ProductService::class);
        $product = $service->create([
            'code' => 'NOSIZE',
            'name' => 'No Size Product',
        ]);

        expect($product->sizes->count())->toBe(0);
    });
});

describe('product update', function () {
    it('allows admin to update product details', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create(['name' => 'Original Name']);
        $service = app(ProductService::class);

        $updated = $service->update($product, ['name' => 'Updated Name']);
        expect($updated->name)->toBe('Updated Name');
    });

    it('allows admin to change product code to a new unique value', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create(['code' => 'OLDCODE']);
        $service = app(ProductService::class);

        $updated = $service->update($product, ['code' => 'NEWCODE']);
        expect($updated->code)->toBe('NEWCODE');
    });

    it('rejects duplicate code on update', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product1 = Product::factory()->create(['code' => 'CODE1']);
        $product2 = Product::factory()->create(['code' => 'CODE2']);
        $service = app(ProductService::class);

        expect(fn () => $service->update($product2, ['code' => 'CODE1']))
            ->toThrow(ValidationException::class);
    });
});

describe('sizes management', function () {
    it('admin can add a size to a product', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(ProductService::class);

        $size = $service->addSize($product, '38');
        expect($size)->toBeInstanceOf(ProductSize::class);
        expect($size->size_label)->toBe('38');
        expect($size->is_active)->toBeTrue();
    });

    it('rejects adding duplicate size to a product', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(ProductService::class);

        $service->addSize($product, '38');

        expect(fn () => $service->addSize($product, '38'))
            ->toThrow(ValidationException::class);
    });

    it('admin can remove a size from a product', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(ProductService::class);

        $service->addSize($product, '38');
        $service->removeSize($product, '38');

        $size = ProductSize::where('product_id', $product->id)->where('size_label', '38')->first();
        expect($size->is_active)->toBeFalse();
    });

    it('rejects removing a size that does not exist', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(ProductService::class);

        expect(fn () => $service->removeSize($product, 'NONEXISTENT'))
            ->toThrow(ValidationException::class);
    });

    it('allows same size label for different products', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $productA = Product::factory()->create(['code' => 'PRODA']);
        $productB = Product::factory()->create(['code' => 'PRODB']);
        $service = app(ProductService::class);

        $sizeA = $service->addSize($productA, '38');
        $sizeB = $service->addSize($productB, '38');

        expect($sizeA)->not->toBeNull();
        expect($sizeB)->not->toBeNull();
    });
});

describe('activate / deactivate', function () {
    it('admin can activate a product', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create(['is_active' => false]);
        $service = app(ProductService::class);

        $activated = $service->activate($product);
        expect($activated->is_active)->toBeTrue();
        expect($activated->deactivated_at)->toBeNull();
    });

    it('admin can deactivate a product', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create(['is_active' => true]);
        $service = app(ProductService::class);

        $deactivated = $service->deactivate($product);
        expect($deactivated->is_active)->toBeFalse();
        expect($deactivated->deactivated_at)->not->toBeNull();
        expect($deactivated->deactivated_by)->toBe($admin->id);
    });

    it('deactivated product remains in database', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(ProductService::class);

        $service->deactivate($product);

        expect(Product::where('id', $product->id)->exists())->toBeTrue();
    });
});

describe('product search and list', function () {
    it('returns paginated list of products', function () {
        Product::factory()->count(5)->create();
        $service = app(ProductService::class);
        $result = $service->getPaginatedList(['per_page' => 10]);
        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });

    it('searches by code', function () {
        Product::factory()->create(['code' => 'SEARCH1', 'name' => 'Test']);
        Product::factory()->create(['code' => 'OTHER', 'name' => 'Other']);
        $service = app(ProductService::class);
        $results = $service->search('SEARCH1');
        expect($results->count())->toBe(1);
        expect($results->first()->code)->toBe('SEARCH1');
    });

    it('searches by name', function () {
        Product::factory()->create(['name' => 'Red Dress']);
        Product::factory()->create(['name' => 'Blue Shirt']);
        $service = app(ProductService::class);
        $results = $service->search('Red');
        expect($results->count())->toBe(1);
        expect($results->first()->name)->toBe('Red Dress');
    });

    it('filters by status', function () {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);
        $service = app(ProductService::class);

        $active = $service->getPaginatedList(['status' => 'active', 'per_page' => 100]);
        $inactive = $service->getPaginatedList(['status' => 'inactive', 'per_page' => 100]);

        expect($active->total())->toBeGreaterThanOrEqual(1);
        expect($inactive->total())->toBe(1);
    });
});

describe('product edit — authorization', function () {
    it('allows admin to view edit form', function () {
        $admin = adminUser();
        $product = Product::factory()->create();
        $response = $this->actingAs($admin)->get(route('products.edit', $product));
        $response->assertStatus(200);
        $response->assertSee('Edit Product');
    });

    it('denies supervisor access to edit form', function () {
        $branch = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $product = Product::factory()->create();
        $response = $this->actingAs($supervisor)->get(route('products.edit', $product));
        $response->assertForbidden();
    });
});

describe('product view', function () {
    it('allows admin to view product details', function () {
        $admin = adminUser();
        $product = Product::factory()->create();
        $response = $this->actingAs($admin)->get(route('products.show', $product));
        $response->assertStatus(200);
        $response->assertSee($product->name);
    });

    it('allows salesperson to view product details', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $product = Product::factory()->create();
        $response = $this->actingAs($salesperson)->get(route('products.show', $product));
        $response->assertStatus(200);
    });
});

describe('auditing', function () {
    it('product creation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $service = app(ProductService::class);
        $product = $service->create(['code' => 'AUDIT1', 'name' => 'Audit Test']);

        $auditLog = AuditLog::where('action', 'product.create')
            ->where('reference_type', 'product')
            ->where('reference_id', $product->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->user_id)->toBe($admin->id);
    });

    it('product update is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create(['name' => 'Original']);
        AuditLog::query()->delete();

        $service = app(ProductService::class);
        $service->update($product, ['name' => 'Updated']);

        $auditLog = AuditLog::where('action', 'product.update')
            ->where('reference_id', $product->id)
            ->first();

        expect($auditLog)->not->toBeNull();
    });

    it('size addition is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        AuditLog::query()->delete();

        $service = app(ProductService::class);
        $size = $service->addSize($product, '40');

        $auditLog = AuditLog::where('action', 'product_size.create')
            ->where('reference_id', $size->id)
            ->first();

        expect($auditLog)->not->toBeNull();
    });
});
