<?php

namespace App\Shared\Rules;

use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
    protected ?User $user;

    public function __construct(?User $user = null)
    {
        $this->user = $user ?? Auth::user();
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        if (! $this->user || ! $value) {
            return true;
        }

        // If the user has no password set yet (e.g., new account), allow any
        if (! $this->user->getAuthPassword()) {
            return true;
        }

        return ! Hash::check($value, $this->user->getAuthPassword());
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return __('The new password cannot be the same as your current password.');
    }
}
