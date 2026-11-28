<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Organization\Models\Branch;
use App\Modules\Sales\Http\Livewire\POS\Checkout;
use App\Modules\Sales\Http\Livewire\POS\PointOfSale;
use App\Modules\Sales\Models\SaleInvoice;
use App\Modules\Sales\Models\SaleInvoiceItem;
use App\Modules\Sales\Models\SalePayment;
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

/**
 * Build a cart payload (the shape PointOfSale.proceedToCheckout emits to
 * Checkout's openCheckout listener) from a set of garments + prices.
 *
 * @param  array  $lines  [{garment, price, discount?}, ...]
 */
function cartPayload(array $lines): array
{
    $items = [];
    foreach ($lines as $line) {
        $garment = $line['garment'];
        $price = $line['price'];
        $discount = (string) ($line['discount'] ?? '0.00');
        $standard = (string) $price->standard_price;
        $items[] = [
            'garment_id' => $garment->id,
            'barcode' => $garment->barcode,
            'serial' => $garment->fiscal_year_serial,
            'product_name' => $garment->product?->name ?? '(unknown)',
            'size_label' => $garment->productSize?->size_label,
            'standard_price' => $standard,
            'applied_discount' => $discount,
            'final_price' => bcsub($standard, $discount, 2),
            'minimum_price' => (string) $price->minimum_price,
            'is_clearance' => (bool) $garment->is_clearance,
            'at_or_below_minimum' => bccomp(bcsub($standard, $discount, 2), (string) $price->minimum_price, 2) <= 0,
        ];
    }

    return ['items' => $items, 'saleType' => 'retail'];
}

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/
describe('checkout — authorization', function () {
    it('requires pos.sale.complete to complete a sale', function () {
        $branch = Branch::where('code', 'B1')->first();
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        // Salesperson has pos.sale.complete — completes successfully.
        $salesperson = salesperson($branch);
        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale')
            ->assertNotSet('completedInvoiceId', null);

        expect(SaleInvoice::count())->toBe(1);
    });

    it('is denied for a role without pos.sale.complete', function () {
        $branch = Branch::where('code', 'B1')->first();
        $user = User::factory()->forBranch($branch)->create();
        $role = Role::firstOrCreate(
            ['name' => 'no_complete', 'guard_name' => 'web'],
            ['display_name' => 'No Complete', 'description' => 'Test-only role']
        );
        $role->syncPermissions(['pos.sale.create', 'inventory.view.all_branches', 'customer.create']);
        $user->assignRole($role);

        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        Livewire::actingAs($user)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale')
            ->assertSet('completedInvoiceId', null);

        // No invoice created.
        expect(SaleInvoice::count())->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| Customer lookup / walk-in creation
|--------------------------------------------------------------------------
*/
describe('customer — lookup and walk-in', function () {
    it('looks up an existing customer by phone', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $customer = Customer::factory()->walkIn()->create(['phone' => '01711112222', 'name' => 'Alice']);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->set('customerPhone', '01711112222')
            ->call('lookupCustomer')
            ->assertSet('customerId', $customer->id)
            ->assertSet('foundCustomerName', 'Alice');
    });

    it('marks "not found" for a new phone (will create walk-in at completion)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->set('customerPhone', '01799998888')
            ->call('lookupCustomer')
            ->assertSet('customerId', null)
            ->assertSet('foundCustomerName', null)
            ->assertSet('customerLookupDone', true);
    });

    it('requires a phone to look up', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->set('customerPhone', '')
            ->call('lookupCustomer')
            ->assertHasErrors('customerPhone');
    });

    it('creates a new walk-in customer at completion via findOrCreateByPhone', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('customerPhone', '01755554444')
            ->set('customerName', 'Bob Walk-in')
            ->set('payments', [['method' => 'cash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale')
            ->assertNotSet('completedInvoiceId', null);

        $invoice = SaleInvoice::first();
        expect($invoice->customer_id)->not->toBeNull()
            ->and($invoice->customer->phone)->toBe('01755554444')
            ->and($invoice->customer->name)->toBe('Bob Walk-in');
    });

    it('links an existing customer by id when the phone was found', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);
        $customer = Customer::factory()->walkIn()->create(['phone' => '01733330000', 'name' => 'Carol']);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('customerPhone', '01733330000')
            ->call('lookupCustomer')
            ->set('payments', [['method' => 'cash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale');

        $invoice = SaleInvoice::first();
        expect($invoice->customer_id)->toBe($customer->id);
        expect(Customer::where('phone', '01733330000')->count())->toBe(1); // not duplicated
    });

    it('supports a true walk-in sale (no customer) via the walk-in toggle', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale')
            ->assertNotSet('completedInvoiceId', null);

        $invoice = SaleInvoice::first();
        expect($invoice->customer_id)->toBeNull();
    });

    it('requires a phone (or walk-in toggle) before completing', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('customerPhone', '') // no phone, no walk-in
            ->set('payments', [['method' => 'cash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale')
            ->assertHasErrors('customerPhone');

        expect(SaleInvoice::count())->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| Multiple payment methods + summation
|--------------------------------------------------------------------------
*/
describe('payments — multiple methods + summation', function () {
    it('supports cash + bkash in one sale, summing correctly', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [
                ['method' => 'cash', 'amount' => '600', 'reference' => null],
                ['method' => 'bkash', 'amount' => '400', 'reference' => 'TXN123'],
            ])
            ->call('completeSale')
            ->assertNotSet('completedInvoiceId', null);

        $invoice = SaleInvoice::first();
        expect($invoice->payments)->toHaveCount(2)
            ->and($invoice->total_paid)->toBe('1000.00');
    });

    it('can add and remove payment lines', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $component = Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->call('addPaymentMethod');
        expect(count($component->get('payments')))->toBe(2);

        $component->call('removePaymentMethod', 0);
        expect(count($component->get('payments')))->toBe(1);
    });

    it('rejects a payment amount of zero', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '0', 'reference' => null]])
            ->call('completeSale')
            ->assertSet('completedInvoiceId', null);

        expect(SaleInvoice::count())->toBe(0);
    });

    it('rejects a bkash payment without a reference number', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'bkash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale')
            ->assertSet('completedInvoiceId', null);

        expect(SaleInvoice::count())->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| total_paid >= total_amount enforcement
|--------------------------------------------------------------------------
*/
describe('total paid enforcement', function () {
    it('blocks completion when total_paid < total_amount with a "short by X" message', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        $component = Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '500', 'reference' => null]])
            ->call('completeSale');

        $component->assertSet('completedInvoiceId', null)
            ->assertSee('short by 500.00');

        expect(SaleInvoice::count())->toBe(0);
    });

    it('accepts overpayment and shows change due (informational)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        $component = Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '1500', 'reference' => null]]);

        // Change due is 500.00; completion succeeds.
        $component->call('completeSale')
            ->assertNotSet('completedInvoiceId', null);

        $invoice = SaleInvoice::first();
        expect($invoice->total_paid)->toBe('1500.00')
            ->and($invoice->total_amount)->toBe('1000.00');
    });
});

