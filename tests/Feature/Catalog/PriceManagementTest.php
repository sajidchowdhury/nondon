<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use App\Modules\Catalog\Models\ProductPrice;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

describe('setPrice', function () {
    it('admin can set a product-level price', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        $price = $service->setPrice($product, null, 1500.00, 1000.00);

        expect($price)->toBeInstanceOf(ProductPrice::class);
        expect($price->product_id)->toBe($product->id);
        expect($price->product_size_id)->toBeNull();
        expect($price->standard_price)->toBe('1500.00');
        expect($price->minimum_price)->toBe('1000.00');
        expect($price->effective_to)->toBeNull();
        expect($price->set_by)->toBe($admin->id);
    });

    it('admin can set a size-specific price', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $size = ProductSize::factory()->forProduct($product)->create(['size_label' => '34']);
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        $price = $service->setPrice($product, $size, 1600.00, 1100.00);

        expect($price->product_size_id)->toBe($size->id);
        expect($price->standard_price)->toBe('1600.00');
    });

    it('rejects minimum_price higher than standard_price', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        expect(fn() => $service->setPrice($product, null, 1000.00, 1500.00))
            ->toThrow(ValidationException::class);
    });

    it('rejects negative prices', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        expect(fn() => $service->setPrice($product, null, -100.00, -50.00))
            ->toThrow(ValidationException::class);
    });

    it('setting a new price closes the previous price period', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        // Set first price
        $firstPrice = $service->setPrice($product, null, 1000.00, 800.00);
        expect($firstPrice->effective_to)->toBeNull();

        // Set second price
        $secondPrice = $service->setPrice($product, null, 1200.00, 900.00);

        // First price should now have effective_to set
        $firstPrice = $firstPrice->fresh();
        expect($firstPrice->effective_to)->not->toBeNull();
        expect($secondPrice->effective_to)->toBeNull();
    });

    it('size-specific price does not close product-level price period', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $size = ProductSize::factory()->forProduct($product)->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        // Set product-level price
        $productPrice = $service->setPrice($product, null, 1000.00, 800.00);

        // Set size-specific price (should not affect product-level price)
        $sizePrice = $service->setPrice($product, $size, 1200.00, 900.00);

        // Product-level price should still be active
        $productPrice = $productPrice->fresh();
        expect($productPrice->effective_to)->toBeNull();
        expect($sizePrice->effective_to)->toBeNull();
    });
});

describe('getCurrentPrice', function () {
    it('returns the current product-level price', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);
        $service->setPrice($product, null, 1500.00, 1000.00);

        $current = $service->getCurrentPrice($product);
        expect($current)->not->toBeNull();
        expect($current->standard_price)->toBe('1500.00');
    });

    it('returns size-specific price if it exists', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $size = ProductSize::factory()->forProduct($product)->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        // Set product-level price
        $service->setPrice($product, null, 1000.00, 800.00);
        // Set size-specific price
        $service->setPrice($product, $size, 1200.00, 900.00);

        $current = $service->getCurrentPrice($product, $size);
        expect($current->standard_price)->toBe('1200.00');
    });

    it('falls back to product-level price if no size-specific price', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $size = ProductSize::factory()->forProduct($product)->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        // Set only product-level price
        $service->setPrice($product, null, 1000.00, 800.00);

        $current = $service->getCurrentPrice($product, $size);
        expect($current->standard_price)->toBe('1000.00');
    });

    it('returns null if no price set', function () {
        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        $current = $service->getCurrentPrice($product);
        expect($current)->toBeNull();
    });
});

describe('getPriceAt — historical price lookup', function () {
    it('returns the price effective at a past date', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        // Create an old price (expired)
        ProductPrice::factory()->forProduct($product)->effectiveAt(
            now()->subDays(30),
            now()->subDay()
        )->create([
            'standard_price' => 800.00,
            'minimum_price' => 600.00,
        ]);

        // Create current price
        $service->setPrice($product, null, 1000.00, 800.00);

        // Get price at 15 days ago
        $pastPrice = $service->getPriceAt($product, null, now()->subDays(15));
        expect($pastPrice)->not->toBeNull();
        expect($pastPrice->standard_price)->toBe('800.00');
    });

    it('returns current price for today', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);
        $service->setPrice($product, null, 1500.00, 1000.00);

        $todayPrice = $service->getPriceAt($product, null, now());
        expect($todayPrice->standard_price)->toBe('1500.00');
    });

    it('returns null if no price was effective at the date', function () {
        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        $pastPrice = $service->getPriceAt($product, null, now()->subDays(10));
        expect($pastPrice)->toBeNull();
    });
});

