<?php

namespace App\Modules\Transfers\Http\Livewire\Transfers;

use App\Modules\Identity\Models\User;
use App\Modules\Transfers\Models\TransferRequest;
use App\Modules\Transfers\Services\TransferService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * TransferView — read-only detail with role-gated lifecycle actions.
 *
 * Actions (per policy + status):
 *  - pending + source supervisor → Approve / Reject (reason)
 *  - approved + source supervisor → Dispatch
 *  - dispatched + destination supervisor → Receive (routes to 3.8)
 *  - pending/approved + requester → Cancel
 */
class TransferView extends Component
{
    public TransferRequest $transfer;

    public string $rejectReason = '';

    public string $cancelReason = '';

    public string $actionError = '';

    public string $actionSuccess = '';

    public function mount(TransferRequest $transfer): void
    {
        $this->authorize('view', $transfer);
        $this->transfer = $transfer->load([
            'fromBranch', 'toBranch', 'requestedBy', 'approvedBy', 'dispatchedBy', 'receivedBy',
            'items.garment.product', 'items.garment.productSize', 'items.product', 'items.productSize',
            'discrepancies',
        ]);
    }

    public function approve(TransferService $service): void
    {
        $this->actionError = '';
        $this->actionSuccess = '';

        try {
            /** @var User $user */
            $user = auth()->user();
            $this->transfer = $service->approve($this->transfer, $user);
            $this->actionSuccess = __('Transfer approved.');
        } catch (AuthorizationException $e) {
            $this->actionError = $e->getMessage();
        } catch (ValidationException $e) {
            $this->actionError = collect($e->errors())->flatten()->first();
        }
    }

    public function reject(TransferService $service): void
    {
        $this->actionError = '';
        $this->actionSuccess = '';

        if (trim($this->rejectReason) === '') {
            $this->addError('rejectReason', __('A rejection reason is required.'));

            return;
        }

        try {
            /** @var User $user */
            $user = auth()->user();
            $this->transfer = $service->reject($this->transfer, $user, $this->rejectReason);
            $this->actionSuccess = __('Transfer rejected.');
            $this->rejectReason = '';
        } catch (AuthorizationException $e) {
            $this->actionError = $e->getMessage();
        } catch (ValidationException $e) {
            $this->actionError = collect($e->errors())->flatten()->first();
        }
    }

    public function dispatchTransfer(TransferService $service): void
    {
        $this->actionError = '';
        $this->actionSuccess = '';

        try {
            /** @var User $user */
            $user = auth()->user();
            $this->transfer = $service->dispatch($this->transfer, $user);
            $this->actionSuccess = __('Transfer dispatched.');
        } catch (AuthorizationException $e) {
            $this->actionError = $e->getMessage();
        } catch (ValidationException $e) {
            $this->actionError = collect($e->errors())->flatten()->first();
        }
    }

    public function cancel(TransferService $service): void
    {
        $this->actionError = '';
        $this->actionSuccess = '';

        if (trim($this->cancelReason) === '') {
            $this->addError('cancelReason', __('A cancellation reason is required.'));

            return;
        }

        try {
            /** @var User $user */
            $user = auth()->user();
            $this->transfer = $service->cancel($this->transfer, $user, $this->cancelReason);
            $this->actionSuccess = __('Transfer cancelled.');
            $this->cancelReason = '';
        } catch (AuthorizationException $e) {
            $this->actionError = $e->getMessage();
        } catch (ValidationException $e) {
            $this->actionError = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        $this->authorize('view', $this->transfer);

        return view('livewire.transfers.view', [
            'transfer' => $this->transfer,
            'canApprove' => auth()->user()?->can('approve', $this->transfer),
            'canDispatch' => auth()->user()?->can('dispatch', $this->transfer),
            'canReceive' => auth()->user()?->can('receive', $this->transfer),
            'canCancel' => auth()->user()?->can('cancel', $this->transfer),
        ])->layout('layouts.app');
    }
}
