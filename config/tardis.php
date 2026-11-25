<?php

use Tardis\Models\ActivityLog;
use Tardis\Models\Media;
use Tardis\Models\Permission;
use Tardis\Models\Role;

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Prefix
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'prefix' => 'admin',
        // Before anyone has chosen a language, follow the browser's
        // Accept-Language when the panel has that translation.
        'browser_locale' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | BREAD (Browse/Read/Edit/Add/Delete) Settings
    |--------------------------------------------------------------------------
    */
    /*
    | Where runtime state lives: settings, themes, the admin menu overlay, site
    | menus, the dashboard layout, plugin switches, user preferences and BREAD
    | definitions. "file" (default) keeps JSON files under storage/tardis;
    | "database" keeps them in the tardis_state table, so every server of a
    | multi-server or ephemeral (container) deploy shares them. Copy existing
    | state across with `php artisan tardis:state:copy --to=database`.
    */
    'state' => [
        'driver' => env('TARDIS_STATE_DRIVER', 'file'),
        // Database connection for the "database" driver; null = the default.
        'connection' => env('TARDIS_STATE_CONNECTION'),
    ],

    /*
    | A JSON API over the BREAD definitions: GET {prefix} lists the BREADs,
    | GET/POST {prefix}/{slug}, GET/PUT/PATCH/DELETE {prefix}/{slug}/{id}.
    | Same validation, scope, owner_column, field abilities and authorization
    | as the panel; the user also needs "access admin". Off until enabled.
    */
    'api' => [
        'enabled' => env('TARDIS_API', false),
        'prefix' => env('TARDIS_API_PREFIX', 'api/tardis'),
        // How a client signs in. auth:sanctum needs laravel/sanctum; any
        // guard works ("auth:api", or "web" + "auth" for the same session).
        'middleware' => ['api', 'auth:sanctum', 'throttle:60,1'],
        'per_page' => 15,
        'max_per_page' => 100,
        // Limit the API to these slugs; null exposes every BREAD.
        'only' => null,
    ],

    'bread' => [
        // Directory where runtime BREAD definitions are stored as JSON files.
        // When null, defaults to storage_path('tardis/bread').
        'path' => null,
        // How many timestamped backups to keep per definition.
        'backup_keep' => 10,
        // The CSV export button on every BREAD list (anyone who may browse
        // the BREAD may export what they see).
        'export' => true,
        // Excel (.xlsx) next to CSV, when openspout/openspout is installed.
        'xlsx' => true,
        // The most records a bulk action runs on with "select all matching".
        'bulk_limit' => 1000,
        // CSV import on BREAD lists, for users who may add or edit records:
        // at most this many rows and this size per file.
        'import' => true,
        'import_limit' => 1000,
        'import_max_kb' => 5120,
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
        // Where the explorer may change the schema (create, alter and drop
        // tables and columns). Everywhere else it is read-only and the schema
        // belongs to migrations. Every change is written to the activity log.
        'schema_changes' => [
            'enabled' => true,
            'environments' => ['local', 'testing'],
        ],

        // How a change on the Database screen reaches the schema:
        //   "migrate" (default) writes a migration under `path` and runs it,
        //   so the change is in the migrations table and in version control;
        //   "write" only writes the file (run `php artisan migrate` yourself);
        //   "off" alters the schema directly, with no file (pre-1.x behaviour).
        'migrations' => [
            'mode' => env('TARDIS_DATABASE_MIGRATIONS', 'migrate'),
            // null = database_path('migrations')
            'path' => null,
        ],

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
    | checked against the enabled AuthorizationPlugin. RoleAuthorizationPlugin
    | reads the tardis_roles / tardis_permission_role / tardis_permissions
    | tables, so roles you assign on the Roles page are what grant access.
    |
    | The roles listed below bypass every ability check.
    |
    */
    'authorization' => [
        // When true (default) RoleAuthorizationPlugin is registered and enabled:
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
    | Users
    |--------------------------------------------------------------------------
    |
    | The Users screen and the profile page work on the model of the default
    | auth provider (auth.providers.users.model). These are the columns they
    | read and write; a column the table does not have is left out.
    */
    /*
    | Two-factor authentication (TOTP). Users turn it on from their profile;
    | the issuer is the name their authenticator app shows. A QR code is
    | drawn when bacon/bacon-qr-code is installed, else the key is typed in.
    */
    'two_factor' => [
        'enabled' => env('TARDIS_TWO_FACTOR', true),
        'issuer' => env('TARDIS_TWO_FACTOR_ISSUER'),
    ],

    'users' => [
        // The type of the user columns in Tardis' tables: auto (read from the
        // user model), int, uuid, ulid or string. Change it before migrating,
        // or run php artisan tardis:user-keys afterwards.
        'key_type' => env('TARDIS_USER_KEY_TYPE', 'auto'),

        'columns' => [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log Settings
    |--------------------------------------------------------------------------
    */
    'activity_log' => [
        'enabled' => true,

        // Entries older than this many days are removed by
        // `php artisan tardis:activity:prune` (schedule it daily). null keeps
        // everything; the command then removes nothing.
        'retention_days' => env('TARDIS_ACTIVITY_RETENTION_DAYS', 365),

        'log_events' => [
            'created',
            'updated',
            'deleted',
            'restored',
            'force_deleted',
        ],

        // Additional field names whose values are replaced with [redacted] in
        // old_values/new_values. Common names (password, *_token, *_key,
        // secret, recovery_code, ...) are already covered without configuration.
        'redact' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Mapping
    |--------------------------------------------------------------------------
    |
    | The Eloquent models Tardis uses for roles, permissions, media and the
    | activity log. A host application may swap any of them for its own class,
    | as long as it extends the package's base model (Voyager's useModel).
    | A class that does not exist or does not extend the base model is refused
    | with an error naming the key, the first time the model is needed.
    |
    */
    'models' => [
        'role' => Role::class,
        'permission' => Permission::class,
        'media' => Media::class,
        'activity_log' => ActivityLog::class,
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

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Sent on panel pages, Livewire updates and the JSON API, but never over a
    | header the host already set. The CSP only restricts framing: a script
    | policy would break Livewire/Alpine and assets the host injects. HSTS is
    | left to the host's TLS decision. Set a header to null to skip it, or the
    | whole list to [] to turn the middleware off.
    |
    */
    'security' => [
        'headers' => [
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | The disk and folder of the media screen, the extensions an upload may
    | have and its size limit in KB. Livewire's own upload limit applies first
    | (README → Media uploads).
    */
    'media' => [
        'disk' => env('TARDIS_MEDIA_DISK', 'public'),
        'path' => env('TARDIS_MEDIA_PATH', 'media'),
        // svg is left out on purpose: an SVG can carry script, and the file is
        // served from the media disk as it was uploaded. Add it only for
        // uploaders you trust, or serve the disk from another domain.
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'],

        // Entries per page on the media screen and in the media picker.
        'per_page' => 60,
        'max_file_size' => env('TARDIS_MEDIA_MAX_SIZE', 10240), // KB
        // Image uploads get a thumbnail fitted inside this box (needs the GD
        // extension; without it the media screen shows the original).
        'thumbnail_size' => [
            'width' => 300,
            'height' => 300,
        ],
        'thumbnail_quality' => 85,
    ],

    /*
    |--------------------------------------------------------------------------
    | Icons
    |--------------------------------------------------------------------------
    |
    | Short names <x-tardis::icon> resolves to a Heroicon.
    */
    'icons' => [
        'aliases' => [
            'nav.dashboard' => 'heroicon-o-home',
            'nav.media' => 'heroicon-o-photo',
            'nav.ui' => 'heroicon-o-squares-2x2',
            'nav.settings' => 'heroicon-o-cog-6-tooth',
            'nav.plugins' => 'heroicon-o-puzzle-piece',
            'nav.bread' => 'heroicon-o-table-cells',
            'nav.permissions' => 'heroicon-o-lock-closed',
            'nav.roles' => 'heroicon-o-user-group',
            'action.create' => 'heroicon-o-plus',
            'action.edit' => 'heroicon-o-pencil-square',
            'action.delete' => 'heroicon-o-trash',
            'action.view' => 'heroicon-o-eye',
            'action.search' => 'heroicon-o-magnifying-glass',
            'auth.logout' => 'heroicon-o-arrow-right-on-rectangle',
            'ui.sun' => 'heroicon-o-sun',
            'ui.moon' => 'heroicon-o-moon',
            'ui.bell' => 'heroicon-o-bell',
            'ui.menu' => 'heroicon-o-bars-3',
            'ui.close' => 'heroicon-o-x-mark',

            /*
            |--------------------------------------------------------------------------
            | Raw-name fallbacks (used by icon picker / older templates)
            |--------------------------------------------------------------------------
            |
            | These map short icon names that don't follow the "heroicon-o-*" pattern
            | to their actual Heroicon outline equivalent. The <x-tardis::icon>
            | component auto-prefixes unknown names with "heroicon-o-", but icons
            | whose file name differs from the short name need an explicit alias.
            |
            */
            'database' => 'heroicon-o-circle-stack',
            'text' => 'heroicon-o-document-text',
            'toggle' => 'heroicon-o-adjustments-horizontal',
        ],
    ],

];