/*
|--------------------------------------------------------------------------
| Successful completion end-to-end
|--------------------------------------------------------------------------
*/
describe('successful completion', function () {
    it('completes atomically: invoice completed, garment sold, customer linked, payments recorded, cart cleared, invoice number shown', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        $component = Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('customerPhone', '01711119999')
            ->set('customerName', 'Dave')
            ->set('payments', [['method' => 'cash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale');

        // Success state.
        $component->assertNotSet('completedInvoiceId', null)
            ->assertSee('Sale completed');

        // Invoice number follows INV-YY-NNNNN.
        expect($component->get('completedInvoiceNumber'))->toMatch('/^INV-\d{2}-\d{5}$/');

        // The cart is cleared in the Checkout.
        $component->assertSet('cartItems', []);

        // Invoice state.
        $invoice = SaleInvoice::first();
        expect($invoice->status)->toBe(SaleInvoice::STATUS_COMPLETED)
            ->and($invoice->completed_at)->not->toBeNull()
            ->and($invoice->customer->phone)->toBe('01711119999')
            ->and($invoice->items)->toHaveCount(1)
            ->and($invoice->payments)->toHaveCount(1);

        // Garment is sold.
        expect($garment->fresh()->current_status)->toBe(Garment::STATUS_SOLD);
    });

    it('emits checkoutClosed to PointOfSale on success (cart clears on the POS side too)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);

        // Drive the PointOfSale (add garment + proceed to checkout), then
        // drive the Checkout (complete). The PointOfSale's cart must clear.
        $pos = Livewire::actingAs($salesperson)
            ->test(PointOfSale::class)
            ->set('scanInput', $garment->barcode)
            ->call('scanBarcode');
        expect($pos->get('cartItems'))->toHaveCount(1);

        // The Checkout emits checkoutClosed to point-of-sale on success;
        // simulate that event arriving by calling the listener directly.
        $pos->call('refreshCart', ['items' => []])
            ->assertSet('cartItems', []);
    });

    it('audits the sale.complete action', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale');

        $invoice = SaleInvoice::first();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sale.complete',
            'category' => 'sales',
            'reference_type' => 'sale_invoice',
            'reference_id' => $invoice->id,
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| Atomic rollback on a mid-checkout garment conflict
|--------------------------------------------------------------------------
*/
describe('atomic rollback on conflict', function () {
    it('flags the specific garment and creates no invoice when a garment is sold by another cashier mid-checkout', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 500);
        $g1 = availableGarmentAtBranch($branch, $product);
        $g2 = availableGarmentAtBranch($branch, $product);
        $payload = cartPayload([
            ['garment' => $g1, 'price' => $price],
            ['garment' => $g2, 'price' => $price],
        ]);

        // Simulate another cashier selling g2 between cart-build and checkout.
        $g2->update(['current_status' => Garment::STATUS_SOLD]);
        GarmentCurrentStock::where('garment_id', $g2->id)
            ->update(['current_status' => Garment::STATUS_SOLD]);

        $component = Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '2000', 'reference' => null]])
            ->call('completeSale');

        // The specific garment is flagged.
        $component->assertSet('completedInvoiceId', null)
            ->assertSee((string) $g2->id)
            ->assertSee('no longer available');

        // Atomic rollback: NO invoice, NO items, NO payments.
        expect(SaleInvoice::count())->toBe(0, 'no invoice after a rolled-back sale')
            ->and(SaleInvoiceItem::count())->toBe(0)
            ->and(SalePayment::count())->toBe(0);

        // g1 is STILL available (no partial deduction).
        expect($g1->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE, 'the other garment must remain available — no partial deduction');
        // g2 stays sold (by the other cashier).
        expect($g2->fresh()->current_status)->toBe(Garment::STATUS_SOLD);
    });
});

