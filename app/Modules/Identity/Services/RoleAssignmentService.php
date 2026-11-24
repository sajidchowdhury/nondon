<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleAssignmentService
{
    /**
     * Assign a role to a user.
     *
     * If the user already has the role, this is a no-op.
     * If the user had only one role before, and now has two, the active role
     * remains the original one (user can switch later).
     *
     * @param User $user
     * @param Role $role
     * @return User
     */
    public function assignRole(User $user, Role $role): User
    {
        return DB::transaction(function () use ($user, $role) {
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }

            // If user has only one role and no active role set, set it as active
            if (!$user->active_role_id) {
                $roles = $user->roles;
                if ($roles->count() === 1) {
                    $user->update(['active_role_id' => $roles->first()->id]);
                }
            }

            return $user->fresh();
        });
    }

    /**
     * Remove a role from a user.
     *
     * If the removed role was the active role, reset the active role.
     * If the user has one remaining role, set it as active.
     * If the user has no remaining roles, set active_role_id to null
     * (this is an invalid state — user should always have at least one role).
     *
     * @param User $user
     * @param Role $role
     * @return User
     * @throws ValidationException if trying to remove the user's only role
     */
    public function removeRole(User $user, Role $role): User
    {
        return DB::transaction(function () use ($user, $role) {
            $currentRoles = $user->roles;

            // Cannot remove the user's only role
            if ($currentRoles->count() <= 1 && $user->hasRole($role)) {
                throw ValidationException::withMessages([
                    'roles' => __('Cannot remove the user\'s only role. Every user must have at least one role.'),
                ]);
            }

            if ($user->hasRole($role)) {
                $user->removeRole($role);
            }

            // If the removed role was the active role, update
            $remainingRoles = $user->fresh()->roles;

            if ($user->active_role_id === $role->id) {
                if ($remainingRoles->count() === 1) {
                    $user->update(['active_role_id' => $remainingRoles->first()->id]);
                } else {
                    $user->update(['active_role_id' => null]);
                }
            }

            return $user->fresh();
        });
    }

    /**
     * Sync all roles for a user.
     *
     * Replaces the user's current roles with the provided list.
     * Ensures the user always has at least one role.
     * Updates the active role if it was removed.
     *
     * @param User $user
     * @param array $roleIds Array of role IDs
     * @return User
     * @throws ValidationException if no roles provided
     */
    public function syncRoles(User $user, array $roleIds): User
    {
        if (empty($roleIds)) {
            throw ValidationException::withMessages([
                'roles' => __('At least one role must be assigned to the user.'),
            ]);
        }

        $roles = Role::whereIn('id', $roleIds)->get();

        if ($roles->count() !== count($roleIds)) {
            throw ValidationException::withMessages([
                'roles' => __('One or more roles not found.'),
            ]);
        }

        return DB::transaction(function () use ($user, $roles, $roleIds) {
            // Get current active role before sync
            $currentActiveRoleId = $user->active_role_id;

            // Sync roles
            $user->syncRoles($roles->pluck('name')->toArray());

            // Update active role
            $newRoleIds = $roles->pluck('id')->toArray();

            if ($currentActiveRoleId && in_array($currentActiveRoleId, $newRoleIds)) {
                // Active role is still in the new list — keep it
                // No change needed
            } elseif (count($newRoleIds) === 1) {
                // Only one role — set it as active
                $user->update(['active_role_id' => $newRoleIds[0]]);
            } else {
                // Multiple roles and active role was removed — set to null
                // User will be prompted to select on next login
                $user->update(['active_role_id' => null]);
            }

            return $user->fresh();
        });
    }

    /**
     * Get all roles assigned to a user.
     */
    public function getUserRoles(User $user): Collection
    {
        return $user->roles;
    }

    /**
     * Check if a user has multiple roles assigned.
     */
    public function hasMultipleRoles(User $user): bool
    {
        return $user->roles->count() > 1;
    }

    /**
     * Set the active role for a user's session.
     *
     * Validates that the user actually has the role.
     * Updates the active_role_id on the user model.
     * Also updates the session if the user is currently logged in.
     *
     * @param User $user
     * @param Role $role
     * @return User
     * @throws ValidationException if the user does not have the role
     */
    public function setActiveRole(User $user, Role $role): User
    {
        if (!$user->hasRole($role)) {
            throw ValidationException::withMessages([
                'role' => __('You do not have this role assigned.'),
            ]);
        }

        $user->update(['active_role_id' => $role->id]);

        // Update session if this is the currently authenticated user
        if (auth()->id() === $user->id) {
            session()->put('active_role_id', $role->id);
        }

        return $user->fresh();
    }

    /**
     * Get the active role for a user.
     *
     * @param User $user
     * @return Role|null
     */
    public function getActiveRole(User $user): ?Role
    {
        if (!$user->active_role_id) {
            return null;
        }

        return $user->activeRole;
    }

    /**
     * Determine the default active role for a user.
     *
     * If the user has one role, return it.
     * If the user has multiple roles, return null (user must select).
     * If the user has no roles, return null (invalid state).
     *
     * @param User $user
     * @return Role|null
     */
    public function determineDefaultActiveRole(User $user): ?Role
    {
        $roles = $user->roles;

        if ($roles->count() === 1) {
            return $roles->first();
        }

        return null;
    }
}
