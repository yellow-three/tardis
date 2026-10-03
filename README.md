# Tardis

TARDIS is a Laravel admin framework package built around Livewire 4, DaisyUI and a modular plugin/menu system.

## Requirements

- PHP 8.3+
- Laravel 13+
- Livewire 4+
- Vite + Tailwind/DaisyUI for frontend styling

## Installation

```bash
composer require yellow-three/tardis
```

Publish the package configuration if needed:

```bash
php artisan vendor:publish --tag=tardis-config
```

Publish the compiled admin assets and theme manifest as well:

```bash
php artisan vendor:publish --tag=tardis-assets --force
php artisan vendor:publish --tag=tardis-themes-assets --force
```

Run these commands again after rebuilding the package assets with `npm run build`.

## Current architecture

The package follows the Livewire 4 page-first pattern:

- Static management screens are routed as Livewire pages
- Complex admin screens use the Multi-File Component (MFC) structure when they become large
- Simpler screens stay in the Single-File Component (SFC) format
- Sidebar/menu entries are derived from the MenuManager and plugin registrations
- Themes are discovered from a Vite manifest and exposed through the admin layout

This keeps the framework flexible while matching the official Livewire 4 convention:

- fixed screens like dashboard, settings, plugins, roles and permissions work as page components
- more complex screens like BREAD management and media flows can be split into MFC folders for clearer logic and template separation

## Route structure

All admin routes are Livewire page routes (`Route::livewire`) defined in `routes/admin.php`, under the `admin` prefix (`tardis.admin.prefix`) and the `tardis.` route-name prefix:

- Fixed screens
  - `/admin/dashboard`, `/admin/settings`, `/admin/plugins`
  - `/admin/media`, `/admin/activity-log`, `/admin/search`
  - `/admin/database` (database explorer: list / create / edit tables)
  - `/admin/permissions`, `/admin/roles`
  - `/admin/bread` (definitions), `/admin/bread/create` and `/admin/bread/{slug}/edit` (BREAD builder)
- Dynamic BREAD resources, declared **last** so they never shadow a fixed screen
  - `/admin/{slug}`, `/admin/{slug}/create`, `/admin/{slug}/{id}`, `/admin/{slug}/{id}/edit`

Because `/admin/{slug}` is a wildcard, any route a plugin adds under the admin prefix must be registered **before** it, or the wildcard wins. Fixed screens also include `/admin/users`.

## Authentication and authorization

Every admin route runs through the `tardis.admin` middleware. It delegates authentication to the enabled `AuthenticationPlugin` (the built-in one checks `Auth::check()` and redirects to `/admin/login`; the login form is rate limited to 5 failed attempts per email + IP) and then requires the **`access admin`** ability.

Authorization is on by default: `TardisAuthorizationPlugin` is registered and enabled, backed by the `tardis_roles` / `tardis_permissions` tables. A logged-in user who holds no role cannot open the panel, so create the first administrator after migrating:

```bash
php artisan migrate
php artisan tardis:admin you@example.com            # existing user
php artisan tardis:admin you@example.com --create   # create the user as well (password generated, or --password=...)
```

`tardis:admin` creates the `super-admin` role, provisions every permission and assigns the role; it is safe to run again. Roles listed in `tardis.authorization.super_admin_roles` (default `super-admin`) bypass every check. Further roles and their permissions are managed on the **Roles** and **Permissions** pages, and roles are assigned to people on the **Users** page.

What is checked where:

| Screen | Ability |
|---|---|
| BREAD resources | `browse` / `read` / `add` / `edit` / `delete` + the resource slug (`browse posts`); provisioned automatically when a BREAD definition is saved |
| Settings, Plugins, Users, Roles + Permissions, Database Explorer, BREAD management + builder, Activity log | `manage settings`, `manage plugins`, `manage users`, `manage roles`, `manage database`, `manage bread`, `view activity` |
| Media | `browse media`, plus `upload media`, `rename media`, `delete media` for writes |

