<?php

use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\GarmentCurrentStock;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Organization\Models\Branch;
use App\Modules\Sales\Exceptions\GarmentNotAvailableException;
use App\Modules\Sales\Models\SaleInvoice;
use App\Modules\Sales\Services\SaleService;
use App\Modules\Transfers\Events\TransferDispatched;
use App\Modules\Transfers\Events\TransferReceived;
use App\Modules\Transfers\Events\TransferRequested;
use App\Modules\Transfers\Models\TransferDiscrepancy;
use App\Modules\Transfers\Models\TransferRequest;
use App\Modules\Transfers\Services\TransferService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\FiscalYearSeeder;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(BranchSeeder::class);
    $this->seed(FiscalYearSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| createRequest — implicit reservation
|--------------------------------------------------------------------------
*/
describe('createRequest — reservation', function () {
    it('reserves all garments and writes a reservation movement per garment', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);

        [$product] = productWithPrice(1000, 500);
        $g1 = availableGarmentAtBranch($main, $product);
        $g2 = availableGarmentAtBranch($main, $product);

        $service = app(TransferService::class);
        $request = $service->createRequest($supervisor, $main, $b1, [$g1->id, $g2->id], 'Test transfer');

        // Request is pending with TR-YY-NNNNN number.
        expect($request->status)->toBe(TransferRequest::STATUS_PENDING)
            ->and($request->request_number)->toMatch('/^TR-\d{2}-\d{5}$/')
            ->and($request->from_branch_id)->toBe($main->id)
            ->and($request->to_branch_id)->toBe($b1->id)
            ->and($request->items)->toHaveCount(2);

        // Both garments are reserved.
        expect($g1->fresh()->current_status)->toBe(Garment::STATUS_RESERVED)
            ->and($g2->fresh()->current_status)->toBe(Garment::STATUS_RESERVED);

        // The projection carries the reservation reference.
        $proj1 = GarmentCurrentStock::where('garment_id', $g1->id)->first();
        expect($proj1->reservation_reference_type)->toBe('transfer_request')
            ->and($proj1->reservation_reference_id)->toBe($request->id);

        // Exactly one reservation movement per garment.
        $movements = InventoryMovement::where('reference_type', 'transfer_request')
            ->where('reference_id', $request->id)
            ->where('movement_type', InventoryMovement::TYPE_RESERVATION)
            ->get();
        expect($movements)->toHaveCount(2)
            ->and($movements->pluck('garment_id')->sort()->values()->all())->toEqual([$g1->id, $g2->id]);
    });

    it('dispatches the TransferRequested event', function () {
        Event::fake([TransferRequested::class]);
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);

        Event::assertDispatched(TransferRequested::class, fn ($e) => $e->request->id === $request->id);
    });

    it('audits transfer.request', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer.request',
            'category' => 'transfers',
            'reference_type' => 'transfer_request',
            'reference_id' => $request->id,
        ]);
    });

    it('refuses to create a request with no garments', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);

        try {
            app(TransferService::class)->createRequest($supervisor, $main, $b1, []);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('garments');
        }
    });

    it('refuses to create a request where from === to branch', function () {
        $main = Branch::where('code', 'M')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        try {
            app(TransferService::class)->createRequest($supervisor, $main, $main, [$g->id]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('branches');
        }
    });

    it('refuses to request a non-available garment (sold)', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);
        $g->update(['current_status' => Garment::STATUS_SOLD]);

        try {
            app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('garments');
        }
    });

    it('refuses to request a garment not at the source branch', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $b2 = Branch::where('code', 'B2')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($b2, $product); // garment at B2, source is M

        try {
            app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('garments');
        }
    });

    it('refuses a salesperson (no transfer.request.create permission)', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $salesperson = salesperson($main);
        $this->actingAs($salesperson);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        try {
            app(TransferService::class)->createRequest($salesperson, $main, $b1, [$g->id]);
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            expect(true)->toBeTrue();
        }
        expect(TransferRequest::count())->toBe(0);
        expect($g->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE);
    });
});