describe('getPriceHistory', function () {
    it('returns all price records ordered by most recent first', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        // Set multiple prices
        $service->setPrice($product, null, 500.00, 400.00);
        sleep(0.01);
        $service->setPrice($product, null, 800.00, 600.00);
        sleep(0.01);
        $service->setPrice($product, null, 1000.00, 800.00);

        $history = $service->getPriceHistory($product);

        expect($history->count())->toBe(3);
        expect($history->first()->standard_price)->toBe('1000.00');
        expect($history->last()->standard_price)->toBe('500.00');
    });

    it('filters by size when provided', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $size = ProductSize::factory()->forProduct($product)->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        $service->setPrice($product, null, 1000.00, 800.00);
        $service->setPrice($product, $size, 1200.00, 900.00);

        $productHistory = $service->getPriceHistory($product);
        $sizeHistory = $service->getPriceHistory($product, $size);

        expect($productHistory->count())->toBe(2); // Both product-level and size-specific
        expect($sizeHistory->count())->toBe(1); // Only size-specific
    });
});

describe('getAllCurrentPrices', function () {
    it('returns all active prices for a product', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        $size1 = ProductSize::factory()->forProduct($product)->create();
        $size2 = ProductSize::factory()->forProduct($product)->create();
        $service = app(\App\Modules\Catalog\Services\PriceService::class);

        $service->setPrice($product, null, 1000.00, 800.00);
        $service->setPrice($product, $size1, 1100.00, 850.00);
        $service->setPrice($product, $size2, 1200.00, 900.00);

        $all = $service->getAllCurrentPrices($product);
        expect($all->count())->toBe(3);
    });
});

describe('model helper methods', function () {
    it('isCurrentlyActive returns true for active price', function () {
        $price = ProductPrice::factory()->create(['effective_to' => null]);
        expect($price->isCurrentlyActive())->toBeTrue();
    });

    it('isCurrentlyActive returns false for expired price', function () {
        $price = ProductPrice::factory()->create(['effective_to' => now()->subDay()]);
        expect($price->isCurrentlyActive())->toBeFalse();
    });

    it('wasEffectiveAt returns true for date within price period', function () {
        $price = ProductPrice::factory()->create([
            'effective_from' => now()->subDays(10),
            'effective_to' => now()->subDay(),
        ]);

        expect($price->wasEffectiveAt(now()->subDays(5)))->toBeTrue();
    });

    it('wasEffectiveAt returns false for date outside price period', function () {
        $price = ProductPrice::factory()->create([
            'effective_from' => now()->subDays(10),
            'effective_to' => now()->subDay(),
        ]);

        expect($price->wasEffectiveAt(now()))->toBeFalse();
    });

    it('appliesToSize returns true for product-level price', function () {
        $price = ProductPrice::factory()->create(['product_size_id' => null]);
        expect($price->appliesToSize(123))->toBeTrue();
    });

    it('appliesToSize returns true for matching size', function () {
        $price = ProductPrice::factory()->create(['product_size_id' => 42]);
        expect($price->appliesToSize(42))->toBeTrue();
    });

    it('appliesToSize returns false for non-matching size', function () {
        $price = ProductPrice::factory()->create(['product_size_id' => 42]);
        expect($price->appliesToSize(99))->toBeFalse();
    });
});

describe('auditing', function () {
    it('price creation is audited', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $product = Product::factory()->create();
        \App\Modules\Audit\Models\AuditLog::query()->delete();

        $service = app(\App\Modules\Catalog\Services\PriceService::class);
        $price = $service->setPrice($product, null, 1500.00, 1000.00);

        $auditLog = \App\Modules\Audit\Models\AuditLog::where('action', 'price.create')
            ->where('reference_id', $price->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->user_id)->toBe($admin->id);
    });
});

describe('authorization', function () {
    it('denies supervisor from setting prices', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $product = Product::factory()->create();

        // Supervisor cannot access product edit (where price manager is)
        $response = $this->actingAs($supervisor)->get(route('products.edit', $product));
        $response->assertForbidden();
    });

    it('denies salesperson from setting prices', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $product = Product::factory()->create();

        $response = $this->actingAs($salesperson)->get(route('products.edit', $product));
        $response->assertForbidden();
    });
});
