<?php

declare(strict_types=1);
use Tardis\Models\ActivityLog;
use Tardis\Models\Media;
use Tardis\Models\Permission;
use Tardis\Models\Role;

return [

    /*
    |--------------------------------------------------------------------------
    | Application name
    |--------------------------------------------------------------------------
    */

    'name' => env('TARDIS_NAME', env('APP_NAME')),

    /*
    |--------------------------------------------------------------------------
    | Admin panel route prefix
    |--------------------------------------------------------------------------
    */

    'admin' => [
        'prefix' => env('TARDIS_ADMIN_PREFIX', 'admin'),
        'middleware' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */

    'url' => env('TARDIS_URL', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Application version
    |--------------------------------------------------------------------------
    */

    'version' => '2.0.0',

    /*
    |--------------------------------------------------------------------------
    | Page settings
    |--------------------------------------------------------------------------
    */

    'pages' => [
        'namespace' => 'Tardis\\Http\\Livewire',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database settings
    |--------------------------------------------------------------------------
    */

    'database' => [
        'hidden_tables' => [
            'migrations',
            'failed_jobs',
            'password_resets',
            'password_reset_tokens',
            'personal_access_tokens',
            'sessions',
            'cache',
            'jobs',
            'job_batches',
        ],
    ],

    'plugins' => null,

    /*
    |--------------------------------------------------------------------------
    | Authorization settings
    |--------------------------------------------------------------------------
    */

    'authorization' => [
        'enabled' => true,
        'super_admin_roles' => ['super-admin'],
    ],

    'media' => null,

    /*
    |--------------------------------------------------------------------------
    | Activity log settings
    |--------------------------------------------------------------------------
    */

    'activity_log' => [
        'enabled' => true,
        'log_events' => true,
        'prune' => env('TARDIS_ACTIVITY_LOG_PRUNE_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model settings
    |--------------------------------------------------------------------------
    */

    'models' => [
        'role' => Role::class,
        'permission' => Permission::class,
        'media' => Media::class,
        'activity_log' => ActivityLog::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | BREAD settings
    |--------------------------------------------------------------------------
    */

    'bread' => [
        'path' => storage_path('tardis/bread'),
        'backup_keep' => 10,
        'soft_deletes' => null,
        'timestamps' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | System settings
    |--------------------------------------------------------------------------
    */

    'system' => [
        'logs' => [
            'path' => storage_path('logs'),
            'filename_pattern' => '/^(laravel|laravel-.+)\.log$/',
            'tail' => 200,
            'max_bytes' => 10 * 1024 * 1024,
        ],
        'commands' => [
            'enabled' => env('TARDIS_COMMAND_RUNNER_ENABLED', false),
            'environments' => ['local'],
            'allowlist' => [
                // ['php' => ['-v']],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Themes settings
    |--------------------------------------------------------------------------
    */

    'themes' => [
        'light' => 'tardis-light',
        'dark' => 'tardis-dark',
        'auto' => 'tardis-dark',
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance settings
    |--------------------------------------------------------------------------
    */

    'appearance' => [
        'default_mode' => env('TARDIS_APPEARANCE_MODE', 'auto'),
        'logo' => env('TARDIS_LOGO', 'images/logo.svg'),
        'favicon' => env('TARDIS_FAVICON', 'images/favicon.svg'),
        'loader' => env('TARDIS_LOADER', 'images/logo.svg'),
        'custom_css' => env('TARDIS_CUSTOM_CSS', ''),
        'brand_title' => env('TARDIS_BRAND_TITLE', env('APP_NAME')),
    ],

    'assets' => [
        'css' => [],
        'js' => [],
    ],

];