/*
|--------------------------------------------------------------------------
| approve / reject
|--------------------------------------------------------------------------
*/
describe('approve / reject', function () {
    it('approves a pending request; garments stay reserved', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->approve($request, $supervisor);

        expect($request->status)->toBe(TransferRequest::STATUS_APPROVED)
            ->and($request->approved_by)->toBe($supervisor->id)
            ->and($request->approved_at)->not->toBeNull()
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_RESERVED);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer.approve',
            'reference_type' => 'transfer_request',
            'reference_id' => $request->id,
        ]);
    });

    it('rejects a pending request and releases reservations', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->reject($request, $supervisor, 'Wrong branch');

        expect($request->status)->toBe(TransferRequest::STATUS_REJECTED)
            ->and($request->rejection_reason)->toBe('Wrong branch')
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE);

        // Reservation ref cleared.
        $proj = GarmentCurrentStock::where('garment_id', $g->id)->first();
        expect($proj->reservation_reference_type)->toBeNull();

        // Reservation-release movement written.
        $release = InventoryMovement::where('garment_id', $g->id)
            ->where('movement_type', InventoryMovement::TYPE_RESERVATION_RELEASE)
            ->first();
        expect($release)->not->toBeNull();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer.reject',
            'reference_type' => 'transfer_request',
            'reference_id' => $request->id,
        ]);
    });

    it('destination supervisor cannot approve (403)', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $mainSup = supervisor($main);
        $b1Sup = supervisor($b1);
        $this->actingAs($mainSup);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g->id]);

        try {
            app(TransferService::class)->approve($request, $b1Sup);
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            expect(true)->toBeTrue();
        }
        expect($request->fresh()->status)->toBe(TransferRequest::STATUS_PENDING);
    });
});

/*
|--------------------------------------------------------------------------
| dispatch
|--------------------------------------------------------------------------
*/
describe('dispatch', function () {
    it('dispatches an approved request; garments → in_transit', function () {
        Event::fake([TransferDispatched::class]);
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->approve($request, $supervisor);
        $request = app(TransferService::class)->dispatch($request, $supervisor);

        expect($request->status)->toBe(TransferRequest::STATUS_DISPATCHED)
            ->and($request->dispatched_by)->toBe($supervisor->id)
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_IN_TRANSIT);

        // Transfer-dispatch movement.
        $dispatch = InventoryMovement::where('garment_id', $g->id)
            ->where('movement_type', InventoryMovement::TYPE_TRANSFER_DISPATCH)->first();
        expect($dispatch)->not->toBeNull()
            ->and($dispatch->reference_type)->toBe('transfer_request')
            ->and($dispatch->reference_id)->toBe($request->id);

        // Item dispatched flag.
        expect($request->items->first()->dispatched)->toBeTrue();

        Event::assertDispatched(TransferDispatched::class, fn ($e) => $e->request->id === $request->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer.dispatch',
            'reference_type' => 'transfer_request',
            'reference_id' => $request->id,
        ]);
    });

    it('refuses to dispatch a pending request (not approved)', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);

        try {
            app(TransferService::class)->dispatch($request, $supervisor);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('request');
        }
    });
});

