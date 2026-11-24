<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserPermission;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PermissionOverrideService
{
    /**
     * Permissions that are ALWAYS restricted to Main Branch Admin.
     *
     * These cannot be granted to non-admin users via overrides.
     * This enforces the BRD principle: sensitive data restrictions apply
     * regardless of role combination.
     */
    protected const ADMIN_ONLY_PERMISSIONS = [
        'purchase.cost.view',
        'supplier.manage',
        'supplier.payment.record',
        'report.view.purchase_cost',
        'report.view.profit',
        'report.view.historical',
        'report.view.all_today',
        'report.view.audit',
        'employee.manage',
        'product.create',
        'product.price.update',
        'purchase.create',
        'expense.approve',
        'inventory.adjust.approve',
        'return.approve.unlimited',
        'fiscal_year.manage',
        'system_settings.manage',
    ];

    /**
     * Grant a custom permission to a user.
     *
     * If the user already has a revoke entry for this permission, it is
     * updated to a grant. If no entry exists, a new grant is created.
     *
     * @param User $user
     * @param Permission $permission
     * @param string|null $reason Optional reason for the grant
     * @return User
     * @throws ValidationException if the permission is admin-only and user is not admin
     */
    public function grantPermission(User $user, Permission $permission, ?string $reason = null): User
    {
        // Prevent granting admin-only permissions to non-admin users
        if (in_array($permission->name, self::ADMIN_ONLY_PERMISSIONS) && !$user->hasRole('main_branch_admin')) {
            throw ValidationException::withMessages([
                'permission' => __('This permission is restricted to Main Branch Admin and cannot be granted to other users.'),
            ]);
        }

        DB::transaction(function () use ($user, $permission, $reason) {
            UserPermission::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'permission_id' => $permission->id,
                ],
                [
                    'granted' => true,
                    'granted_by' => auth()->id(),
                    'granted_at' => now(),
                    'reason' => $reason,
                ]
            );
        });

        return $user->fresh();
    }

    /**
     * Revoke a permission from a user.
     *
     * If the user already has a grant entry for this permission, it is
     * updated to a revoke. If no entry exists, a new revoke is created.
     *
     * Note: Revoking a permission that the user has via their role will
     * remove it from their effective permissions.
     *
     * @param User $user
     * @param Permission $permission
     * @param string|null $reason Optional reason for the revocation
     * @return User
     */
    public function revokePermission(User $user, Permission $permission, ?string $reason = null): User
    {
        DB::transaction(function () use ($user, $permission, $reason) {
            UserPermission::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'permission_id' => $permission->id,
                ],
                [
                    'granted' => false,
                    'granted_by' => auth()->id(),
                    'granted_at' => now(),
                    'reason' => $reason,
                ]
            );
        });

        return $user->fresh();
    }

    /**
     * Remove a permission override entirely (restore to role-based behavior).
     *
     * @param User $user
     * @param Permission $permission
     * @return User
     */
    public function removeOverride(User $user, Permission $permission): User
    {
        UserPermission::where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->delete();

        return $user->fresh();
    }

    /**
     * Get all permission overrides for a user.
     */
    public function getOverrides(User $user): Collection
    {
        return UserPermission::where('user_id', $user->id)
            ->with('permission', 'grantedByUser')
            ->get();
    }

    /**
     * Compute the effective permissions for a user.
     *
     * Effective Permission = (Role Permissions UNION User Grants)
     *                       MINUS User Revokes
     *                       WITH Sensitive Data Restrictions (always applied)
     *
     * @param User $user
     * @return Collection<int, Permission>
     */
    public function getEffectivePermissions(User $user): Collection
    {
        // 1. Get all permissions from the user's roles
        $rolePermissionIds = $user->getPermissionsViaRoles()->pluck('id');

        // 2. Get user's direct grants (granted = true)
        $grantPermissionIds = UserPermission::where('user_id', $user->id)
            ->grants()
            ->pluck('permission_id');

        // 3. Get user's revokes (granted = false)
        $revokePermissionIds = UserPermission::where('user_id', $user->id)
            ->revokes()
            ->pluck('permission_id');

        // 4. Union: role permissions + grants
        $allPermissionIds = $rolePermissionIds
            ->merge($grantPermissionIds)
            ->unique();

        // 5. Minus revokes
        $effectivePermissionIds = $allPermissionIds
            ->diff($revokePermissionIds);

        // 6. Apply sensitive data restrictions: filter out admin-only permissions
        //    for non-admin users (regardless of overrides)
        if (!$user->hasRole('main_branch_admin')) {
            $adminOnlyPermissionNames = self::ADMIN_ONLY_PERMISSIONS;
            $effectivePermissionIds = $effectivePermissionIds->filter(function ($permissionId) use ($adminOnlyPermissionNames) {
                $permission = Permission::find($permissionId);
                return $permission && !in_array($permission->name, $adminOnlyPermissionNames);
            });
        }

        return Permission::whereIn('id', $effectivePermissionIds)->get();
    }

    /**
     * Check if a user has a specific effective permission.
     *
     * @param User $user
     * @param string $permissionName
     * @return bool
     */
    public function hasEffectivePermission(User $user, string $permissionName): bool
    {
        // Admin-only permissions: always false for non-admin
        if (in_array($permissionName, self::ADMIN_ONLY_PERMISSIONS) && !$user->hasRole('main_branch_admin')) {
            return false;
        }

        return $this->getEffectivePermissions($user)
            ->contains('name', $permissionName);
    }

    /**
     * Get a summary of overrides for display.
     *
     * Returns grants and revokes separately for the UI.
     *
     * @param User $user
     * @return array{grants: Collection, revokes: Collection}
     */
    public function getOverrideSummary(User $user): array
    {
        $overrides = UserPermission::where('user_id', $user->id)
            ->with('permission', 'grantedByUser')
            ->get();

        return [
            'grants' => $overrides->filter(fn ($override) => $override->granted),
            'revokes' => $overrides->filter(fn ($override) => !$override->granted),
        ];
    }

    /**
     * Get the list of admin-only permissions (for display/validation).
     */
    public function getAdminOnlyPermissions(): array
    {
        return self::ADMIN_ONLY_PERMISSIONS;
    }

    /**
     * Check if a permission is admin-only.
     */
    public function isAdminOnlyPermission(string $permissionName): bool
    {
        return in_array($permissionName, self::ADMIN_ONLY_PERMISSIONS);
    }
}
