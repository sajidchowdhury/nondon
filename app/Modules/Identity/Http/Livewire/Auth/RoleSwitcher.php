<?php

namespace App\Modules\Identity\Http\Livewire\Auth;

use Livewire\Component;
use App\Modules\Identity\Services\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\ValidationException;

class RoleSwitcher extends Component
{
    /**
     * The selected role ID.
     *
     * This is a simple scalar and is safe to expose as a public Livewire
     * property (it is wired to the radio inputs via wire:model).
     */
    public ?int $selectedRoleId = null;

    /**
     * Initialize the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        // Pre-select the current active role, or the first available.
        // Roles themselves are loaded in render() — see the note there about
        // why they must NOT be a public Livewire property (Eloquent Collection
        // dehydration throws "getMorphClass does not exist").
        $roles = $user->roles->load('permissions');
        $this->selectedRoleId = $user->active_role_id ?? ($roles->first()?->id);
    }

    /**
     * Switch to the selected role.
     *
     * Uses RoleAssignmentService for proper role switching with validation.
     * Stores the active role in session for middleware access.
     * Redirects to dashboard with confirmation toast.
     *
     * @param RoleAssignmentService $roleAssignmentService
     */
    public function switchRole(RoleAssignmentService $roleAssignmentService)
    {
        $this->validate([
            'selectedRoleId' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $user = Auth::user();
        $role = Role::find($this->selectedRoleId);

        try {
            $roleAssignmentService->setActiveRole($user, $role);

            // Store in session for middleware access
            session()->put('active_role_id', $role->id);
            session()->put('active_role_name', $role->name);

            // Flash confirmation message
            session()->flash('success', __('Switched to :role role.', [
                'role' => $role->display_name ?? $role->name,
            ]));

            // TODO: Session 1.4 — Audit log the role switch event
            // Event::dispatch(new RoleSwitched($user, $role));

            return $this->redirect(route('dashboard'), navigate: true);

        } catch (ValidationException $e) {
            $this->addError('selectedRoleId', $e->getMessage());
        }
    }

    /**
     * Get the display name for a role.
     */
    public function getRoleDisplayName($role): string
    {
        return $role->display_name ?? ucfirst(str_replace('_', ' ', $role->name));
    }

    /**
     * Render the component.
     */
    public function render()
    {
        // Load roles here (not as a public property) to avoid Livewire's
        // EloquentCollectionSynth dehydration error.
        $user = Auth::user();
        $roles = $user ? $user->roles->load('permissions') : collect();

        return view('livewire.auth.role-switcher', [
            'roles' => $roles,
        ])->layout('layouts.auth');
    }
}
