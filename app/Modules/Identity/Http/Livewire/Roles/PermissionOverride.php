<?php

namespace App\Modules\Identity\Http\Livewire\Roles;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionOverrideService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

class PermissionOverride extends Component
{
    /**
     * The user whose permission overrides are being managed.
     */
    public User $user;

    /**
     * Selected permission ID to grant or revoke.
     */
    public ?int $selectedPermissionId = null;

    /**
     * Action: 'grant' or 'revoke'.
     */
    public string $action = 'grant';

    /**
     * Optional reason for the override.
     */
    public string $reason = '';

    /**
     * Available permissions for override.
     */
    public $availablePermissions;

    /**
     * Current grants and revokes.
     */
    public $grants;

    public $revokes;

    /**
     * Permission override service.
     */
    protected PermissionOverrideService $overrideService;

    /**
     * Initialize the component.
     */
    public function mount(User $user): void
    {
        $this->user = $user->fresh();
        $this->overrideService = app(PermissionOverrideService::class);
        $this->refreshData();
    }

    /**
     * Refresh the permission data.
     */
    protected function refreshData(): void
    {
        $summary = $this->overrideService->getOverrideSummary($this->user);
        $this->grants = $summary['grants'];
        $this->revokes = $summary['revokes'];

        // Get permissions not yet overridden
        $overriddenIds = $this->grants->merge($this->revokes)->pluck('permission_id')->toArray();
        $this->availablePermissions = Permission::whereNotIn('id', $overriddenIds)
            ->orderBy('module')
            ->orderBy('name')
            ->get();
    }

    /**
     * Apply the override (grant or revoke).
     */
    public function applyOverride(PermissionOverrideService $service)
    {
        $this->authorize('assignRoles', $this->user);

        $this->validate([
            'selectedPermissionId' => ['required', 'exists:permissions,id'],
            'action' => ['required', 'in:grant,revoke'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $permission = Permission::find($this->selectedPermissionId);

        try {
            if ($this->action === 'grant') {
                $service->grantPermission($this->user, $permission, $this->reason ?: null);
                session()->flash('success', __('Permission :permission granted.', [
                    'permission' => $permission->display_name ?? $permission->name,
                ]));
            } else {
                $service->revokePermission($this->user, $permission, $this->reason ?: null);
                session()->flash('success', __('Permission :permission revoked.', [
                    'permission' => $permission->display_name ?? $permission->name,
                ]));
            }

            $this->user = $this->user->fresh();
            $this->selectedPermissionId = null;
            $this->reason = '';
            $this->refreshData();

        } catch (ValidationException $e) {
            $this->addError('selectedPermissionId', $e->getMessage());
        }
    }

    /**
     * Remove an override entirely (restore to role-based behavior).
     */
    public function removeOverride(int $permissionId, PermissionOverrideService $service)
    {
        $this->authorize('assignRoles', $this->user);

        $permission = Permission::find($permissionId);

        if (! $permission) {
            return;
        }

        $service->removeOverride($this->user, $permission);

        $this->user = $this->user->fresh();
        $this->refreshData();

        session()->flash('success', __('Override for :permission removed.', [
            'permission' => $permission->display_name ?? $permission->name,
        ]));
    }

    /**
     * Check if a permission is admin-only.
     */
    public function isAdminOnlyPermission(string $permissionName): bool
    {
        return app(PermissionOverrideService::class)->isAdminOnlyPermission($permissionName);
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.identity.roles.override', [
            'grants' => $this->grants,
            'revokes' => $this->revokes,
            'availablePermissions' => $this->availablePermissions,
        ]);
    }
}
