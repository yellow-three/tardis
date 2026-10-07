<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Prefix
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'prefix' => 'admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | BREAD (Browse/Read/Edit/Add/Delete) Settings
    |--------------------------------------------------------------------------
    */
    'bread' => [
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
    | Localization / Translation Settings
    |--------------------------------------------------------------------------
    */
    // Locales available for translatable BREAD fields. A field may override
    // this list with its own "locales" key. When empty, translatable fields
    // fall back to the application's current locale.
    'locales' => [],

    'translation' => [
        /*
         * Which locales a translatable field's validation rules are applied to:
         *
         *  - "all"    every locale of the field must satisfy the rules. A
         *             "required" field then rejects a record that has only one
         *             of its translations filled in.
         *  - "active" only the locale being edited has to satisfy them, so a
         *             record can be filled in one language at a time.
         *
         * A field overrides this with its own "validation_mode" key.
         */
        'validation' => 'all',

        /*
         * Whether the create/edit pages show one control per locale as tabs.
         * With this off, every locale's control is stacked and labelled, which
         * is what the layout looked like before tabs existed.
         */
        'tabs' => true,
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

    /*
    |--------------------------------------------------------------------------
    | System Settings
    |--------------------------------------------------------------------------
    |
    | Backs the System screen: the read-only log viewer and the Artisan command
    | runner. Both are diagnostics, so the defaults are the restrictive ones.
    |
    */
    'system' => [
        'logs' => [
            // Directory the log viewer reads. Relative paths resolve against the
            // application's storage path; null means storage_path('logs').
            'path' => null,
            // Only files matching this pattern can be opened, so a crafted name
            // cannot walk out of the log directory.
            'filename_pattern' => '/^[A-Za-z0-9._-]+\.log$/',
            // How many trailing lines a single read returns.
            'tail' => 200,
            // Hard ceiling on the bytes one read may touch. The reader seeks to
            // the end of the file, so a huge log costs a seek, not a full load.
            'max_bytes' => 262144,
        ],

        'commands' => [
            // The runner is off until you turn it on. A published config file
            // must never be able to expose artisan to a browser by accident.
            'enabled' => false,
            // Even when enabled, the runner stays closed unless the app runs in
            // one of these environments.
            'environments' => ['local'],
            // The only commands that may run, as ['name' => [...allowed options]].
            // An option is '--flag' or '--option=value'; positional arguments are
            // not supported. Example: ['queue:work' => ['--once', '--queue=default']].
            // An empty list means nothing is allowed, so enabling alone is not
            // enough to make a command reachable.
            'allowlist' => [],
        ],

        'demo' => [
            // Demo seeder is opt-in and only runs in local/testing environments.
            // This switch must remain false by default to avoid seeding non-local
            // environments.
            'enabled' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | Inline <style>/<script> blocks the panel writes carry this nonce so a strict
    | policy can allow them. Leave null to use Laravel's Vite nonce (if you set
    | one with Vite::useCspNonce()); a closure returning the nonce also works.
    */
    'csp' => [
        'nonce' => null,
    ],

];
