<?php

namespace App\Shared;

use App\Modules\Identity\Models\User;
use App\Shared\Rules\NotSameAsCurrent;
use App\Shared\Rules\NotWeakPassword;

/**
 * Provides password validation rules for use in Form Requests and Livewire components.
 *
 * Password requirements (per BRD Session 1.3):
 * - Minimum 8 characters
 * - Must contain at least one letter and one number
 * - Cannot be the same as the current password
 * - Must not be a commonly-used weak password
 */
class PasswordRules
{
    /**
     * Get the validation rules for a new password.
     *
     * @param  User|null  $user  The user whose password is being changed (for not-same-as-current check)
     * @param  bool  $requireCurrent  Whether to include the current password field
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?User $user = null, bool $requireCurrent = false): array
    {
        $rules = [
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'regex:/[a-zA-Z]/',      // must contain at least one letter
                'regex:/[0-9]/',          // must contain at least one number
                new NotWeakPassword,
                new NotSameAsCurrent($user),
            ],
            'password_confirmation' => [
                'required',
                'same:password',
            ],
        ];

        if ($requireCurrent) {
            $rules['current_password'] = [
                'required',
                'string',
            ];
        }

        return $rules;
    }

    /**
     * Get validation messages for password fields.
     */
    public static function messages(): array
    {
        return [
            'password.required' => __('Password is required.'),
            'password.min' => __('Password must be at least 8 characters.'),
            'password.max' => __('Password must not exceed 255 characters.'),
            'password.regex' => __('Password must contain at least one letter and one number.'),
            'password_confirmation.required' => __('Please confirm your password.'),
            'password_confirmation.same' => __('Password confirmation does not match.'),
            'current_password.required' => __('Current password is required.'),
        ];
    }

    /**
     * Get the rules for a forced password change (no current password required).
     */
    public static function forcedChangeRules(?User $user = null): array
    {
        return self::rules($user, false);
    }

    /**
     * Get the rules for a self-service password change (current password required).
     */
    public static function selfServiceRules(?User $user = null): array
    {
        return self::rules($user, true);
    }
}
