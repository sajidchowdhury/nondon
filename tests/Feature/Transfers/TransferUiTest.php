<?php

use App\Modules\Inventory\Models\Garment;
use App\Modules\Organization\Models\Branch;
use App\Modules\Transfers\Http\Livewire\Transfers\TransferCreate;
use App\Modules\Transfers\Http\Livewire\Transfers\TransferView;
use App\Modules\Transfers\Models\TransferRequest;
use App\Modules\Transfers\Services\TransferService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Transfer list — authorization + scoping
|--------------------------------------------------------------------------
*/
describe('transfer list', function () {
    it('loads for a supervisor', function () {
        $branch = Branch::where('code', 'M')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('transfers.index'));
        $response->assertStatus(200);
        $response->assertSee('Transfers');
    });

    it('loads for admin', function () {
        $admin = adminUser();
        $response = $this->actingAs($admin)->get(route('transfers.index'));
        $response->assertStatus(200);
    });

    it('is 403 for a salesperson (no transfer permissions)', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('transfers.index'));
        $response->assertForbidden();
    });

    it('shows transfers involving the user\'s branch', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $mainSup = supervisor($main);
        $b1Sup = supervisor($b1);

        // Create a transfer M → B1 as M's supervisor.
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g->id]);

        // M's supervisor sees it in the list.
        $response = $this->actingAs($mainSup)->get(route('transfers.index'));
        $response->assertStatus(200);
        $response->assertSee($request->request_number);

        // B1's supervisor also sees it (incoming).
        $response = $this->actingAs($b1Sup)->get(route('transfers.index'));
        $response->assertStatus(200);
        $response->assertSee($request->request_number);
    });

    it('redirects a guest to login', function () {
        $response = $this->get(route('transfers.index'));
        $response->assertRedirect(route('login'));
    });
});

/*
|--------------------------------------------------------------------------
| Transfer create — authorization + cross-branch picker
|--------------------------------------------------------------------------
*/
describe('transfer create — authorization', function () {
    it('loads for a supervisor with transfer.request.create', function () {
        $branch = Branch::where('code', 'M')->first();
        $supervisor = supervisor($branch);

        $response = $this->actingAs($supervisor)->get(route('transfers.create'));
        $response->assertStatus(200);
        $response->assertSee('New Transfer');
    });

    it('is 403 for a salesperson', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);

        $response = $this->actingAs($salesperson)->get(route('transfers.create'));
        $response->assertForbidden();
    });
});

describe('transfer create — cross-branch stock picker', function () {
    it('searchStock returns only available garments at the source branch', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $atMain = availableGarmentAtBranch($main, $product);
        $atB1 = availableGarmentAtBranch($b1, $product);
        $sold = availableGarmentAtBranch($main, $product);
        $sold->update(['current_status' => Garment::STATUS_SOLD]);

        $component = Livewire::actingAs($supervisor)
            ->test(TransferCreate::class)
            ->set('fromBranchId', $main->id)
            ->set('productSearch', $product->name)
            ->call('searchStock');

        $ids = collect($component->get('searchResults'))->pluck('garment_id');
        expect($ids)->toContain($atMain->id)
            ->and($ids)->not->toContain($atB1->id)
            ->and($ids)->not->toContain($sold->id);
    });

    it('does not expose purchase_cost in search results', function () {
        $main = Branch::where('code', 'M')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $g->update(['purchase_cost' => 250.00]);

        $component = Livewire::actingAs($supervisor)
            ->test(TransferCreate::class)
            ->set('fromBranchId', $main->id)
            ->set('productSearch', $product->name)
            ->call('searchStock');

        $results = $component->get('searchResults');
        foreach ($results as $row) {
            expect($row)->not->toHaveKey('purchase_cost');
        }
    });

    it('requires a source branch before searching', function () {
        $main = Branch::where('code', 'M')->first();
        $supervisor = supervisor($main);

        $component = Livewire::actingAs($supervisor)
            ->test(TransferCreate::class)
            ->set('fromBranchId', 0)
            ->set('productSearch', 'test')
            ->call('searchStock');

        $component->assertSet('searchResults', [])
            ->assertSee('Select a source branch');
    });
});

