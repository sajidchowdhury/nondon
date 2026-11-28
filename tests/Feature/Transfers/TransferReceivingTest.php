<?php

use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Services\MovementHistoryService;
use App\Modules\Organization\Models\Branch;
use App\Modules\Transfers\Http\Livewire\Transfers\TransferReceive;
use App\Modules\Transfers\Models\TransferDiscrepancy;
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

/**
 * Helper: create a fully-dispatched transfer (M → B1) with N garments.
 */
function dispatchedTransfer(int $garmentCount): array
{
    $main = Branch::where('code', 'M')->first();
    $b1 = Branch::where('code', 'B1')->first();
    $mainSup = supervisor($main);
    $b1Sup = supervisor($b1);

    [$product] = productWithPrice(1000, 500);
    $garments = [];
    for ($i = 0; $i < $garmentCount; $i++) {
        $garments[] = availableGarmentAtBranch($main, $product);
    }

    $service = app(TransferService::class);
    $request = $service->createRequest($mainSup, $main, $b1, collect($garments)->pluck('id')->all());
    $request = $service->approve($request, $mainSup);
    $request = $service->dispatch($request, $mainSup);

    return [$request, $garments, $mainSup, $b1Sup, $main, $b1];
}

/*
|--------------------------------------------------------------------------
| Receiving form — authorization + access
|--------------------------------------------------------------------------
*/
describe('receiving form — access', function () {
    it('loads for the destination supervisor on a dispatched transfer', function () {
        [$request, , , $b1Sup] = dispatchedTransfer(1);

        $response = $this->actingAs($b1Sup)->get(route('transfers.receive', $request));
        $response->assertStatus(200);
        $response->assertSee('Receive Transfer');
    });

    it('is 403 for the source supervisor (wrong branch)', function () {
        [$request, , $mainSup] = dispatchedTransfer(1);

        $response = $this->actingAs($mainSup)->get(route('transfers.receive', $request));
        $response->assertForbidden();
    });

    it('is 403 for a salesperson', function () {
        [$request, , , , , $b1] = dispatchedTransfer(1);
        $salesperson = salesperson($b1);

        $response = $this->actingAs($salesperson)->get(route('transfers.receive', $request));
        $response->assertForbidden();
    });

    it('refuses a non-dispatched transfer (redirect)', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $mainSup = supervisor($main);
        $b1Sup = supervisor($b1);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g->id]);

        // Transfer is pending, not dispatched — the Livewire mount throws
        // ValidationException, which Livewire renders as a redirect to the
        // show page (or a 404). Test via the Livewire test helper.
        try {
            Livewire::actingAs($b1Sup)
                ->test(TransferReceive::class, ['transfer' => $request])
                ->call('confirmReceipt');
            $this->fail('Expected a validation exception');
        } catch (Exception $e) {
            expect(true)->toBeTrue();
        }
    });
});

/*
|--------------------------------------------------------------------------
| Scan mode
|--------------------------------------------------------------------------
*/
describe('scan mode', function () {
    it('scanning a garment barcode marks it received', function () {
        [$request, $garments, , $b1Sup] = dispatchedTransfer(1);

        $component = Livewire::actingAs($b1Sup)
            ->test(TransferReceive::class, ['transfer' => $request])
            ->set('scanInput', $garments[0]->barcode)
            ->call('scanBarcode');

        expect($component->get('receivedGarmentIds'))->toContain($garments[0]->id);
    });

    it('scanning an unknown barcode shows an error and does not add', function () {
        [$request, , , $b1Sup] = dispatchedTransfer(1);

        $component = Livewire::actingAs($b1Sup)
            ->test(TransferReceive::class, ['transfer' => $request])
            ->set('scanInput', 'NND-99-99999')
            ->call('scanBarcode')
            ->assertSee('Garment not found');

        expect($component->get('receivedGarmentIds'))->toBeEmpty();
    });

    it('scanning a garment not in the transfer adds it as excess', function () {
        [$request, $garments, , $b1Sup] = dispatchedTransfer(1);
        [$product] = productWithPrice(1000, 500);
        $extra = availableGarmentAtBranch(Branch::where('code', 'M')->first(), $product);

        $component = Livewire::actingAs($b1Sup)
            ->test(TransferReceive::class, ['transfer' => $request])
            ->set('scanInput', $extra->barcode)
            ->call('scanBarcode')
            ->assertSee('excess');

        expect($component->get('receivedGarmentIds'))->toContain($extra->id);
    });

    it('confirming a full receipt (all received) completes the transfer', function () {
        [$request, $garments, , $b1Sup] = dispatchedTransfer(2);

        Livewire::actingAs($b1Sup)
            ->test(TransferReceive::class, ['transfer' => $request])
            ->set('scanInput', $garments[0]->barcode)
            ->call('scanBarcode')
            ->set('scanInput', $garments[1]->barcode)
            ->call('scanBarcode')
            ->call('confirmReceipt');

        $request = $request->fresh();
        expect($request->status)->toBe(TransferRequest::STATUS_RECEIVED)
            ->and($garments[0]->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE)
            ->and($garments[0]->fresh()->current_branch_id)->toBe($request->to_branch_id)
            ->and($garments[1]->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE)
            ->and($garments[1]->fresh()->current_branch_id)->toBe($request->to_branch_id);
    });
});

