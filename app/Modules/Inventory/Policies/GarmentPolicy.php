<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Garment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class GarmentPolicy
{
    use HandlesAuthorization;

    /**
     * All authenticated users can view the garment list.
     * (Salespersons need it for POS, supervisors for stock management.)
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * All authenticated users can view a specific garment.
     * Purchase cost is hidden at model level for non-admin.
     */
    public function view(User $user, Garment $garment): Response
    {
        return Response::allow();
    }

    /**
     * Only admin can create garments directly.
     * In production, garments are created via the purchasing module (Phase 2).
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Only admin can update garment metadata.
     * Status changes go through InventoryMovementService (Session 1.10).
     */
    public function update(User $user, Garment $garment): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can view the purchase cost.
     * Admin only. This is a sensitive data restriction.
     */
    public function viewPurchaseCost(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Garments are never deleted — they have permanent identity.
     */
    public function delete(User $user, Garment $garment): bool
    {
        return false;
    }
}
