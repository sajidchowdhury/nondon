<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Organization\Models\Branch;
use App\Modules\Sales\Exceptions\BelowMinimumPriceException;
use App\Modules\Sales\Http\Livewire\POS\PointOfSale;
use App\Modules\Sales\Models\SaleInvoice;
use App\Modules\Sales\Support\CartState;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| CartState unit behaviour
|--------------------------------------------------------------------------
*/
describe('CartState — in-memory cart value object', function () {
    it('adds a garment and computes totals', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 500);
        $g1 = availableGarmentAtBranch($branch, $product);
        $g2 = availableGarmentAtBranch($branch, $product);

        $cart = new CartState;
        $cart->add($g1, $price);
        $cart->add($g2, $price);

        expect($cart->count())->toBe(2)
            ->and($cart->subtotal())->toBe('2000.00')
            ->and($cart->totalDiscount())->toBe('0.00')
            ->and($cart->total())->toBe('2000.00');
    });

    it('refuses to add the same garment twice', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($branch, $product);

        $cart = new CartState;
        $cart->add($g, $price);
        expect(fn () => $cart->add($g, $price))->toThrow(RuntimeException::class);
    });

    it('removes a garment by id', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($branch, $product);

        $cart = new CartState;
        $cart->add($g, $price);
        $cart->remove($g->id);

        expect($cart->count())->toBe(0)
            ->and($cart->isEmpty())->toBeTrue();
    });

    it('replaces one garment with another', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 500);
        $old = availableGarmentAtBranch($branch, $product);
        $new = availableGarmentAtBranch($branch, $product);

        $cart = new CartState;
        $cart->add($old, $price);
        $cart->replace($old->id, $new, $price);

        expect($cart->has($old->id))->toBeFalse()
            ->and($cart->has($new->id))->toBeTrue()
            ->and($cart->count())->toBe(1);
    });

    it('applies a discount above minimum and updates the total', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($branch, $product);

        $cart = new CartState;
        $cart->add($g, $price);
        $cart->applyDiscount($g->id, '100.00');

        expect($cart->get($g->id)->finalPrice())->toBe('900.00')
            ->and($cart->totalDiscount())->toBe('100.00')
            ->and($cart->total())->toBe('900.00');
    });

    it('rejects a discount below the minimum price', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 900);
        $g = availableGarmentAtBranch($branch, $product);

        $cart = new CartState;
        $cart->add($g, $price);

        expect(fn () => $cart->applyDiscount($g->id, '200.00'))
            ->toThrow(BelowMinimumPriceException::class);
        // The cart line is unchanged (rollback semantics).
        expect($cart->get($g->id)->appliedDiscount)->toBe('0.00');
    });

    it('rejects a negative discount', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($branch, $product);

        $cart = new CartState;
        $cart->add($g, $price);

        expect(fn () => $cart->applyDiscount($g->id, '-50.00'))
            ->toThrow(BelowMinimumPriceException::class);
    });

    it('clears all items', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 500);
        $cart = new CartState;
        $cart->add(availableGarmentAtBranch($branch, $product), $price);
        $cart->add(availableGarmentAtBranch($branch, $product), $price);
        $cart->clear();

        expect($cart->isEmpty())->toBeTrue();
    });

    it('serializes to an array with no sensitive data', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($branch, $product);

        $cart = new CartState;
        $cart->add($g, $price);
        $array = $cart->toArray();

        expect($array)->toHaveCount(1)
            ->and($array[0])->toHaveKeys(['garment_id', 'barcode', 'product_name', 'standard_price', 'final_price', 'minimum_price'])
            ->and($array[0])->not->toHaveKey('purchase_cost');
    });
});

