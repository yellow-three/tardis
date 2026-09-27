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
