<?php

namespace App\Modules\Transfers\Services;

use App\Modules\Audit\Events\AuditableEvent;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Inventory\Services\InventoryMovementService;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Organization\Services\FiscalYearService;
use App\Modules\Transfers\Events\TransferDispatched;
use App\Modules\Transfers\Events\TransferReceived;
use App\Modules\Transfers\Events\TransferRequested;
use App\Modules\Transfers\Models\TransferDiscrepancy;
use App\Modules\Transfers\Models\TransferRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TransferService — the WF-6 + WF-7 inter-branch transfer lifecycle.
 *
 * Reservation model: implicit. Creating a request records a TYPE_RESERVATION
 * movement per garment (status → reserved, garment_current_stock.
 * reservation_reference_* → transfer_request). Rejecting/cancelling records
 * TYPE_RESERVATION_RELEASE (status → available, ref cleared). Dispatching
 * records TYPE_TRANSFER_DISPATCH (status → in_transit). Receiving records
 * TYPE_TRANSFER_RECEIVE (status → available, branch → destination) for
 * received garments and TYPE_MISSING_REPORT for missing ones (never silently
 * marked received — WF-7 step 3).
 *
 * All operations are atomic (single DB transaction) and audited.
 */
class TransferService
{
    public function __construct(
        protected InventoryMovementService $inventoryMovementService,
        protected TransferNumberService $transferNumberService,
        protected FiscalYearService $fiscalYearService,
    ) {}

