<?php

namespace App\Modules\Organization\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\StockLocation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class StockLocationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view stock locations.
     * Admin, supervisors can view. Others have no direct access.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['main_branch_admin', 'branch_supervisor', 'online_supervisor']);
    }

    /**
     * Determine whether the user can view a specific stock location.
     */
    public function view(User $user, StockLocation $location): Response
    {
        if ($user->hasRole('main_branch_admin')) {
            return Response::allow();
        }

        if ($user->assigned_branch_id === $location->branch_id) {
            return Response::allow();
        }

        return Response::deny(__('You are not authorized to view this stock location.'));
    }

    /**
     * Determine whether the user can create stock locations.
     * Admin only.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can update a stock location.
     * Admin only.
     */
    public function update(User $user, StockLocation $location): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can delete a stock location.
     * Stock locations are deactivated, not deleted.
     */
    public function delete(User $user, StockLocation $location): bool
    {
        return false;
    }
}