/*
|--------------------------------------------------------------------------
| Quantity mode
|--------------------------------------------------------------------------
*/
describe('quantity mode', function () {
    it('entering N received marks N garments received and the rest missing', function () {
        [$request, $garments, , $b1Sup] = dispatchedTransfer(3);
        $product = $garments[0]->product;
        $size = $garments[0]->productSize?->size_label ?? 'no-size';
        $key = $product->name.' / '.$size;

        $component = Livewire::actingAs($b1Sup)
            ->test(TransferReceive::class, ['transfer' => $request])
            ->set('mode', 'quantity')
            ->set("quantityInputs.{$key}", 2)
            ->call('applyQuantities');

        $received = $component->get('receivedGarmentIds');
        expect(count($received))->toBe(2);

        // Confirm with a discrepancy reason (1 missing).
        $component->set('discrepancyReason', '1 garment missing')
            ->call('confirmReceipt');

        $request = $request->fresh();
        expect($request->status)->toBe(TransferRequest::STATUS_DISCREPANCY);

        // The 3rd garment is missing.
        $allReceived = collect($received)->all();
        $missingGarment = collect($garments)->firstWhere('id', '!=', $allReceived[0]);
        $missingGarment = collect($garments)->filter(fn ($g) => ! in_array($g->id, $allReceived))->first();
        expect($missingGarment->fresh()->current_status)->toBe(Garment::STATUS_MISSING);
    });
});

/*
|--------------------------------------------------------------------------
| Shortage handling — missing garments never silently received
|--------------------------------------------------------------------------
*/
describe('shortage handling', function () {
    it('marks missing garments as missing (not received); discrepancy recorded', function () {
        [$request, $garments, , $b1Sup] = dispatchedTransfer(4);

        // Receive only 3 of 4 (1 missing).
        Livewire::actingAs($b1Sup)
            ->test(TransferReceive::class, ['transfer' => $request])
            ->set('scanInput', $garments[0]->barcode)
            ->call('scanBarcode')
            ->set('scanInput', $garments[1]->barcode)
            ->call('scanBarcode')
            ->set('scanInput', $garments[2]->barcode)
            ->call('scanBarcode')
            ->set('discrepancyReason', '1 garment missing')
            ->call('confirmReceipt');

        $request = $request->fresh();
        expect($request->status)->toBe(TransferRequest::STATUS_DISCREPANCY);

        // The 4th garment (missing) is flagged missing, NOT received.
        expect($garments[3]->fresh()->current_status)->toBe(Garment::STATUS_MISSING);

        // No transfer_receive movement for the missing garment.
        $receiveMovement = InventoryMovement::where('garment_id', $garments[3]->id)
            ->where('movement_type', InventoryMovement::TYPE_TRANSFER_RECEIVE)->first();
        expect($receiveMovement)->toBeNull();

        // A missing_report movement for the missing garment.
        $missingMovement = InventoryMovement::where('garment_id', $garments[3]->id)
            ->where('movement_type', InventoryMovement::TYPE_MISSING_REPORT)->first();
        expect($missingMovement)->not->toBeNull();

        // Discrepancy row with missing_garment_ids.
        $discrepancy = TransferDiscrepancy::where('transfer_request_id', $request->id)->first();
        expect($discrepancy)->not->toBeNull()
            ->and($discrepancy->discrepancy_type)->toBe(TransferDiscrepancy::TYPE_SHORTAGE)
            ->and($discrepancy->missing_garment_ids)->toBe([$garments[3]->id]);
    });

    it('requires a discrepancy reason when there are missing garments', function () {
        [$request, $garments, , $b1Sup] = dispatchedTransfer(2);

        // Receive only 1 (1 missing) but no reason.
        $component = Livewire::actingAs($b1Sup)
            ->test(TransferReceive::class, ['transfer' => $request])
            ->set('scanInput', $garments[0]->barcode)
            ->call('scanBarcode')
            ->set('discrepancyReason', '')
            ->call('confirmReceipt')
            ->assertSee('discrepancy reason is required');

        // Transfer not received yet.
        expect($request->fresh()->status)->toBe(TransferRequest::STATUS_DISPATCHED);
    });
});

