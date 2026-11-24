<?php

namespace App\Shared\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Rule: Password must not be a commonly-used weak password.
 *
 * Checks against a list of frequently-used weak passwords that should
 * never be accepted in a business system.
 */
class NotWeakPassword implements Rule
{
    /**
     * Common weak passwords that must never be accepted.
     */
    protected const WEAK_PASSWORDS = [
        'password',
        'password1',
        'password12',
        'password123',
        '12345678',
        '123456789',
        '1234567890',
        'qwerty123',
        'abc12345',
        'abcd1234',
        'iloveyou',
        'sunshine',
        'football',
        'baseball',
        'dragon',
        'master',
        'monkey',
        'shadow',
        'ashley',
        'michael',
        'nondon123',
        'nondon2025',
        'admin123',
        'admin1234',
        'letmein',
        'welcome',
        'welcome1',
        'welcome123',
        'changeme',
        'passw0rd',
        'p@ssw0rd',
        'p@ssword',
    ];

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (!$value) {
            return true;
        }

        $lowercase = strtolower($value);

        return !in_array($lowercase, self::WEAK_PASSWORDS);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return __('This password is too common. Please choose a more secure password.');
    }
}
