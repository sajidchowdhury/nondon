<?php

namespace App\Modules\Transfers\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Transfers\Models\TransferRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * TransferRequest authorization policy (doc 05 §4.1, ADR-007).
 *
 *  - main_branch_admin: all transfer actions, all branches.
 *  - branch_supervisor: create + approve/dispatch from own branch + receive at own branch.
 *  - salesperson: no transfer actions (blocked — transfers are a supervisor/admin workflow).
 *  - online_team / online_supervisor: create requests TO the online channel
 *    (their assigned branch is the online channel; from-branch validation
 *    still applies at the service layer).
 */
class TransferRequestPolicy
{
    use HandlesAuthorization;

    /**
     * View the transfer list. Any role with a transfer permission sees the
     * list (scoped by branch in the service query).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('transfer.request.create')
            || $user->can('transfer.approve')
            || $user->can('transfer.dispatch')
            || $user->can('transfer.receive');
    }

    /**
     * View a specific transfer. Admin: all. Others: involved branch (from or to).
     */
    public function view(User $user, TransferRequest $request): Response
    {
        if ($user->hasRole('main_branch_admin')) {
            return Response::allow();
        }

        $isInvolved = (int) $request->from_branch_id === (int) $user->assigned_branch_id
            || (int) $request->to_branch_id === (int) $user->assigned_branch_id;

        return $isInvolved
            ? Response::allow()
            : Response::deny(__('You can only view transfers involving your own branch.'));
    }

    /**
     * Create a transfer request (supervisor or online supervisor).
     */
    public function create(User $user): bool
    {
        return $user->can('transfer.request.create');
    }

    /**
     * Approve a transfer request — source-branch supervisor or admin.
     */
    public function approve(User $user, TransferRequest $request): bool
    {
        if (! $user->can('transfer.approve')) {
            return false;
        }

        return $user->hasRole('main_branch_admin')
            || (int) $request->from_branch_id === (int) $user->assigned_branch_id;
    }

    /**
     * Reject a transfer request — same as approve (source supervisor).
     */
    public function reject(User $user, TransferRequest $request): bool
    {
        return $this->approve($user, $request);
    }

    /**
     * Dispatch a transfer request — source-branch supervisor or admin.
     */
    public function dispatch(User $user, TransferRequest $request): bool
    {
        if (! $user->can('transfer.dispatch')) {
            return false;
        }

        return $user->hasRole('main_branch_admin')
            || (int) $request->from_branch_id === (int) $user->assigned_branch_id;
    }

    /**
     * Receive a transfer request — destination-branch supervisor or admin.
     */
    public function receive(User $user, TransferRequest $request): bool
    {
        if (! $user->can('transfer.receive')) {
            return false;
        }

        return $user->hasRole('main_branch_admin')
            || (int) $request->to_branch_id === (int) $user->assigned_branch_id;
    }

    /**
     * Cancel a transfer request — the requester or an admin.
     */
    public function cancel(User $user, TransferRequest $request): bool
    {
        return (int) $request->requested_by === $user->id
            || $user->hasRole('main_branch_admin');
    }

    /**
     * Transfer requests are never deleted.
     */
    public function delete(User $user, TransferRequest $request): bool
    {
        return false;
    }
}