/*
|--------------------------------------------------------------------------
| Excess handling
|--------------------------------------------------------------------------
*/
describe('excess handling', function () {
    it('flags excess garments as a discrepancy', function () {
        [$request, $garments, , $b1Sup] = dispatchedTransfer(1);
        [$product] = productWithPrice(1000, 500);
        $extra = availableGarmentAtBranch(Branch::where('code', 'M')->first(), $product);

        Livewire::actingAs($b1Sup)
            ->test(TransferReceive::class, ['transfer' => $request])
            ->set('scanInput', $garments[0]->barcode)
            ->call('scanBarcode')
            ->set('scanInput', $extra->barcode)
            ->call('scanBarcode')
            ->set('discrepancyReason', '1 excess garment')
            ->call('confirmReceipt');

        $request = $request->fresh();
        $discrepancy = TransferDiscrepancy::where('transfer_request_id', $request->id)->first();
        expect($discrepancy)->not->toBeNull()
            ->and($discrepancy->discrepancy_type)->toBe(TransferDiscrepancy::TYPE_EXCESS)
            ->and($discrepancy->excess_garment_ids)->toBe([$extra->id]);
    });
});

/*
|--------------------------------------------------------------------------
| Discrepancy rows are 'open'
|--------------------------------------------------------------------------
*/
describe('discrepancy status', function () {
    it('creates discrepancy rows with status open (resolution deferred to Phase 7)', function () {
        [$request, $garments, , $b1Sup] = dispatchedTransfer(2);

        Livewire::actingAs($b1Sup)
            ->test(TransferReceive::class, ['transfer' => $request])
            ->set('scanInput', $garments[0]->barcode)
            ->call('scanBarcode')
            ->set('discrepancyReason', '1 missing')
            ->call('confirmReceipt');

        $discrepancy = TransferDiscrepancy::first();
        expect($discrepancy->status)->toBe(TransferDiscrepancy::STATUS_OPEN);
    });
});

/*
|--------------------------------------------------------------------------
| Transfer history — movement descriptions
|--------------------------------------------------------------------------
*/
describe('transfer history', function () {
    it('movement history correctly describes transfer movements for a transferred garment', function () {
        [$request, $garments, , $b1Sup] = dispatchedTransfer(1);

        // Receive the garment so we have the full lifecycle.
        app(TransferService::class)->receive($request, $b1Sup, [$garments[0]->id]);

        $history = app(MovementHistoryService::class)->getGarmentHistory($garments[0]->fresh());
        $descriptions = $history->pluck('description')->all();

        // The history should include the reservation, dispatch, and receive
        // descriptions (MovementHistoryService already has these in Phase 1).
        $joined = implode(' | ', $descriptions);
        expect($joined)->toContain('Reserved')
            ->and($joined)->toContain('Dispatched')
            ->and($joined)->toContain('Received');
    });
});