    /**
     * Create a transfer request and implicitly reserve all the garments.
     *
     * @param  array<int, int>  $garmentIds  garment IDs to transfer (min 1)
     *
     * @throws AuthorizationException|ValidationException
     */
    public function createRequest(User $requester, Branch $fromBranch, Branch $toBranch, array $garmentIds, ?string $notes = null): TransferRequest
    {
        $this->authorize('create', $requester, $fromBranch);

        $this->validateCreate($fromBranch, $toBranch, $garmentIds);

        $garments = $this->loadAndValidateGarments($garmentIds, $fromBranch);

        $fiscalYear = FiscalYear::active()->first();
        if (! $fiscalYear) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('No active fiscal year. Cannot create a transfer request.'),
            ]);
        }

        return DB::transaction(function () use ($requester, $fromBranch, $toBranch, $garments, $fiscalYear, $notes) {
            $requestNumber = $this->transferNumberService->generateNextRequestNumber($fiscalYear);

            $request = TransferRequest::create([
                'request_number' => $requestNumber,
                'from_branch_id' => $fromBranch->id,
                'to_branch_id' => $toBranch->id,
                'status' => TransferRequest::STATUS_PENDING,
                'requested_by' => $requester->id,
                'requested_at' => now(),
                'notes' => $notes,
            ]);

            // Reserve each garment (implicit reservation — WF-6 step 1f).
            foreach ($garments as $garment) {
                $this->inventoryMovementService->record(
                    garment: $garment,
                    movementType: InventoryMovement::TYPE_RESERVATION,
                    context: [
                        'reference_type' => 'transfer_request',
                        'reference_id' => $request->id,
                        'performed_by' => $requester->id,
                        'notes' => 'Transfer request '.$requestNumber,
                    ],
                );

                $request->items()->create([
                    'garment_id' => $garment->id,
                    'product_id' => $garment->product_id,
                    'product_size_id' => $garment->product_size_id,
                    'dispatched' => false,
                    'received' => false,
                ]);
            }

            event(new AuditableEvent('transfer.request', [
                'category' => 'transfers',
                'reference_type' => 'transfer_request',
                'reference_id' => $request->id,
                'new_values' => [
                    'request_number' => $requestNumber,
                    'from_branch_id' => $fromBranch->id,
                    'to_branch_id' => $toBranch->id,
                    'item_count' => count($garments),
                ],
                'reason' => null,
            ]));

            event(new TransferRequested($request->fresh()));

            return $request->fresh();
        });
    }

    /**
     * Approve a pending transfer request (source-branch supervisor/admin).
     * Garments remain reserved.
     */
    public function approve(TransferRequest $request, User $approver): TransferRequest
    {
        $this->authorizeApprove($request, $approver);

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => __('Only pending transfer requests can be approved.'),
            ]);
        }

        return DB::transaction(function () use ($request, $approver) {
            $request->update([
                'status' => TransferRequest::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            event(new AuditableEvent('transfer.approve', [
                'category' => 'transfers',
                'reference_type' => 'transfer_request',
                'reference_id' => $request->id,
                'new_values' => ['request_number' => $request->request_number],
                'reason' => null,
            ]));

            return $request->fresh();
        });
    }

    /**
     * Reject a pending/approved transfer request, releasing all reservations.
     */
    public function reject(TransferRequest $request, User $approver, string $reason): TransferRequest
    {
        $this->authorizeApprove($request, $approver);

        if (! in_array($request->status, [TransferRequest::STATUS_PENDING, TransferRequest::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages([
                'request' => __('Only pending or approved transfer requests can be rejected.'),
            ]);
        }

        return DB::transaction(function () use ($request, $approver, $reason) {
            $request->load('items.garment');

            // Release all reservations.
            foreach ($request->items as $item) {
                $garment = $item->garment;
                if ($garment && $garment->current_status === Garment::STATUS_RESERVED) {
                    $this->inventoryMovementService->record(
                        garment: $garment,
                        movementType: InventoryMovement::TYPE_RESERVATION_RELEASE,
                        context: [
                            'reference_type' => 'transfer_request',
                            'reference_id' => $request->id,
                            'performed_by' => $approver->id,
                            'notes' => 'Released: '.$reason,
                        ],
                    );
                }
            }

            $request->update([
                'status' => TransferRequest::STATUS_REJECTED,
                'rejected_by' => $approver->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            event(new AuditableEvent('transfer.reject', [
                'category' => 'transfers',
                'reference_type' => 'transfer_request',
                'reference_id' => $request->id,
                'new_values' => ['request_number' => $request->request_number],
                'reason' => $reason,
            ]));

            return $request->fresh();
        });
    }

    /**
     * Dispatch an approved transfer (source-branch supervisor/admin).
     * Garments → in_transit.
     */
    public function dispatch(TransferRequest $request, User $dispatcher): TransferRequest
    {
        $this->authorizeDispatch($request, $dispatcher);

        if (! $request->isApproved()) {
            throw ValidationException::withMessages([
                'request' => __('Only approved transfer requests can be dispatched.'),
            ]);
        }

        return DB::transaction(function () use ($request, $dispatcher) {
            $request->load('items.garment', 'toBranch');
            $toBranchId = $request->to_branch_id;

            foreach ($request->items as $item) {
                $garment = $item->garment;
                if (! $garment) {
                    continue;
                }

                $this->inventoryMovementService->record(
                    garment: $garment,
                    movementType: InventoryMovement::TYPE_TRANSFER_DISPATCH,
                    context: [
                        'reference_type' => 'transfer_request',
                        'reference_id' => $request->id,
                        'to_branch_id' => $toBranchId,
                        'performed_by' => $dispatcher->id,
                        'notes' => 'Dispatch '.$request->request_number,
                    ],
                );

                $item->update(['dispatched' => true]);
            }

            $request->update([
                'status' => TransferRequest::STATUS_DISPATCHED,
                'dispatched_by' => $dispatcher->id,
                'dispatched_at' => now(),
            ]);

            event(new AuditableEvent('transfer.dispatch', [
                'category' => 'transfers',
                'reference_type' => 'transfer_request',
                'reference_id' => $request->id,
                'new_values' => ['request_number' => $request->request_number],
                'reason' => null,
            ]));

            event(new TransferDispatched($request->fresh()));

            return $request->fresh();
        });
    }

    /**
     * Receive a dispatched transfer at the destination branch.
     *
     * @param  array<int, int>  $receivedGarmentIds  the garment IDs confirmed received
     */
    public function receive(TransferRequest $request, User $receiver, array $receivedGarmentIds, ?string $discrepancyReason = null): TransferRequest
    {
        $this->authorizeReceive($request, $receiver);

        if (! $request->isDispatched()) {
            throw ValidationException::withMessages([
                'request' => __('Only dispatched transfer requests can be received.'),
            ]);
        }

        $request->load(['items.garment', 'toBranch']);
        $toBranch = $request->toBranch;
        $toLocationId = $toBranch?->stockLocations()->first()?->id;

        // Expected = all dispatched item garment ids; received = the passed set.
        $expectedIds = $request->items->where('dispatched', true)->pluck('garment_id')->all();
        $receivedSet = array_map('intval', $receivedGarmentIds);
        $missingIds = array_values(array_diff($expectedIds, $receivedSet));
        $excessIds = array_values(array_diff($receivedSet, $expectedIds));

        return DB::transaction(function () use ($request, $receiver, $receivedSet, $missingIds, $excessIds, $toBranch, $toLocationId, $discrepancyReason) {
            // Receive each expected garment that was confirmed received.
            foreach ($request->items as $item) {
                $garment = $item->garment;
                if (! $garment) {
                    continue;
                }

                if (in_array($garment->id, $receivedSet, true)) {
                    $this->inventoryMovementService->record(
                        garment: $garment,
                        movementType: InventoryMovement::TYPE_TRANSFER_RECEIVE,
                        context: [
                            'reference_type' => 'transfer_request',
                            'reference_id' => $request->id,
                            'to_branch_id' => $toBranch?->id,
                            'to_location_id' => $toLocationId,
                            'performed_by' => $receiver->id,
                            'notes' => 'Receive '.$request->request_number,
                        ],
                    );
                    $item->update(['received' => true]);
                } elseif (in_array($garment->id, $missingIds, true)) {
                    // Missing garments → flagged 'missing', never silently received.
                    $this->inventoryMovementService->record(
                        garment: $garment,
                        movementType: InventoryMovement::TYPE_MISSING_REPORT,
                        context: [
                            'reference_type' => 'transfer_request',
                            'reference_id' => $request->id,
                            'performed_by' => $receiver->id,
                            'notes' => 'Missing on receive of '.$request->request_number,
                        ],
                    );
                }
            }

            // Determine final status.
            $allReceived = empty($missingIds) && empty($excessIds);
            $hasShortage = ! empty($missingIds);
            $hasExcess = ! empty($excessIds);

            $finalStatus = $allReceived
                ? TransferRequest::STATUS_RECEIVED
                : ($hasShortage
                    ? TransferRequest::STATUS_DISCREPANCY
                    : TransferRequest::STATUS_PARTIALLY_RECEIVED);

            $request->update([
                'status' => $finalStatus,
                'received_by' => $receiver->id,
                'received_at' => now(),
            ]);

            // Record a discrepancy row if anything didn't match.
            if ($hasShortage || $hasExcess) {
                $discrepancyType = $hasShortage
                    ? TransferDiscrepancy::TYPE_SHORTAGE
                    : TransferDiscrepancy::TYPE_EXCESS;

                TransferDiscrepancy::create([
                    'transfer_request_id' => $request->id,
                    'discrepancy_type' => $discrepancyType,
                    'reason' => $discrepancyReason,
                    'missing_garment_ids' => $hasShortage ? $missingIds : null,
                    'excess_garment_ids' => $hasExcess ? $excessIds : null,
                    'status' => TransferDiscrepancy::STATUS_OPEN,
                ]);
            }

            event(new AuditableEvent('transfer.receive', [
                'category' => 'transfers',
                'reference_type' => 'transfer_request',
                'reference_id' => $request->id,
                'new_values' => [
                    'request_number' => $request->request_number,
                    'received_count' => count($receivedSet),
                    'missing_count' => count($missingIds),
                    'excess_count' => count($excessIds),
                    'final_status' => $finalStatus,
                ],
                'reason' => null,
            ]));

            event(new TransferReceived($request->fresh()));

            return $request->fresh();
        });
    }

    /**
     * Cancel a pending/approved transfer request, releasing all reservations.
     * Cannot cancel a dispatched request (the goods have left).
     */
    public function cancel(TransferRequest $request, User $actor, string $reason): TransferRequest
    {
        // Requester or admin can cancel; only pending or approved.
        if ((int) $request->requested_by !== $actor->id && ! $actor->hasRole('main_branch_admin')) {
            throw new AuthorizationException(__('Only the requester or an admin can cancel a transfer request.'));
        }

        if (! $request->canCancel()) {
            throw ValidationException::withMessages([
                'request' => __('Only pending or approved transfer requests can be cancelled.'),
            ]);
        }

        return DB::transaction(function () use ($request, $actor, $reason) {
            $request->load('items.garment');

            foreach ($request->items as $item) {
                $garment = $item->garment;
                if ($garment && $garment->current_status === Garment::STATUS_RESERVED) {
                    $this->inventoryMovementService->record(
                        garment: $garment,
                        movementType: InventoryMovement::TYPE_RESERVATION_RELEASE,
                        context: [
                            'reference_type' => 'transfer_request',
                            'reference_id' => $request->id,
                            'performed_by' => $actor->id,
                            'notes' => 'Cancelled: '.$reason,
                        ],
                    );
                }
            }

            $request->update([
                'status' => TransferRequest::STATUS_CANCELLED,
                'rejection_reason' => $reason,
            ]);

            event(new AuditableEvent('transfer.cancel', [
                'category' => 'transfers',
                'reference_type' => 'transfer_request',
                'reference_id' => $request->id,
                'new_values' => ['request_number' => $request->request_number],
                'reason' => $reason,
            ]));

            return $request->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Validation helpers
    |--------------------------------------------------------------------------
    */

    protected function validateCreate(Branch $fromBranch, Branch $toBranch, array $garmentIds): void
    {
        if ($fromBranch->id === $toBranch->id) {
            throw ValidationException::withMessages([
                'branches' => __('From and to branches must be different.'),
            ]);
        }

        if (empty($garmentIds)) {
            throw ValidationException::withMessages([
                'garments' => __('At least one garment is required for a transfer request.'),
            ]);
        }
    }

    /**
     * Load and validate each garment: must be available AND at the from-branch.
     *
     * @return array<int, Garment>
     */
    protected function loadAndValidateGarments(array $garmentIds, Branch $fromBranch): array
    {
        $garmentIds = array_map('intval', array_unique($garmentIds));
        $garments = Garment::whereIn('id', $garmentIds)->get()->keyBy('id');

        $resolved = [];
        foreach ($garmentIds as $id) {
            $garment = $garments->get($id);
            if (! $garment) {
                throw ValidationException::withMessages([
                    'garments' => __('Garment :id not found.', ['id' => $id]),
                ]);
            }

            if (! $garment->isAvailable()) {
                throw ValidationException::withMessages([
                    'garments' => __('Garment :barcode is not available for transfer (current status: :status).', [
                        'barcode' => $garment->barcode,
                        'status' => $garment->current_status,
                    ]),
                ]);
            }

            if ((int) $garment->current_branch_id !== $fromBranch->id) {
                throw ValidationException::withMessages([
                    'garments' => __('Garment :barcode is not at the source branch.', ['barcode' => $garment->barcode]),
                ]);
            }

            $resolved[] = $garment;
        }

        return $resolved;
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    protected function authorize(string $action, User $user, Branch $fromBranch): void
    {
        // Delegates to the TransferRequestPolicy via $user->can().
        // For create, the policy checks transfer.request.create.
        if (! $user->can('create', TransferRequest::class)) {
            throw new AuthorizationException(__('You do not have permission to create a transfer request.'));
        }
    }

    protected function authorizeApprove(TransferRequest $request, User $user): void
    {
        if (! $user->can('approve', $request)) {
            throw new AuthorizationException(__('You do not have permission to approve this transfer request.'));
        }
    }

    protected function authorizeDispatch(TransferRequest $request, User $user): void
    {
        if (! $user->can('dispatch', $request)) {
            throw new AuthorizationException(__('You do not have permission to dispatch this transfer request.'));
        }
    }

    protected function authorizeReceive(TransferRequest $request, User $user): void
    {
        if (! $user->can('receive', $request)) {
            throw new AuthorizationException(__('You do not have permission to receive this transfer request.'));
        }
    }
}
