<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Prefix
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'prefix' => 'admin',
        'middleware' => ['web', 'auth', 'verified'],
    ],

    /*
    |--------------------------------------------------------------------------
    | BREAD (Browse/Read/Edit/Add/Delete) Settings
    |--------------------------------------------------------------------------
    */
    'bread' => [
        'soft_deletes' => true,
        'timestamps' => true,
        // Directory where runtime BREAD definitions are stored as JSON files.
        // When null, defaults to storage_path('tardis/bread').
        'path' => null,
        // How many timestamped backups to keep per definition.
        'backup_keep' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Explorer
    |--------------------------------------------------------------------------
    |
    | Tables the explorer never lists, opens, alters or drops. Tables whose name
    | starts with "tardis_" belong to this package and are always hidden too.
    |
    */
    'database' => [
        'hidden_tables' => [
            'migrations',
            'password_resets',
            'password_reset_tokens',
            'failed_jobs',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'personal_access_tokens',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional assets
    |--------------------------------------------------------------------------
    |
    | Extra stylesheet and script URLs loaded on every admin page, after the
    | package's own assets (Voyager's additional_css / additional_js). Plugins can
    | still provide inline CSS/JS through the CSS and JS provider contracts.
    |
    */
    'assets' => [
        'css' => [],
        'js' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Settings
    |--------------------------------------------------------------------------
    */
    'media' => [
        'disk' => 'public',
        'path' => 'media',
        'max_size' => 10240, // KB
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization / Translation Settings
    |--------------------------------------------------------------------------
    */
    // Locales available for translatable BREAD fields. A field may override
    // this list with its own "locales" key. When empty, translatable fields
    // fall back to the application's current locale.
    'locales' => [],

    /*
    |--------------------------------------------------------------------------
    | Plugin Settings
    |--------------------------------------------------------------------------
    */
    'plugins' => [
        'enabled' => [],
        'disabled' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Settings
    |--------------------------------------------------------------------------
    |
    | BREAD pages ("{action} {slug}" abilities, e.g. "browse posts") are
    | checked against the enabled AuthorizationPlugin. TardisAuthorizationPlugin
    | reads the tardis_roles / tardis_permission_role / tardis_permissions
    | tables, so roles you assign on the Roles page are what grant access.
    |
    | The roles listed below bypass every ability check.
    |
    */
    'authorization' => [
        // When true (default) TardisAuthorizationPlugin is registered and enabled:
        // a logged-in user needs the "access admin" ability — through a role — to
        // open the panel. Create the first administrator with
        // `php artisan tardis:admin you@example.com`. Set to false only if you
        // register your own AuthorizationPlugin or protect the panel another way:
        // with no authorization plugin every authenticated user is allowed in.
        'enabled' => true,
        'super_admin_roles' => ['super-admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log Settings
    |--------------------------------------------------------------------------
    */
    'activity_log' => [
        'enabled' => true,
        'log_events' => [
            'created',
            'updated',
            'deleted',
        ],
    ],

];
