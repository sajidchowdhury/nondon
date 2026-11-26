<?php

namespace App\Modules\Identity\Http\Livewire\Roles;

use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionMatrix extends Component
{
    /**
     * All roles in the system.
     */
    public $roles;

    /**
     * All permissions grouped by module.
     */
    public $permissionsByModule;

    /**
     * Permission override service for checking admin-only permissions.
     */
    protected $permissionOverrideService;

    /**
     * Initialize the component.
     */
    public function mount(): void
    {
        // Only Main Branch Admin can view/manipulate the global permission matrix.
        // Using the assignRoles policy (admin-only) rather than viewAny (which
        // also allows supervisors) ensures non-admin users get a 403 here.
        $this->authorize('assignRoles', \App\Modules\Identity\Models\User::class);

        $this->roles = Role::with('permissions')->orderBy('name')->get();

        $permissions = Permission::orderBy('module')->orderBy('name')->get();

        // Group permissions by module
        $this->permissionsByModule = $permissions->groupBy('module');
    }

    /**
     * Check if a role has a permission.
     */
    public function roleHasPermission(Role $role, Permission $permission): bool
    {
        return $role->permissions->contains('id', $permission->id);
    }

    /**
     * Toggle a permission for a role.
     */
    public function togglePermission(int $roleId, int $permissionId)
    {
        $this->authorize('assignRoles', \App\Modules\Identity\Models\User::class);

        $role = Role::find($roleId);
        $permission = Permission::find($permissionId);

        if (!$role || !$permission) {
            return;
        }

        $permissionOverrideService = app(\App\Modules\Identity\Services\PermissionOverrideService::class);

        // Prevent modifying admin-only permissions on non-admin roles
        if ($permissionOverrideService->isAdminOnlyPermission($permission->name)
            && $role->name !== 'main_branch_admin') {
            session()->flash('error', __(':permission is restricted to Main Branch Admin role.', [
                'permission' => $permission->display_name ?? $permission->name,
            ]));
            return;
        }

        if ($role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);
            session()->flash('success', __('Permission :permission removed from :role.', [
                'permission' => $permission->display_name ?? $permission->name,
                'role' => $role->display_name ?? $role->name,
            ]));
        } else {
            $role->givePermissionTo($permission);
            session()->flash('success', __('Permission :permission granted to :role.', [
                'permission' => $permission->display_name ?? $permission->name,
                'role' => $role->display_name ?? $role->name,
            ]));
        }

        // Refresh roles with permissions
        $this->roles = Role::with('permissions')->orderBy('name')->get();
    }

    /**
     * Check if a permission is admin-only.
     */
    public function isAdminOnlyPermission(string $permissionName): bool
    {
        return app(\App\Modules\Identity\Services\PermissionOverrideService::class)
            ->isAdminOnlyPermission($permissionName);
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.identity.roles.permission-matrix')
            ->layout('layouts.app');
    }
}
