<?php

namespace App\Modules\Identity\Http\Livewire\Password;

use App\Modules\Identity\Services\AuthService;
use App\Shared\PasswordRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Forced password change component.
 *
 * Shown when a user logs in with force_password_change = true.
 * This happens after:
 * - Admin password reset (force_password_change is set to true)
 * - First login with default credentials
 * - Admin manually forcing a password change
 *
 * Does NOT require the current password — the user has already authenticated.
 * The ForcePasswordChangeMiddleware prevents access to other pages until
 * the password is changed.
 */
class ForcePasswordChange extends Component
{
    /**
     * New password.
     */
    public string $password = '';

    /**
     * New password confirmation.
     */
    public string $password_confirmation = '';

    /**
     * The user being forced to change their password.
     */
    protected $user;

    /**
     * Initialize the component.
     */
    public function mount(): void
    {
        $this->user = Auth::user();

        // If user doesn't need to change password, redirect to dashboard
        if ($this->user && ! $this->user->force_password_change) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    /**
     * Validation rules.
     */
    protected function rules(): array
    {
        return PasswordRules::forcedChangeRules(Auth::user());
    }

    /**
     * Validation messages.
     */
    protected function messages(): array
    {
        return PasswordRules::messages();
    }

    /**
     * Change the password (forced flow — no current password required).
     */
    public function change(AuthService $authService)
    {
        $validated = $this->validate();

        try {
            $authService->changePasswordForced($validated['password']);

            $this->reset(['password', 'password_confirmation']);

            session()->flash('success', __('Password changed successfully. Welcome to Nondon.'));

            // Redirect to dashboard (or role select if multi-role)
            $user = Auth::user()->fresh();
            if ($user->roles->count() > 1 && ! $user->active_role_id) {
                return $this->redirect(route('role.select'), navigate: true);
            }

            return $this->redirect(route('dashboard'), navigate: true);

        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.identity.password.force-change')
            ->layout('layouts.auth');
    }
}
