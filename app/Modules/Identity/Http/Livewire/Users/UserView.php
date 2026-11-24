<?php

namespace App\Modules\Identity\Http\Livewire\Users;

use Livewire\Component;
use App\Modules\Identity\Models\User;

class UserView extends Component
{
    /**
     * The user being viewed.
     */
    public User $user;

    /**
     * Initialize the component.
     */
    public function mount(User $user): void
    {
        $this->authorize('view', $user);
        $this->user = $user->load(['assignedBranch', 'roles', 'lockedByUser']);
    }

    /**
     * Render the component.
     */
    public function render()
    {
        // Get recent login attempts for this user
        $loginAttempts = $this->user->loginAttempts()
            ->orderBy('attempted_at', 'desc')
            ->limit(20)
            ->get();

        return view('livewire.identity.users.view', [
            'loginAttempts' => $loginAttempts,
        ])
        ->layout('layouts.app');
    }
}
