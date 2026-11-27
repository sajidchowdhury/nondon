<?php

use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchaseItem;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Catalog\Models\Product;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
});

/**
 * Helper: create a draft purchase via the service for UI tests.
 */
function createDraftPurchaseForUi(): Purchase
{
    $admin = adminUser();
    test()->actingAs($admin);

    $supplier = Supplier::factory()->create();
    $fiscalYear = FiscalYear::active()->first();
    $product = Product::factory()->create();

    return app(PurchasingService::class)->createPurchase([
        'supplier_id' => $supplier->id,
        'fiscal_year_id' => $fiscalYear->id,
        'purchase_date' => '2025-08-15',
        'items' => [
            ['product_id' => $product->id, 'ordered_quantity' => 10, 'unit_cost' => 250.00],
        ],
    ]);
}

describe('purchase list — UI access', function () {
    it('allows admin to view purchase list', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('purchases.index'));
        $response->assertStatus(200);
        $response->assertSee('Purchases');
    });

    it('denies supervisor access to purchase list', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $response = $this->actingAs($supervisor)->get(route('purchases.index'));
        $response->assertForbidden();
    });

    it('denies salesperson access to purchase list', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $response = $this->actingAs($salesperson)->get(route('purchases.index'));
        $response->assertForbidden();
    });

    it('redirects guest to login', function () {
        $response = $this->get(route('purchases.index'));
        $response->assertRedirect(route('login'));
    });
});

describe('purchase create — UI access', function () {
    it('allows admin to view create form', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('purchases.create'));
        $response->assertStatus(200);
        $response->assertSee('Add Purchase');
    });

    it('denies supervisor access to create form', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $response = $this->actingAs($supervisor)->get(route('purchases.create'));
        $response->assertForbidden();
    });

    it('shows supplier and fiscal year selectors on create form', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('purchases.create'));
        $response->assertStatus(200);
        $response->assertSee('Select supplier');
        $response->assertSee('Fiscal Year');
        $response->assertSee('Line Items');
    });

    it('shows unit cost fields for admin', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('purchases.create'));
        $response->assertSee('Unit Cost');
        $response->assertSee('Grand Total');
    });
});

describe('purchase view — UI access', function () {
    it('allows admin to view purchase detail', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.show', $purchase));
        $response->assertStatus(200);
        $response->assertSee($purchase->purchase_number);
    });

    it('denies supervisor access to purchase detail', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($supervisor)->get(route('purchases.show', $purchase));
        $response->assertForbidden();
    });

    it('shows line items with ordered and received quantities', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.show', $purchase));
        $response->assertSee('Ordered');
        $response->assertSee('Received');
        $response->assertSee('Remaining');
    });

    it('shows total cost for admin on view', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.show', $purchase));
        $response->assertSee('Total Cost');
    });

    it('shows edit button for draft purchases', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.show', $purchase));
        $response->assertSee('Edit');
    });
});

describe('purchase edit — UI access', function () {
    it('allows admin to view edit form for draft purchase', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.edit', $purchase));
        $response->assertStatus(200);
        $response->assertSee('Edit Purchase');
    });

    it('denies supervisor access to edit form', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($supervisor)->get(route('purchases.edit', $purchase));
        $response->assertForbidden();
    });

    it('shows confirm button for draft purchase', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.edit', $purchase));
        $response->assertSee('Confirm Purchase');
    });

    it('shows cancel button for draft purchase', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.edit', $purchase));
        $response->assertSee('Cancel Purchase');
    });

    it('shows read-only items for confirmed purchase (no edit)', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        $purchase = createDraftPurchaseForUi();
        app(PurchasingService::class)->confirmPurchase($purchase);

        $response = $this->actingAs($admin)->get(route('purchases.edit', $purchase->fresh()));
        $response->assertStatus(200);
        // Should show the warning about non-editable status
        $response->assertSee('cannot be edited');
    });
});

describe('purchase list — filtering', function () {
    it('purchase list filters work by status', function () {
        $admin = adminUser();
        $this->actingAs($admin);

        Purchase::factory()->draft()->create();
        Purchase::factory()->confirmed()->create();
        Purchase::factory()->cancelled()->create();

        $response = $this->actingAs($admin)->get(route('purchases.index'));
        $response->assertStatus(200);
        $response->assertSee('Draft');
        $response->assertSee('Confirmed');
        $response->assertSee('Cancelled');
    });

    it('purchase list shows correct status badges', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.index'));
        $response->assertSee('Draft');
    });

    it('purchase list shows purchase number', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.index'));
        $response->assertSee($purchase->purchase_number);
    });
});

describe('cost visibility in UI', function () {
    it('purchase cost fields are not rendered for non-admin roles', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $supervisor = supervisor($branch);

        // Supervisor gets 403 — never sees the page at all
        $response = $this->actingAs($supervisor)->get(route('purchases.index'));
        $response->assertForbidden();
        $response->assertDontSee('Total Cost');
    });

    it('admin sees total cost column in list', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.index'));
        $response->assertSee('Total Cost');
    });

    it('admin sees unit cost in view', function () {
        $admin = adminUser();
        $purchase = createDraftPurchaseForUi();

        $response = $this->actingAs($admin)->get(route('purchases.show', $purchase));
        $response->assertSee('Unit Cost');
        $response->assertSee('Grand Total');
    });
});

describe('purchase management routes are protected', function () {
    it('all purchase routes deny salesperson', function () {
        $branch = \App\Modules\Organization\Models\Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $purchase = createDraftPurchaseForUi();

        $this->actingAs($salesperson)->get(route('purchases.index'))->assertForbidden();
        $this->actingAs($salesperson)->get(route('purchases.create'))->assertForbidden();
        $this->actingAs($salesperson)->get(route('purchases.show', $purchase))->assertForbidden();
        $this->actingAs($salesperson)->get(route('purchases.edit', $purchase))->assertForbidden();
    });

    it('all purchase routes deny online team', function () {
        $user = \App\Modules\Identity\Models\User::factory()->onlineChannel()->create();
        $user->assignRole('online_team');
        $purchase = createDraftPurchaseForUi();

        $this->actingAs($user)->get(route('purchases.index'))->assertForbidden();
        $this->actingAs($user)->get(route('purchases.create'))->assertForbidden();
    });
});
