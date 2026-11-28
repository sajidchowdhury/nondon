<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the list of users.
     * Only Main Branch Admin and Branch Supervisors can view the user list.
     * Supervisors see only their own branch users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['main_branch_admin', 'branch_supervisor', 'online_supervisor']);
    }

    /**
     * Determine whether the user can view a specific user.
     * Admin: all users.
     * Supervisor: users in their own branch.
     * Salesperson: only themselves.
     */
    public function view(User $user, User $target): Response
    {
        // Main Branch Admin can view all users
        if ($user->hasRole('main_branch_admin')) {
            return Response::allow();
        }

        // Users can always view their own profile
        if ($user->id === $target->id) {
            return Response::allow();
        }

        // Branch Supervisor can view users in their own branch
        if ($user->hasRole('branch_supervisor')
            && $user->assigned_branch_id === $target->assigned_branch_id
            && $user->assigned_branch_id !== null) {
            return Response::allow();
        }

        // Online Supervisor can view online channel users
        if ($user->hasRole('online_supervisor')
            && $target->is_online_channel) {
            return Response::allow();
        }

        return Response::deny(__('You are not authorized to view this user.'));
    }

    /**
     * Determine whether the user can create new users.
     * Only Main Branch Admin can create users.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can update a user.
     * Only Main Branch Admin can edit user details.
     */
    public function update(User $user, User $target): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can activate/deactivate a user.
     * Only Main Branch Admin can change account status.
     */
    public function toggleStatus(User $user, User $target): Response
    {
        if (! $user->hasRole('main_branch_admin')) {
            return Response::deny(__('Only administrators can change account status.'));
        }

        // Cannot deactivate yourself
        if ($user->id === $target->id) {
            return Response::deny(__('You cannot change your own account status.'));
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can assign a user to a different branch.
     * Only Main Branch Admin can change branch assignment.
     */
    public function assignBranch(User $user, User $target): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can unlock a locked account.
     * Only Main Branch Admin can unlock accounts.
     */
    public function unlock(User $user, User $target): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can assign roles.
     * Only Main Branch Admin can assign roles.
     * Accepts optional target user — when called via @can with a class
     * name (no model instance), only the first argument is passed.
     */
    public function assignRoles(User $user, ?User $target = null): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can reset passwords.
     * Only Main Branch Admin can reset passwords.
     */
    public function resetPassword(User $user, User $target): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can delete a user.
     * Users are never deleted — they are deactivated.
     * This policy always denies deletion.
     */
    public function delete(User $user, User $target): bool
    {
        return false;
    }
}
