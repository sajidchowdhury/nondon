<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return [

    'models' => [

        /*
         * The model you want to use as a Role model.
         */
        'role' => Role::class,

        /*
         * The model you want to use as a Permission model.
         */
        'permission' => Permission::class,

    ],

    'table_names' => [

        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',

    ],

    'column_names' => [

        'role_pivot_key' => 'role_id',
        'permission_pivot_key' => 'permission_id',
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',

    ],

    'register_permission_check_method' => true,

    'teams' => false,

    'use_passport_client_credentials' => false,

    'display_permission_in_exception' => false,

    'display_role_in_exception' => false,

    'enable_wildcard_permission' => false,

    'cache' => [

        'enabled' => env('PERMISSION_CACHE_ENABLED', true),

        'store' => env('PERMISSION_CACHE_STORE', 'default'),

        'expiration_time' => env('PERMISSION_CACHE_EXPIRATION_TIME', 86400),

        'key' => 'spatie.permission.cache',

        'model_key' => 'spatie.permission.cache.model.{model}',

    ],

];