/*
|--------------------------------------------------------------------------
| receive
|--------------------------------------------------------------------------
*/
describe('receive', function () {
    it('receives all garments: status → available, branch → destination', function () {
        Event::fake([TransferReceived::class]);
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $mainSup = supervisor($main);
        $b1Sup = supervisor($b1);
        $this->actingAs($mainSup);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->approve($request, $mainSup);
        $request = app(TransferService::class)->dispatch($request, $mainSup);

        // Receive at B1 by B1's supervisor.
        $this->actingAs($b1Sup);
        $request = app(TransferService::class)->receive($request, $b1Sup, [$g->id]);

        expect($request->status)->toBe(TransferRequest::STATUS_RECEIVED)
            ->and($request->received_by)->toBe($b1Sup->id)
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE)
            ->and($g->fresh()->current_branch_id)->toBe($b1->id);

        // Transfer-receive movement.
        $receive = InventoryMovement::where('garment_id', $g->id)
            ->where('movement_type', InventoryMovement::TYPE_TRANSFER_RECEIVE)->first();
        expect($receive)->not->toBeNull();

        // Item received flag.
        expect($request->items->first()->received)->toBeTrue();

        Event::assertDispatched(TransferReceived::class, fn ($e) => $e->request->id === $request->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer.receive',
            'reference_type' => 'transfer_request',
            'reference_id' => $request->id,
        ]);
    });

    it('receive with shortage: missing garments → missing status; discrepancy recorded', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $mainSup = supervisor($main);
        $b1Sup = supervisor($b1);
        $this->actingAs($mainSup);
        [$product] = productWithPrice(1000, 500);
        $g1 = availableGarmentAtBranch($main, $product);
        $g2 = availableGarmentAtBranch($main, $product);
        $g3 = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g1->id, $g2->id, $g3->id]);
        $request = app(TransferService::class)->approve($request, $mainSup);
        $request = app(TransferService::class)->dispatch($request, $mainSup);

        // Receive only g1 and g2 — g3 is missing.
        $this->actingAs($b1Sup);
        $request = app(TransferService::class)->receive($request, $b1Sup, [$g1->id, $g2->id], 'One garment missing');

        expect($request->status)->toBe(TransferRequest::STATUS_DISCREPANCY);

        // g3 is flagged missing (never silently received).
        expect($g3->fresh()->current_status)->toBe(Garment::STATUS_MISSING)
            ->and($g1->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE)
            ->and($g1->fresh()->current_branch_id)->toBe($b1->id);

        // Discrepancy row with missing_garment_ids.
        $discrepancy = TransferDiscrepancy::where('transfer_request_id', $request->id)->first();
        expect($discrepancy)->not->toBeNull()
            ->and($discrepancy->discrepancy_type)->toBe(TransferDiscrepancy::TYPE_SHORTAGE)
            ->and($discrepancy->missing_garment_ids)->toBe([$g3->id]);

        // No transfer_receive movement for g3.
        $receiveG3 = InventoryMovement::where('garment_id', $g3->id)
            ->where('movement_type', InventoryMovement::TYPE_TRANSFER_RECEIVE)->first();
        expect($receiveG3)->toBeNull();
        // But a missing_report movement for g3.
        $missingG3 = InventoryMovement::where('garment_id', $g3->id)
            ->where('movement_type', InventoryMovement::TYPE_MISSING_REPORT)->first();
        expect($missingG3)->not->toBeNull();
    });

    it('receive with excess: discrepancy row type = excess', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $mainSup = supervisor($main);
        $b1Sup = supervisor($b1);
        $this->actingAs($mainSup);
        [$product] = productWithPrice(1000, 500);
        $g1 = availableGarmentAtBranch($main, $product);
        // An extra garment not in the request, but scanned as received.
        $gExtra = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g1->id]);
        $request = app(TransferService::class)->approve($request, $mainSup);
        $request = app(TransferService::class)->dispatch($request, $mainSup);

        // Receive g1 + the extra garment.
        $this->actingAs($b1Sup);
        $request = app(TransferService::class)->receive($request, $b1Sup, [$g1->id, $gExtra->id]);

        $discrepancy = TransferDiscrepancy::where('transfer_request_id', $request->id)->first();
        expect($discrepancy)->not->toBeNull()
            ->and($discrepancy->discrepancy_type)->toBe(TransferDiscrepancy::TYPE_EXCESS)
            ->and($discrepancy->excess_garment_ids)->toBe([$gExtra->id]);
    });

    it('destination supervisor can receive; source supervisor cannot', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $mainSup = supervisor($main);
        $b1Sup = supervisor($b1);
        $this->actingAs($mainSup);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($mainSup, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->approve($request, $mainSup);
        $request = app(TransferService::class)->dispatch($request, $mainSup);

        // Source supervisor (M) cannot receive — must be the destination supervisor.
        try {
            app(TransferService::class)->receive($request, $mainSup, [$g->id]);
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            expect(true)->toBeTrue();
        }

        // Destination supervisor (B1) can.
        $this->actingAs($b1Sup);
        $request = app(TransferService::class)->receive($request, $b1Sup, [$g->id]);
        expect($request->status)->toBe(TransferRequest::STATUS_RECEIVED);
    });
});