describe('transfer create — submit', function () {
    it('creates a transfer request via the UI', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        Livewire::actingAs($supervisor)
            ->test(TransferCreate::class)
            ->set('fromBranchId', $main->id)
            ->set('toBranchId', $b1->id)
            ->set('selectedGarmentIds', [$g->id])
            ->set('notes', 'Test transfer')
            ->call('submit')
            ->assertHasNoErrors();

        expect(TransferRequest::count())->toBe(1);
        expect($g->fresh()->current_status)->toBe(Garment::STATUS_RESERVED);
    });

    it('rejects when source and destination are the same', function () {
        $main = Branch::where('code', 'M')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        Livewire::actingAs($supervisor)
            ->test(TransferCreate::class)
            ->set('fromBranchId', $main->id)
            ->set('toBranchId', $main->id)
            ->set('selectedGarmentIds', [$g->id])
            ->call('submit')
            ->assertHasErrors(['branches']);

        expect(TransferRequest::count())->toBe(0);
    });

    it('rejects when no garments are selected', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);

        Livewire::actingAs($supervisor)
            ->test(TransferCreate::class)
            ->set('fromBranchId', $main->id)
            ->set('toBranchId', $b1->id)
            ->set('selectedGarmentIds', [])
            ->call('submit')
            ->assertHasErrors(['garments']);
    });
});

/*
|--------------------------------------------------------------------------
| Transfer view — approve / reject / dispatch
|--------------------------------------------------------------------------
*/
describe('transfer view — approve / reject / dispatch', function () {
    it('source supervisor can approve a pending request via the UI', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);

        Livewire::actingAs($supervisor)
            ->test(TransferView::class, ['transfer' => $request])
            ->call('approve')
            ->assertSee('Transfer approved');

        expect($request->fresh()->status)->toBe(TransferRequest::STATUS_APPROVED)
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_RESERVED);
    });

    it('source supervisor can reject with a reason (releases reservations)', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);

        Livewire::actingAs($supervisor)
            ->test(TransferView::class, ['transfer' => $request])
            ->set('rejectReason', 'Wrong branch')
            ->call('reject')
            ->assertSee('Transfer rejected');

        expect($request->fresh()->status)->toBe(TransferRequest::STATUS_REJECTED)
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE);
    });

    it('reject requires a reason', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);

        Livewire::actingAs($supervisor)
            ->test(TransferView::class, ['transfer' => $request])
            ->set('rejectReason', '')
            ->call('reject')
            ->assertHasErrors(['rejectReason']);

        expect($request->fresh()->status)->toBe(TransferRequest::STATUS_PENDING);
    });

    it('source supervisor can dispatch an approved request via the UI', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->approve($request, $supervisor);

        Livewire::actingAs($supervisor)
            ->test(TransferView::class, ['transfer' => $request])
            ->call('dispatchTransfer')
            ->assertSee('Transfer dispatched');

        expect($request->fresh()->status)->toBe(TransferRequest::STATUS_DISPATCHED)
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_IN_TRANSIT);
    });

    it('destination supervisor cannot approve (wrong branch)', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $mainSup = supervisor($main);
        $b1Sup = supervisor($b1);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g->id]);

        Livewire::actingAs($b1Sup)
            ->test(TransferView::class, ['transfer' => $request])
            ->call('approve')
            ->assertSee('do not have permission');

        expect($request->fresh()->status)->toBe(TransferRequest::STATUS_PENDING);
    });

    it('a salesperson can view an involved transfer but cannot approve (action denied)', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $mainSup = supervisor($main);
        $salesperson = salesperson($b1); // salesperson at B1 (the destination)
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g->id]);

        // The salesperson can view the transfer (B1 is involved).
        $response = $this->actingAs($salesperson)->get(route('transfers.show', $request));
        $response->assertStatus(200);

        // But cannot approve via the Livewire action (no transfer.approve permission).
        Livewire::actingAs($salesperson)
            ->test(TransferView::class, ['transfer' => $request])
            ->call('approve')
            ->assertSee('do not have permission');

        expect($request->fresh()->status)->toBe(TransferRequest::STATUS_PENDING);
    });

    it('cancels a pending request via the UI (requester)', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);

        Livewire::actingAs($supervisor)
            ->test(TransferView::class, ['transfer' => $request])
            ->set('cancelReason', 'Not needed')
            ->call('cancel')
            ->assertSee('Transfer cancelled');

        expect($request->fresh()->status)->toBe(TransferRequest::STATUS_CANCELLED)
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE);
    });
});