Pages check their ability on every Livewire request, not only on mount, and the sidebar hides entries the user may not open. If the host user model already has a `hasPermissionTo()` method (for example Spatie's `HasRoles`), that answer is used instead of the TARDIS tables.

To use your own authorization, set `tardis.authorization.enabled` to `false` and register an `AuthorizationPlugin`. **With no authorization plugin at all, every authenticated user is allowed into the panel**, so only disable it if something else protects `/admin`.

## Menu system

The sidebar is built from the `MenuManager` and plugin-provided items.

```php
use Tardis\Facades\Tardis;

$menu = Tardis::menu();
```

Default entries include:

- Overview: Dashboard, Media, UI Components
- Management: Settings, Plugins, Database Explorer, BREAD (plus one entry per BREAD resource)
- Access: Permissions, Users, Roles

Entries the current user is not allowed to open are hidden. The user dropdown offers Logout, and a Profile link only when the host application defines a `profile.edit` route.

Menu items can be grouped by section and can participate in active-route detection across nested admin pages.

## Theme system

TARDIS uses a build-time manifest system for DaisyUI themes.

### How it works

During build, a Vite plugin extracts theme metadata from CSS definitions and generates a manifest at `public/tardis-assets/themes-manifest.json`. The service provider loads this manifest and the admin layout exposes it to the Alpine theme store.

### Adding a custom theme

```css
@plugin "daisyui/theme" {
  name: "my-custom-theme",
  color-scheme: dark;
  --color-primary: oklch(50% 0.2 260);
  --color-secondary: oklch(60% 0.15 180);
  --color-accent: oklch(70% 0.18 80);
  --color-base-100: oklch(25% 0.02 260);
}
```

Then rebuild assets:

```bash
npm run build
```

## BREAD usage

A BREAD definition is a JSON file under `storage/tardis/bread/{slug}.json` (configurable through `tardis.bread.path`, defaults to `storage_path('tardis/bread')`). The management screens then render list/detail/edit/create screens from that metadata instead of hardcoding one-off admin pages.

The fastest way to create one is from an existing model:

```bash
php artisan tardis:make-bread "App\Models\Post"
```

This writes `storage/tardis/bread/posts.json` with the fields detected from the model (fillable + schema nullability):

```json
{
    "slug": "posts",
    "model": "App\\Models\\Post",
    "name": "Post",
    "name_plural": "Posts",
    "fields": []
}
```

Definitions are read through the `BreadManager`:

```php
use Tardis\Bread\BreadManager;

app(BreadManager::class)->find('posts'); // ?BreadDefinition
app(BreadManager::class)->all();         // Collection of BreadDefinition
```

Every save keeps a timestamped backup (`posts.backup.{Y-m-d@H-i-s.u}.json`) and prunes older ones to `tardis.bread.backup_keep` (default 10). Backups can be restored from the BREAD management screen.

Legacy PHP definitions in `config/bread/{slug}.php` are still readable. Migrate them to JSON storage with:

```bash
php artisan tardis:bread:migrate          # write JSON copies
php artisan tardis:bread:migrate --dry-run  # preview what would be migrated
php artisan tardis:bread:migrate --force    # overwrite existing JSON with the same slug
php artisan tardis:bread:migrate --delete   # move migrated .php files to config/bread-migrated
```

Export all JSON definitions as a single document with:

```bash
php artisan tardis:bread:export --file=bread-export.json
php artisan tardis:bread:export            # print to stdout
```

## Livewire usage

Livewire is used for the admin shell and fixed page screens, for example:

```blade
<livewire:tardis::pages.dashboard />
<livewire:tardis::pages.settings />
<livewire:tardis::pages.bread.manage />
```

Larger screens (BREAD pages, BREAD builder, media browser, database explorer, settings) are Multi-File Components; small ones stay Single-File Components. Class-based components are not used. See [docs/LIVEWIRE_PAGE_ORGANIZATION.md](docs/LIVEWIRE_PAGE_ORGANIZATION.md).

## Artisan commands

| Command | Purpose |
|---|---|
| `tardis:admin {email}` | Make a user a super administrator (`--create` creates the user) |
| `tardis:make-bread {model} {slug?}` | Create a BREAD definition (JSON) from an Eloquent model |
| `tardis:make-model {table}` | Generate an Eloquent model for an existing table |
| `tardis:make-plugin {name}` | Scaffold a plugin package (`--with-menu`, `--with-widgets`, `--with-settings`, `--with-migration`, `--with-model`) |
| `tardis:bread:migrate` | Convert legacy `config/bread/*.php` definitions to JSON |
| `tardis:bread:export` | Export all JSON definitions as one document |

See [docs/PLUGIN_GUIDE.md](docs/PLUGIN_GUIDE.md) and [docs/EXAMPLE_BREAD.md](docs/EXAMPLE_BREAD.md).

## Extra CSS and JavaScript

`tardis.assets.css` and `tardis.assets.js` take lists of URLs (`https://…` or root-relative `/…`) that load on every admin page after the package assets. Plugins can add inline CSS/JS through the `CSS` / `JS` provider contracts.

## Database Explorer

The explorer never lists, opens, alters or drops framework tables (`migrations`, `sessions`, `jobs`, `cache`, …, configurable in `tardis.database.hidden_tables`) or this package's `tardis_*` tables.

## Media uploads

Uploads on the media screen are limited to the extensions in `tardis-media.allowed_mimes` (images, pdf, office documents, zip) and to `tardis-media.max_file_size` KB. Executable or markup files such as `.php` and `.html` are rejected. Publish the config with `--tag=tardis-media-config` to change the list; keep in mind that `svg` is in the default list and can carry script, so remove it if you serve the media disk from your own origin.

## Testing

The package includes feature tests for the core admin flows and BREAD behavior.

```bash
composer test
composer test-coverage
composer lint
composer lint-fix
```

## Development workflow

Use the project scripts defined in `composer.json` as the default workflow for validation and formatting:

```bash
composer test
composer lint
```

For local coverage or formatting fixes:

```bash
composer test-coverage
composer lint-fix
```

## Documentation

| File | What it covers |
|---|---|
| [PROJECT_STATUS.md](PROJECT_STATUS.md) | Current state, verification and open follow-ups |
| [RELEASE_NOTES.md](RELEASE_NOTES.md) | What the package includes and what changed |
| [docs/PLUGIN_GUIDE.md](docs/PLUGIN_GUIDE.md) | Writing and enabling plugins |
| [docs/EXAMPLE_BREAD.md](docs/EXAMPLE_BREAD.md) | A complete BREAD definition |
| [docs/DEMO_FLOW.md](docs/DEMO_FLOW.md) | A walkthrough for demonstrating the panel |
| [docs/constraints.md](docs/constraints.md) | Permanent constraints and gotchas |
| [docs/notes.md](docs/notes.md), [docs/backlog.md](docs/backlog.md) | Open findings and the roadmap |

## License

MIT