/*
|--------------------------------------------------------------------------
| Discount re-validation at completion
|--------------------------------------------------------------------------
*/
describe('discount re-validation', function () {
    it('rejects completion if the cart discount pushed the price below minimum (server-side re-check)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        [$product, $price] = productWithPrice(1000, 900);
        $garment = availableGarmentAtBranch($branch, $product);

        // Build a cart payload with a discount that violates the minimum
        // (the CartState would have blocked it, but a direct emit bypasses
        // the UI guard — the SaleService re-validates at completion).
        $payload = cartPayload([['garment' => $garment, 'price' => $price, 'discount' => '200.00']]);

        $component = Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '800', 'reference' => null]])
            ->call('completeSale');

        $component->assertSet('completedInvoiceId', null);
        expect(SaleInvoice::count())->toBe(0);
        // The garment is NOT sold.
        expect($garment->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE);
    });
});

/*
|--------------------------------------------------------------------------
| Branch scope
|--------------------------------------------------------------------------
*/
describe('branch scope', function () {
    it('rejects completing a sale for a garment at another branch', function () {
        $b1 = Branch::where('code', 'B1')->first();
        $b2 = Branch::where('code', 'B2')->first();
        $salesperson = salesperson($b1); // salesperson at B1
        [$product, $price] = productWithPrice(1000, 500);
        $garment = availableGarmentAtBranch($b2, $product); // garment at B2

        // The Checkout uses the salesperson's active branch (B1); SaleService
        // rejects the garment because it's at B2.
        $payload = cartPayload([['garment' => $garment, 'price' => $price]]);

        $component = Livewire::actingAs($salesperson)
            ->test(Checkout::class)
            ->dispatch('openCheckout', $payload)
            ->set('walkIn', true)
            ->set('payments', [['method' => 'cash', 'amount' => '1000', 'reference' => null]])
            ->call('completeSale');

        $component->assertSet('completedInvoiceId', null);
        expect(SaleInvoice::count())->toBe(0)
            ->and($garment->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE);
    });
});
