<?php

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Organization\Models\Branch;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Suite Configuration
|--------------------------------------------------------------------------
|
| Pest PHP configuration for the Nondon ERP project.
| Tests use PostgreSQL with a dedicated nondon_test database.
| RefreshDatabase trait handles migration and cleanup.
|
*/

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure below tells Pest to use the Tests\TestCase class as the
| base test case for all tests in the Feature and Unit directories.
| This gives every test access to Laravel testing helpers:
|   - $this->seed()
|   - $this->actingAs()
|   - $this->get(), $this->post()
|   - $this->assertDatabaseHas()
|   - RefreshDatabase trait (via Tests\TestCase)
|
*/
uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
|
| Helper functions available to all Pest tests for creating users
| with specific roles and branches.
|
*/

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('main_branch_admin');

    return $user->fresh();
}

function supervisor(Branch $branch): User
{
    $user = User::factory()->forBranch($branch)->create();
    $user->assignRole('branch_supervisor');

    return $user->fresh();
}

function salesperson(Branch $branch): User
{
    $user = User::factory()->forBranch($branch)->create();
    $user->assignRole('salesperson');

    return $user->fresh();
}

function onlineTeamMember(): User
{
    $onlineBranch = Branch::where('is_online_channel', true)->first();
    $user = User::factory()->forBranch($onlineBranch)->onlineChannel()->create();
    $user->assignRole('online_team');

    return $user->fresh();
}

function onlineSupervisor(): User
{
    $onlineBranch = Branch::where('is_online_channel', true)->first();
    $user = User::factory()->forBranch($onlineBranch)->onlineChannel()->create();
    $user->assignRole('online_supervisor');

    return $user->fresh();
}

function mainBranch(): Branch
{
    return Branch::where('type', 'main')->first();
}

/*
|--------------------------------------------------------------------------
| Sales test helpers (Session 3.2)
|--------------------------------------------------------------------------
|
| Helpers for creating the minimum fixtures a POS sale needs: a product
| with an active price (standard + minimum) and a saleable garment at a
| branch with its current-stock projection row.
|
*/

/**
 * Create a Product with an active price (standard + minimum). The price is
 * size-level null (applies to all sizes of the product).
 *
 * @return array{0: Product, 1: ProductPrice}
 */
function productWithPrice(float $standardPrice = 1000, float $minimumPrice = 500): array
{
    $product = App\Modules\Catalog\Models\Product::factory()->create();
    $price = App\Modules\Catalog\Models\ProductPrice::factory()->create([
        'product_id' => $product->id,
        'product_size_id' => null,
        'standard_price' => $standardPrice,
        'minimum_price' => $minimumPrice,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    return [$product, $price];
}

/**
 * Create a saleable (available) garment at the given branch, with its
 * garment_current_stock projection row.
 */
function availableGarmentAtBranch(Branch $branch, ?App\Modules\Catalog\Models\Product $product = null): Garment
{
    $product ??= App\Modules\Catalog\Models\Product::factory()->create();
    $garment = Garment::factory()
        ->available()
        ->atBranch($branch)
        ->forProduct($product)
        ->create();

    // Keep the projection row's branch in sync (the factory's afterCreating
    // sets current_branch_id from the default Main branch).
    GarmentCurrentStock::where('garment_id', $garment->id)->update([
        'current_branch_id' => $branch->id,
        'current_status' => Garment::STATUS_AVAILABLE,
    ]);

    return $garment->fresh();
}