/*
|--------------------------------------------------------------------------
| POS page authorization
|--------------------------------------------------------------------------
*/
describe('POS page — authorization', function () {
    it('loads for a salesperson with pos.sale.create', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('pos.index'));
        $response->assertStatus(200);
        $response->assertSee('Point of Sale');
    });

    it('loads for admin', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('pos.index'));
        $response->assertStatus(200);
        $response->assertSee('Point of Sale');
    });

    it('redirects a guest to login', function () {
        $response = $this->get(route('pos.index'));
        $response->assertRedirect(route('login'));
    });

    it('is 403 for a user without pos.sale.create', function () {
        // Create a user whose role lacks pos.sale.create.
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $role = Role::firstOrCreate(
            ['name' => 'no_pos', 'guard_name' => 'web'],
            ['display_name' => 'No POS', 'description' => 'Test-only role without pos.sale.create']
        );
        $role->syncPermissions(['inventory.view.all_branches', 'customer.create']);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('pos.index'));
        $response->assertForbidden();
    });
});

/*
|--------------------------------------------------------------------------
| Barcode scan / serial lookup
|--------------------------------------------------------------------------
*/
describe('scanBarcode — add to cart', function () {
    it('adds an available garment at the user\'s branch via barcode scan', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);

        Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode')
            ->assertHasNoErrors()
            ->assertSee($garment->barcode)
            ->assertSee($garment->product->name);
    });

    it('adds a garment via fiscal-year serial lookup (25-00001 format)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);

        Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->fiscal_year_serial)
            ->call('scanBarcode')
            ->assertSee($garment->barcode);
    });

    it('rejects a sold garment with a specific reason and does not add it', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $garment->update(['current_status' => Garment::STATUS_SOLD]);

        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode');

        // The rejection reason names the garment status.
        $component->assertSee('already been sold');
        // The cart stays empty (the garment was not added).
        expect($component->get('cartItems'))->toBeEmpty();
    });

    it('rejects a reserved garment', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $garment->update(['current_status' => Garment::STATUS_RESERVED]);

        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode');

        $component->assertSee('reserved');
        expect($component->get('cartItems'))->toBeEmpty();
    });

    it('rejects a damaged garment', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $garment->update(['current_status' => Garment::STATUS_DAMAGED]);

        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode');

        $component->assertSee('damaged');
        expect($component->get('cartItems'))->toBeEmpty();
    });

    it('rejects a garment located at another branch (wrong branch)', function () {
        $b1 = Branch::where('code', 'B1')->first();
        $b2 = Branch::where('code', 'B2')->first();
        $salesperson = salesperson($b1); // salesperson at B1
        [$product] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($b2, $product); // garment at B2

        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode');

        $component->assertSee('another branch');
        expect($component->get('cartItems'))->toBeEmpty();
    });

    it('rejects scanning the same garment twice (already in cart)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);

        Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode')
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode')
            ->assertSee('already in the cart');
    });

    it('shows "not found" when the barcode is unknown', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', 'NND-99-99999')
            ->call('scanBarcode')
            ->assertSee('No garment found');
    });
});

/*
|--------------------------------------------------------------------------
| Product search dropdown
|--------------------------------------------------------------------------
*/
describe('searchProducts — dropdown', function () {
    it('returns only available garments at the user\'s branch', function () {
        $b1 = Branch::where('code', 'B1')->first();
        $b2 = Branch::where('code', 'B2')->first();
        $salesperson = salesperson($b1);
        [$product, $price] = productWithPrice(1000, 500);
        $atB1 = availableGarmentAtBranch($b1, $product);
        $atB2 = availableGarmentAtBranch($b2, $product); // other branch
        $sold = availableGarmentAtBranch($b1, $product);
        $sold->update(['current_status' => Garment::STATUS_SOLD]);

        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('searchTerm', $product->name)
            ->call('searchProducts');

        $results = collect($component->get('searchResults'));
        $ids = $results->pluck('garment_id');
        expect($ids)->toContain($atB1->id)
            ->and($ids)->not->toContain($atB2->id)
            ->and($ids)->not->toContain($sold->id);
    });

    it('clears results when the search term is empty', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('searchTerm', 'something')
            ->call('searchProducts')
            ->set('searchTerm', '')
            ->call('searchProducts')
            ->assertSet('searchResults', []);
    });
});

