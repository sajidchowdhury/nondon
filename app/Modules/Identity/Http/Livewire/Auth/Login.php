<?php

namespace App\Modules\Identity\Http\Livewire\Auth;

use Livewire\Component;
use App\Modules\Identity\Services\AuthService;
use Illuminate\Support\Facades\Auth;

class Login extends Component
{
    /**
     * The username input.
     */
    public string $username = '';

    /**
     * The password input.
     */
    public string $password = '';

    /**
     * Remember me flag.
     */
    public bool $remember = false;

    /**
     * Validation rules.
     */
    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * Validation messages.
     */
    protected function messages(): array
    {
        return [
            'username.required' => __('Username is required.'),
            'username.min' => __('Username must be at least 3 characters.'),
            'password.required' => __('Password is required.'),
            'password.min' => __('Password must be at least 8 characters.'),
        ];
    }

    /**
     * Attempt to log in.
     */
    public function login(AuthService $authService)
    {
        $this->validate();

        try {
            $user = $authService->login(
                username: $this->username,
                password: $this->password,
                remember: $this->remember,
            );

            // If user has multiple roles and no active role set, show role selector
            if ($user->roles->count() > 1 && !$user->active_role_id) {
                return $this->redirect(route('role.select'), navigate: true);
            }

            return $this->redirect(route('dashboard'), navigate: true);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('username', $e->getMessage());
        } catch (\Exception $e) {
            $this->addError('username', __('An error occurred during login. Please try again.'));
        }
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.auth.login')
            ->layout('layouts.auth');
    }
}
