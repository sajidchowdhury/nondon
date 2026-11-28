<?php

use App\Modules\Identity\Models\User;

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'sanctum',
            'provider' => null,
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 0,
        ],
    ],

    'password_timeout' => 10800,

    // Nondon-specific auth settings
    'login' => [
        'max_attempts' => env('NONDON_LOGIN_MAX_ATTEMPTS', 5),
        'lockout_message' => 'Account is locked due to too many failed attempts. Contact administrator.',
    ],

];
