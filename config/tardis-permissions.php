<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return [
    'enabled' => env('TARDIS_PERMISSIONS_ENABLED', true),

    'models' => [
        'permission' => Permission::class,
        'role' => Role::class,
    ],

    'super_admin_role' => env('TARDIS_SUPER_ADMIN_ROLE', 'super-admin'),

    'column_names' => [
        'role_pivot_key' => 'role_id',
        'permission_pivot_key' => 'permission_id',
        'team_foreign_key' => 'team_id',
    ],

    'team_permissions' => false,

    'display_permission_in_exception' => false,

    'cache' => [
        'expiration_time' => DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],

    'registration' => [
        'cache_store' => 'default',
    ],
];
