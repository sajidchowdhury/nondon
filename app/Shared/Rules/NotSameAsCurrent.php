<?php

namespace App\Shared\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

/**
 * Rule: Password must not be the same as the current password.
 *
 * Used during self-service password change and forced password change
 * to ensure the user picks a genuinely new password.
 */
class NotSameAsCurrent implements Rule
{
    /**
     * The user whose password is being changed.
     * If null, uses the authenticated user.
     */
    protected ?\App\Modules\Identity\Models\User $user;

    public function __construct(?\App\Modules\Identity\Models\User $user = null)
    {
        $this->user = $user ?? Auth::user();
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (!$this->user || !$value) {
            return true;
        }

        // If the user has no password set yet (e.g., new account), allow any
        if (!$this->user->getAuthPassword()) {
            return true;
        }

        return !Hash::check($value, $this->user->getAuthPassword());
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return __('The new password cannot be the same as your current password.');
    }
}
