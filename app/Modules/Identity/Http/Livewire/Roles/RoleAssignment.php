<?php

namespace App\Modules\Identity\Http\Livewire\Roles;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\RoleAssignmentService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class RoleAssignment extends Component
{
    /**
     * The user whose roles are being managed.
     */
    public User $user;

    /**
     * Selected role ID to assign.
     */
    public ?int $selectedRoleId = null;

    /**
     * Available roles (not yet assigned to the user).
     */
    public $availableRoles;

    /**
     * Initialize the component.
     */
    public function mount(User $user): void
    {
        $this->user = $user->fresh();
        $this->refreshAvailableRoles();
    }

    /**
     * Refresh the available (unassigned) roles.
     */
    protected function refreshAvailableRoles(): void
    {
        $assignedRoleIds = $this->user->roles->pluck('id')->toArray();
        $this->availableRoles = Role::whereNotIn('id', $assignedRoleIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Assign the selected role to the user.
     */
    public function assignRole(RoleAssignmentService $service)
    {
        $this->authorize('assignRoles', $this->user);

        $this->validate([
            'selectedRoleId' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::find($this->selectedRoleId);

        try {
            $this->user = $service->assignRole($this->user, $role);
            $this->selectedRoleId = null;
            $this->refreshAvailableRoles();

            session()->flash('success', __('Role :role assigned successfully.', ['role' => $role->display_name ?? $role->name]));

            $this->dispatch('role-updated');

        } catch (ValidationException $e) {
            $this->addError('selectedRoleId', $e->getMessage());
        }
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(int $roleId, RoleAssignmentService $service)
    {
        $this->authorize('assignRoles', $this->user);

        $role = Role::find($roleId);

        if (! $role) {
            $this->addError('roles', __('Role not found.'));

            return;
        }

        try {
            $this->user = $service->removeRole($this->user, $role);
            $this->refreshAvailableRoles();

            session()->flash('success', __('Role :role removed.', ['role' => $role->display_name ?? $role->name]));

            $this->dispatch('role-updated');

        } catch (ValidationException $e) {
            $this->addError('roles', $e->getMessage());
        }
    }

    /**
     * Set a role as the active role for the user.
     */
    public function setActiveRole(int $roleId, RoleAssignmentService $service)
    {
        $this->authorize('assignRoles', $this->user);

        $role = Role::find($roleId);

        if (! $role) {
            $this->addError('roles', __('Role not found.'));

            return;
        }

        try {
            $this->user = $service->setActiveRole($this->user, $role);

            session()->flash('success', __('Active role set to :role.', ['role' => $role->display_name ?? $role->name]));

        } catch (ValidationException $e) {
            $this->addError('roles', $e->getMessage());
        }
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.identity.roles.assignment', [
            'userRoles' => $this->user->fresh()->roles,
        ]);
    }
}