/*
|--------------------------------------------------------------------------
| Dispatch note
|--------------------------------------------------------------------------
*/
describe('dispatch note', function () {
    it('renders the dispatch note for a dispatched transfer', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->approve($request, $supervisor);
        $request = app(TransferService::class)->dispatch($request, $supervisor);

        $response = $this->actingAs($supervisor)->get(route('transfers.dispatch-note', $request));
        $response->assertStatus(200);
        $response->assertSee('Dispatch Note');
        $response->assertSee($request->request_number);
        $response->assertSee($g->barcode);
    });

    it('is 403 for a user not involved in the transfer', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $b2 = Branch::where('code', 'B2')->first();
        $mainSup = supervisor($main);
        $b2Sup = supervisor($b2);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->approve($request, $mainSup);
        $request = app(TransferService::class)->dispatch($request, $mainSup);

        // B2 supervisor is not involved (M→B1, not B2).
        $response = $this->actingAs($b2Sup)->get(route('transfers.dispatch-note', $request));
        $response->assertForbidden();
    });
});

/*
|--------------------------------------------------------------------------
| Online supervisor
|--------------------------------------------------------------------------
*/
describe('online supervisor', function () {
    it('can create a request TO the online channel', function () {
        $main = Branch::where('code', 'M')->first();
        $online = Branch::where('code', 'ONLINE')->first();
        $onlineSup = onlineSupervisor();
        // The online supervisor needs transfer.request.create to create
        // transfers sourcing stock to the online channel. Grant it for this
        // test (the RolePermissionSeeder will be updated when the online
        // orders module lands in Phase 4).
        $onlineSup->givePermissionTo('transfer.request.create');

        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        Livewire::actingAs($onlineSup)
            ->test(TransferCreate::class)
            ->set('fromBranchId', $main->id)
            ->set('toBranchId', $online->id)
            ->set('selectedGarmentIds', [$g->id])
            ->call('submit')
            ->assertHasNoErrors();

        expect(TransferRequest::count())->toBe(1);
        expect($g->fresh()->current_status)->toBe(Garment::STATUS_RESERVED);
    });
});

/*
|--------------------------------------------------------------------------
| Navigation + sensitive data
|--------------------------------------------------------------------------
*/
describe('navigation + sensitive data', function () {
    it('shows the Transfers nav link to a supervisor', function () {
        $branch = Branch::where('code', 'M')->first();
        $supervisor = supervisor($branch);
        $response = $this->actingAs($supervisor)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Transfers');
    });

    it('does not show the Transfers nav link to a salesperson', function () {
        $branch = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($branch);
        $response = $this->actingAs($salesperson)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('>Transfers<');
    });

    it('does not expose purchase_cost in the transfer view', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $g->update(['purchase_cost' => 250.00]);
        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);

        $html = $this->actingAs($supervisor)->get(route('transfers.show', $request))->getContent();
        expect($html)->not->toContain('250.00')
            ->and($html)->not->toContain('purchase_cost');
    });
});