/*
|--------------------------------------------------------------------------
| cancel
|--------------------------------------------------------------------------
*/
describe('cancel', function () {
    it('cancels a pending request and releases reservations', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->cancel($request, $supervisor, 'No longer needed');

        expect($request->status)->toBe(TransferRequest::STATUS_CANCELLED)
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer.cancel',
            'reference_type' => 'transfer_request',
            'reference_id' => $request->id,
        ]);
    });

    it('refuses to cancel a dispatched request', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
        $request = app(TransferService::class)->approve($request, $supervisor);
        $request = app(TransferService::class)->dispatch($request, $supervisor);

        try {
            app(TransferService::class)->cancel($request, $supervisor, 'Too late');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('request');
        }
    });
});

/*
|--------------------------------------------------------------------------
| CRITICAL CROSS-MODULE TEST: a reserved garment cannot be sold via POS
|--------------------------------------------------------------------------
*/
describe('reservation blocks POS', function () {
    it('a garment reserved by a transfer request cannot be sold via SaleService', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product, $price] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        // Create a transfer request reserving the garment.
        app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
        expect($g->fresh()->current_status)->toBe(Garment::STATUS_RESERVED);

        // Attempt to sell the reserved garment via POS SaleService.
        $salesperson = salesperson($main);
        $this->actingAs($salesperson);
        try {
            app(SaleService::class)->complete($salesperson, $main, [
                'sale_type' => 'retail',
                'items' => [['garment_id' => $g->id]],
                'payments' => [['method' => 'cash', 'amount' => 1000]],
            ]);
            $this->fail('Expected GarmentNotAvailableException — reserved garments must not be sellable');
        } catch (GarmentNotAvailableException $e) {
            expect($e->garmentId)->toBe($g->id)
                ->and($e->currentStatus)->toBe(Garment::STATUS_RESERVED);
        }

        // No invoice created; garment stays reserved.
        expect(SaleInvoice::count())->toBe(0);
        expect($g->fresh()->current_status)->toBe(Garment::STATUS_RESERVED);
    });

    it('after rejecting the transfer, the garment becomes sellable again', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);
        [$product, $price] = productWithPrice(1000, 500);
        $g = availableGarmentAtBranch($main, $product);

        $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
        app(TransferService::class)->reject($request, $supervisor, 'Cancelled');

        // Garment is available again.
        expect($g->fresh()->current_status)->toBe(Garment::STATUS_AVAILABLE);

        // Now it can be sold.
        $salesperson = salesperson($main);
        $this->actingAs($salesperson);
        $invoice = app(SaleService::class)->complete($salesperson, $main, [
            'sale_type' => 'retail',
            'items' => [['garment_id' => $g->id]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ]);

        expect($invoice->isCompleted())->toBeTrue()
            ->and($g->fresh()->current_status)->toBe(Garment::STATUS_SOLD);
    });
});

/*
|--------------------------------------------------------------------------
| Number generation concurrency-safety
|--------------------------------------------------------------------------
*/
describe('request numbers', function () {
    it('generates unique TR-YY-NNNNN numbers across sequential requests', function () {
        $main = Branch::where('code', 'M')->first();
        $b1 = Branch::where('code', 'B1')->first();
        $supervisor = supervisor($main);
        $this->actingAs($supervisor);

        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            [$product] = productWithPrice(1000, 500);
            $g = availableGarmentAtBranch($main, $product);
            $request = app(TransferService::class)->createRequest($supervisor, $main, $b1, [$g->id]);
            $numbers[] = $request->request_number;
        }

        expect(count(array_unique($numbers)))->toBe(3);
        foreach ($numbers as $n) {
            expect($n)->toMatch('/^TR-\d{2}-\d{5}$/');
        }
    });
});
