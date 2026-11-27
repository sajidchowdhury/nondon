<?php

namespace App\Modules\Identity\Http\Livewire\Roles;

use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionMatrix extends Component
{
    /**
     * NOTE: Roles and permissions are NOT declared as public properties.
     *
     * Livewire's EloquentCollectionSynth attempts to call getMorphClass() on
     * Illuminate\Database\Eloquent\Collection during dehydration, which throws
     * "Method Illuminate\Database\Eloquent\Collection::getMorphClass does not
     * exist". This happens because Eloquent Collections (unlike Eloquent Models)
     * are not morphable.
     *
     * To avoid this, the data is loaded fresh in render() and passed directly
     * to the view as render-time data (which Livewire does not dehydrate).
     */

    /**
     * Initialize the component.
     */
    public function mount(): void
    {
        // Only Main Branch Admin can view/manipulate the global permission matrix.
        // Using the assignRoles policy (admin-only) rather than viewAny (which
        // also allows supervisors) ensures non-admin users get a 403 here.
        $this->authorize('assignRoles', \App\Modules\Identity\Models\User::class);
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

        // No need to refresh a public property — render() reloads data fresh.
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
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('module')->orderBy('name')->get();
        $permissionsByModule = $permissions->groupBy('module');

        return view('livewire.identity.roles.permission-matrix', [
            'roles' => $roles,
            'permissionsByModule' => $permissionsByModule,
        ])->layout('layouts.app');
    }
}
