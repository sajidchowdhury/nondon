<?php

namespace App\Modules\Identity\Http\Livewire\Auth;

use Livewire\Component;
use App\Modules\Identity\Services\AuthService;
use Illuminate\Support\Facades\Auth;

class RoleSwitcher extends Component
{
    /**
     * The available roles for the user.
     */
    public $roles = [];

    /**
     * The selected role ID.
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

        $this->roles = $user->roles;
        $this->selectedRoleId = $user->active_role_id ?? ($this->roles->first()?->id);
    }

    /**
     * Switch to the selected role.
     */
    public function switchRole(AuthService $authService)
    {
        $this->validate([
            'selectedRoleId' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $user = $authService->switchRole($this->selectedRoleId);

        return $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.auth.role-switcher')
            ->layout('layouts.auth');
    }
}
