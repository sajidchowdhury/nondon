<?php

namespace App\Modules\Identity\Http\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    /**
     * The current user.
     */
    public $user;

    /**
     * The active role.
     */
    public $activeRole;

    /**
     * The assigned branch.
     */
    public $assignedBranch;

    /**
     * Initialize the component.
     */
    public function mount(): void
    {
        $this->user = Auth::user();
        $this->activeRole = $this->user?->activeRole;
        $this->assignedBranch = $this->user?->assignedBranch;
    }

    /**
     * Logout the current user.
     */
    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return $this->redirect(route('login'), navigate: true);
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.auth.dashboard')
            ->layout('layouts.app');
    }
}
