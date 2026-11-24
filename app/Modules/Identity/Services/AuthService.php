<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\LoginAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * The maximum login attempts before account lockout.
     */
    protected int $maxAttempts;

    public function __construct()
    {
        $this->maxAttempts = (int) config('auth.login.max_attempts', 5);
    }

    /**
     * Attempt to log in a user with username and password.
     *
     * @param string $username
     * @param string $password
     * @param bool $remember
     * @return User
     * @throws ValidationException
     */
    public function login(string $username, string $password, bool $remember = false): User
    {
        $user = User::where('username', $username)->first();

        // User not found
        if (!$user) {
            LoginAttempt::logFailure($username, null, 'user_not_found');
            throw ValidationException::withMessages([
                'username' => __('Invalid username or password.'),
            ]);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            LoginAttempt::logFailure($username, $user->id, 'account_locked');
            throw ValidationException::withMessages([
                'username' => __(config('auth.login.lockout_message')),
            ]);
        }

        // Check if account is active
        if (!$user->is_active) {
            LoginAttempt::logFailure($username, $user->id, 'account_inactive');
            throw ValidationException::withMessages([
                'username' => __('This account has been deactivated. Contact administrator.'),
            ]);
        }

        // Validate password
        if (!Hash::check($password, $user->getAuthPassword())) {
            $user->incrementFailedAttempts($this->maxAttempts);
            LoginAttempt::logFailure($username, $user->id, 'invalid_password');

            $remaining = $this->maxAttempts - $user->fresh()->failed_login_attempts;

            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'username' => __("Invalid username or password. :remaining attempts remaining.", [
                        'remaining' => $remaining,
                    ]),
                ]);
            }

            throw ValidationException::withMessages([
                'username' => __(config('auth.login.lockout_message')),
            ]);
        }

        // Login successful
        $this->performLogin($user, $remember);

        LoginAttempt::logSuccess($username, $user->id);

        $user->resetFailedAttempts();

        // Set default active role if user has multiple roles
        $this->setDefaultActiveRole($user);

        return $user;
    }

    /**
     * Perform the Laravel Auth login and session regeneration.
     */
    protected function performLogin(User $user, bool $remember): void
    {
        Auth::login($user, $remember);

        // Regenerate session to prevent session fixation
        request()->session()->regenerate(true);
    }

    /**
     * Set the default active role for a multi-role user.
     * If the user has one role, set it as active.
     * If multiple, redirect to role selection.
     */
    protected function setDefaultActiveRole(User $user): void
    {
        $roles = $user->roles;

        if ($roles->count() === 1) {
            $user->update(['active_role_id' => $roles->first()->id]);
        } elseif ($roles->count() > 1 && !$user->active_role_id) {
            // Leave active_role_id null — user will be prompted to select
            // The RoleSwitcher component handles this
        }
    }

    /**
     * Switch the active role for the current user.
     *
     * @param int $roleId
     * @return User
     * @throws ValidationException
     */
    public function switchRole(int $roleId): User
    {
        $user = Auth::user();

        if (!$user) {
            throw ValidationException::withMessages([
                'role' => __('Not authenticated.'),
            ]);
        }

        // Verify the user has the role
        if (!$user->hasRole($roleId)) {
            throw ValidationException::withMessages([
                'role' => __('You do not have this role assigned.'),
            ]);
        }

        $user->update(['active_role_id' => $roleId]);

        return $user->fresh();
    }

    /**
     * Log out the current user.
     */
    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            // Clear active role on logout (user selects again on next login)
            $user->update(['active_role_id' => null]);
        }

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    /**
     * Reset a user's password (admin action).
     *
     * @param User $user
     * @param string $newPassword
     * @return User
     */
    public function resetPassword(User $user, string $newPassword): User
    {
        $user->update([
            'password_hash' => Hash::make($newPassword),
            'password_changed_at' => now(),
            'force_password_change' => true,
            'failed_login_attempts' => 0,
            'locked_at' => null,
            'locked_by' => null,
        ]);

        return $user;
    }

    /**
     * Change the current user's password (self-service).
     *
     * @param string $currentPassword
     * @param string $newPassword
     * @return User
     * @throws ValidationException
     */
    public function changePassword(string $currentPassword, string $newPassword): User
    {
        $user = Auth::user();

        if (!Hash::check($currentPassword, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => __('Current password is incorrect.'),
            ]);
        }

        $user->update([
            'password_hash' => Hash::make($newPassword),
            'password_changed_at' => now(),
            'force_password_change' => false,
        ]);

        return $user;
    }

    /**
     * Unlock a user account (admin action).
     *
     * @param User $user
     * @return User
     */
    public function unlockAccount(User $user): User
    {
        $user->unlock();
        return $user;
    }
}
