<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\LoginAttempt;
use App\Shared\PasswordRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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

        // Regenerate session to prevent session fixation.
        // Guard with hasSession() so the AuthService can be used outside of a
        // full HTTP request lifecycle (e.g., direct service calls in tests or
        // console commands) without throwing "Session store not set on request."
        $request = request();
        if ($request->hasSession()) {
            $request->session()->regenerate(true);
        }
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

        // Guard session calls for non-HTTP contexts (tests, console).
        $request = request();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * Validate a password against all security rules.
     *
     * Rules:
     * - Minimum 8 characters
     * - Must contain at least one letter and one number
     * - Must not be a commonly-used weak password
     * - Must not be the same as the user's current password (if user is provided)
     *
     * @param string $password
     * @param User|null $user
     * @throws ValidationException
     */
    public function validatePassword(string $password, ?User $user = null): void
    {
        $rules = PasswordRules::rules($user, false);

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $password],
            $rules,
            PasswordRules::messages()
        );

        $validator->validate();
    }

    /**
     * Reset a user's password (admin action).
     *
     * Validates the new password against security rules.
     * Sets force_password_change = true so user must change it on next login.
     * Also clears any lockout state.
     *
     * @param User $user
     * @param string $newPassword
     * @return User
     * @throws ValidationException if password fails validation
     */
    public function resetPassword(User $user, string $newPassword): User
    {
        // Validate the new password (skip not-same-as-current for admin reset)
        $this->validatePassword($newPassword, $user);

        $user->update([
            'password_hash' => Hash::make($newPassword),
            'password_changed_at' => now(),
            'force_password_change' => true,
            'failed_login_attempts' => 0,
            'locked_at' => null,
            'locked_by' => null,
        ]);

        return $user->fresh();
    }

    /**
     * Change the current user's password (self-service).
     *
     * Requires the current password for verification.
     * Validates the new password against security rules.
     * Clears the force_password_change flag.
     *
     * @param string $currentPassword
     * @param string $newPassword
     * @return User
     * @throws ValidationException if current password is wrong or new password fails validation
     */
    public function changePassword(string $currentPassword, string $newPassword): User
    {
        $user = Auth::user();

        if (!$user) {
            throw ValidationException::withMessages([
                'current_password' => __('Not authenticated.'),
            ]);
        }

        // Verify current password
        if (!Hash::check($currentPassword, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => __('Current password is incorrect.'),
            ]);
        }

        // Validate new password (includes not-same-as-current check)
        $this->validatePassword($newPassword, $user);

        $user->update([
            'password_hash' => Hash::make($newPassword),
            'password_changed_at' => now(),
            'force_password_change' => false,
        ]);

        return $user->fresh();
    }

    /**
     * Change the current user's password without requiring the current password.
     *
     * Used for the forced password change flow (after admin reset or first login).
     * The ForcePasswordChangeMiddleware ensures this is only called when
     * force_password_change = true.
     *
     * @param string $newPassword
     * @return User
     * @throws ValidationException if not authenticated or password fails validation
     */
    public function changePasswordForced(string $newPassword): User
    {
        $user = Auth::user();

        if (!$user) {
            throw ValidationException::withMessages([
                'password' => __('Not authenticated.'),
            ]);
        }

        // Validate new password (includes not-same-as-current check)
        $this->validatePassword($newPassword, $user);

        $user->update([
            'password_hash' => Hash::make($newPassword),
            'password_changed_at' => now(),
            'force_password_change' => false,
        ]);

        return $user->fresh();
    }

    /**
     * Force a user to change their password on next login.
     *
     * Sets the force_password_change flag without changing the password.
     * Used when admin wants to require a password change for security reasons
     * without resetting the password themselves.
     *
     * @param User $user
     * @return User
     */
    public function forcePasswordChange(User $user): User
    {
        $user->update(['force_password_change' => true]);

        return $user->fresh();
    }

    /**
     * Unlock a user account (admin action).
     *
     * Clears the lockout state and resets failed login attempts.
     *
     * @param User $user
     * @return User
     */
    public function unlockAccount(User $user): User
    {
        $user->unlock();

        return $user->fresh();
    }
}