/*
|--------------------------------------------------------------------------
| Cart mutations via the UI
|--------------------------------------------------------------------------
*/
describe('cart mutations via the UI', function () {
    it('removes an item from the cart', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);

        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode');

        // Added.
        expect($component->get('cartItems'))->toHaveCount(1);

        $component->call('removeFromCart', $garment->id);

        // Removed — the cart is now empty.
        expect($component->get('cartItems'))->toBeEmpty();
    });

    it('applies a discount above the minimum and updates the total', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 900);
        $garment = availableGarmentAtBranch($branch, $product);

        Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode')
            ->set("discountInputs.{$garment->id}", '100')
            ->call('applyDiscount', $garment->id)
            ->assertHasNoErrors()
            ->assertSee('900.00'); // final price
    });

    it('rejects a discount below the minimum with an error', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 900);
        $garment = availableGarmentAtBranch($branch, $product);

        Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode')
            ->set("discountInputs.{$garment->id}", '200')
            ->call('applyDiscount', $garment->id)
            ->assertHasErrors(["discount.{$garment->id}"]);
    });

    it('clears the cart', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($branch, $product);

        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $g->barcode)
            ->call('scanBarcode');
        expect($component->get('cartItems'))->toHaveCount(1);

        $component->call('clearCart');
        expect($component->get('cartItems'))->toBeEmpty();
    });

    it('replaces an item with a different garment', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product] = productWithPrice(1000, 500);
        $old = availableGarmentAtBranch($branch, $product);
        $new = availableGarmentAtBranch($branch, $product);

        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $old->barcode)
            ->call('scanBarcode');

        // After replace, old is gone and new is present.
        $component->call('replaceItem', $old->id, $new->barcode);
        $cartIds = collect($component->get('cartItems'))->pluck('garment_id');
        expect($cartIds)->not->toContain($old->id)
            ->and($cartIds)->toContain($new->id)
            ->and($cartIds)->toHaveCount(1);
    });
});

/*
|--------------------------------------------------------------------------
| Cart state persistence across round-trips
|--------------------------------------------------------------------------
*/
describe('cart state — persistence across Livewire round-trips', function () {
    it('survives a no-op round-trip (in-memory, no DB drafts)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);

        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode');

        // No-op round-trip (re-render).
        $component->call('$refresh')->assertSee($garment->barcode);

        // Assert no sale_invoice / draft was persisted to the DB.
        expect(SaleInvoice::count())->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| Sensitive-data protection
|--------------------------------------------------------------------------
*/
describe('sensitive data — purchase_cost never shown', function () {
    it('does not expose purchase_cost anywhere in the POS HTML', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        // Create a garment WITH a purchase_cost — it must never render.
        $garment = Garment::factory()->available()->atBranch($branch)->forProduct($product)->create([
            'purchase_cost' => 250.00,
        ]);
        GarmentCurrentStock::where('garment_id', $garment->id)
            ->update(['current_branch_id' => $branch->id, 'current_status' => Garment::STATUS_AVAILABLE]);

        // 1. The initial POS page render never includes the purchase cost.
        $html = $this->actingAs($salesperson)->get(route('pos.index'))->getContent();
        expect($html)->not->toContain('250.00')
            ->and($html)->not->toContain('purchase_cost');

        // 2. After adding the garment to the cart, the cart line renders
        //    product/price/discount but NOT the purchase cost.
        $component = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode');

        $rendered = $component->html();
        expect($rendered)->not->toContain('250.00')
            ->and($rendered)->not->toContain('purchase_cost')
            // the standard price (1000.00) IS shown — sanity check the test
            // is actually exercising the cart line.
            ->and($rendered)->toContain('1000.00');
    });
});

/*
|--------------------------------------------------------------------------
| Navigation visibility
|--------------------------------------------------------------------------
*/
describe('navigation', function () {
    it('shows the POS nav link to a selling role', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $response = $this->actingAs($salesperson)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('POS');
    });

    it('shows the POS nav link to admin', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('POS');
    });

    it('does not show the POS nav link to a role without pos.sale.create', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $role = Role::firstOrCreate(
            ['name' => 'no_pos', 'guard_name' => 'web'],
            ['display_name' => 'No POS', 'description' => 'Test-only role']
        );
        $role->syncPermissions(['inventory.view.all_branches', 'customer.create']);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('>POS<'); // the nav-link label, not a substring elsewhere
    });
});
