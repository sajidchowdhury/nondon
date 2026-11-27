<?php

namespace App\Modules\Purchasing\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Purchasing\Models\Purchase;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class PurchasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the list of purchases.
     * Admin only (per BRD §4.1).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can view a specific purchase.
     * Admin only.
     */
    public function view(User $user, Purchase $purchase): Response
    {
        return $user->hasRole('main_branch_admin')
            ? Response::allow()
            : Response::deny(__('You do not have permission to view purchases.'));
    }

    /**
     * Determine whether the user can create purchases.
     * Admin only.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can update a purchase.
     * Admin only, AND the purchase must be in draft status.
     */
    public function update(User $user, Purchase $purchase): bool
    {
        return $user->hasRole('main_branch_admin') && $purchase->isDraft();
    }

    /**
     * Determine whether the user can confirm a purchase.
     * Admin only, AND the purchase must be a draft.
     */
    public function confirm(User $user, Purchase $purchase): bool
    {
        return $user->hasRole('main_branch_admin') && $purchase->canBeConfirmed();
    }

    /**
     * Determine whether the user can cancel a purchase.
     * Admin only, AND the purchase must be draft or confirmed.
     */
    public function cancel(User $user, Purchase $purchase): bool
    {
        return $user->hasRole('main_branch_admin') && $purchase->canBeCancelled();
    }

    /**
     * Determine whether the user can receive goods for a purchase.
     * Admin + Branch Supervisor (receiving staff).
     * The purchase must be confirmed or partially_received.
     *
     * Per Security §4.1: "Receive goods" is permitted for Main Admin AND
     * Branch Supervisor. In practice, receiving only happens at Main Branch.
     */
    public function receive(User $user, Purchase $purchase): bool
    {
        return $user->hasRole(['main_branch_admin', 'branch_supervisor'])
            && $purchase->canBeReceived();
    }

    /**
     * Determine whether the user can view purchase cost data.
     * Admin only (per BRD §7.4).
     */
    public function viewCost(User $user, Purchase $purchase): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can delete a purchase.
     * Purchases are never deleted — they are cancelled.
     */
    public function delete(User $user, Purchase $purchase): bool
    {
        return false;
    }
}
