<?php

namespace App\Modules\Organization\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class BranchPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the list of branches.
     * Admin, supervisors, and online supervisor can view branch list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['main_branch_admin', 'branch_supervisor', 'online_supervisor']);
    }

    /**
     * Determine whether the user can view a specific branch.
     * Admin: all branches. Others: own branch only.
     */
    public function view(User $user, Branch $branch): Response
    {
        if ($user->hasRole('main_branch_admin')) {
            return Response::allow();
        }

        // Supervisors can view their own branch
        if ($user->assigned_branch_id === $branch->id) {
            return Response::allow();
        }

        // Online supervisor can view the online channel
        if ($user->hasRole('online_supervisor') && $branch->is_online_channel) {
            return Response::allow();
        }

        return Response::deny(__('You are not authorized to view this branch.'));
    }

    /**
     * Determine whether the user can create branches.
     * Admin only.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can update a branch.
     * Admin only.
     */
    public function update(User $user, Branch $branch): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can activate/deactivate a branch.
     * Admin only.
     */
    public function toggleStatus(User $user, Branch $branch): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can manage stock locations for a branch.
     * Admin only.
     */
    public function manageStockLocations(User $user, Branch $branch): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can delete a branch.
     * Branches are never deleted — they are deactivated.
     */
    public function delete(User $user, Branch $branch): bool
    {
        return false;
    }
}
