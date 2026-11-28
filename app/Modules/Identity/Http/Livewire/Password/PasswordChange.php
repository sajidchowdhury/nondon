<?php

namespace App\Modules\Identity\Http\Livewire\Password;

use App\Modules\Identity\Services\AuthService;
use App\Shared\PasswordRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Self-service password change component.
 *
 * Accessible by any authenticated user from the dashboard.
 * Requires the current password for verification.
 * Validates the new password against security rules.
 */
class PasswordChange extends Component
{
    /**
     * Current password (user-entered for verification).
     */
    public string $current_password = '';

    /**
     * New password.
     */
    public string $password = '';

    /**
     * New password confirmation.
     */
    public string $password_confirmation = '';

    /**
     * Validation rules.
     */
    protected function rules(): array
    {
        return PasswordRules::selfServiceRules(Auth::user());
    }

    /**
     * Validation messages.
     */
    protected function messages(): array
    {
        return PasswordRules::messages();
    }

    /**
     * Change the password.
     */
    public function change(AuthService $authService)
    {
        $validated = $this->validate();

        try {
            $authService->changePassword(
                currentPassword: $validated['current_password'],
                newPassword: $validated['password'],
            );

            $this->reset(['current_password', 'password', 'password_confirmation']);

            session()->flash('success', __('Password changed successfully.'));

            return $this->redirect(route('dashboard'), navigate: true);

        } catch (ValidationException $e) {
            // Add errors from the exception to the component
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
        return view('livewire.identity.password.change')
            ->layout('layouts.app');
    }
}
