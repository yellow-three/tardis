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
- Dynamic BREAD resources, generated from the definitions after the fixed screens
  - `/admin/{slug}`, `/admin/{slug}/create`, `/admin/{slug}/{id}`, `/admin/{slug}/{id}/edit`

BREAD routes are generated from the BREAD definitions and constrained to the slugs that exist, so there is no catch-all: a plugin route such as `/admin/blog/posts` is never swallowed. Slugs reserved for built-in screens (`settings`, `users`, `bread`, `media`, …) cannot be used for a BREAD. With `php artisan route:cache`, rebuild the cache after creating a BREAD. Fixed screens also include `/admin/users`.

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
| System diagnostics, log viewer, command runner | `view system`, `view logs`, `run commands` — one ability per screen, so holding one never grants or hides another |

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
- Management: Settings, Plugins, Database Explorer, System (diagnostics, logs, commands), BREAD (plus one entry per BREAD resource)
- Access: Permissions, Users, Roles

Entries the current user is not allowed to open are hidden. The user dropdown offers Logout, and a Profile link only when the host application defines a `profile.edit` route.

Menu items can be grouped by section and can participate in active-route detection across nested admin pages.

## Theme system

Themes are data. The built-in `tardis-light` and `tardis-dark` ship in the compiled stylesheet; any other theme is a validated entry in `storage/tardis/themes.json` that Tardis turns into a `[data-theme]` rule at request time, so a host can add one **without rebuilding any assets**.

- The server resolves the theme per request (the user's own choice, else the defaults under Settings → appearance, else the built-ins) and writes `data-theme` on `<html>`, so the first paint is already right.
- Users pick a mode (light, dark, follow the system) and a light and a dark theme from Settings; administrators can create and edit custom themes under **Themes** (`manage appearance`).
- A custom theme can also be added in code with `app(\Tardis\Manager\ThemeManager::class)->saveCustom([...])`: a `name` (`[a-z0-9-]`), a `scheme` (`light` or `dark`) and colours (at least `primary`, `base-100` and `base-content`; values are checked against an allow-list of colour formats).
- Extra CSS goes in Settings → appearance → custom CSS, or in a stylesheet registered with `Tardis::addCss()`.

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
| `tardis:doctor` | Report install health; exits non-zero on failure so it can gate a deploy (`--json` for scripts) |
| `tardis:install` | Idempotent installer: migrate, seed permissions, publish assets, optionally create the first admin (`--email`, `--force`) |

See [docs/PLUGIN_GUIDE.md](docs/PLUGIN_GUIDE.md) and [docs/EXAMPLE_BREAD.md](docs/EXAMPLE_BREAD.md).

## Extra CSS and JavaScript

`tardis.assets.css` and `tardis.assets.js` take lists of URLs (`https://…` or root-relative `/…`) that load on every admin page after the package assets. Plugins can add inline CSS/JS through the `CSS` / `JS` provider contracts.

## Database Explorer

The explorer never lists, opens, alters or drops framework tables (`migrations`, `sessions`, `jobs`, `cache`, …, configurable in `tardis.database.hidden_tables`) or this package's `tardis_*` tables.

## System diagnostics

Three read-mostly screens, each behind its own ability (`view system`, `view logs`, `run commands`). They appear as three separate sidebar entries rather than one collapsible group, so holding one ability never hides or strands a sibling.

- **Diagnostics** runs the same checks as `tardis:doctor`: PHP version, Laravel version, storage permissions, database tables, published assets, route cache, the authorization plugin, the theme manifest, installed plugins and BREAD definitions.
- **Log viewer** tails files under `tardis.system.logs.path` (default `storage/logs`). `filename_pattern` restricts which names may be opened, and `max_bytes` caps a single read — the reader seeks to the end, so a large log costs a seek rather than a full load.
- **Command runner** is off by default. Even enabled, it stays closed unless the app runs in one of `tardis.system.commands.environments` (default `local`) *and* the command appears in `tardis.system.commands.allowlist` as `['name' => [...allowed args]]`. An empty allowlist allows nothing, so enabling the runner is never by itself enough to expose artisan to a browser.

`php artisan tardis:doctor --json` reports the same checks as JSON and exits non-zero when a check fails, so it can gate a deploy.

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

## Extending

- **Field types:** `Tardis::formfields()->registerType('my_type', MyField::class)` makes `my_type` valid in BREAD definitions. (Custom rendering in the create/edit pages arrives with phase 2.)
- **Assets:** `Tardis::addCss('/vendor/host/extra.css')`, `Tardis::addJs(Asset::js('https://…', integrity: 'sha384-…'))` or `Asset::inlineCss(...)`. URLs must be http(s) or root-relative.
- **Events:** listen to `BreadSaved`, `BreadRemoved`, `BreadRecordCreated`, `BreadRecordUpdated`, `BreadRecordDeleted` and the `tardis.page` event. Permissions and the activity log are listeners you can replace.
- **Authentication:** register an `AuthenticationPlugin` after the built-in one; the login form and the middleware use the last registered, through `attempt()`.
- **Policies:** extend `Tardis\Policies\BasePolicy` — it asks the same authorization plugin the BREAD pages do.
- **Per-BREAD overrides:** a definition may set `components` (replace a page's Livewire component), `policy` (ability word) and `scope` (model scope for listings and lookups). Slugs such as `settings`, `users` or `bread` are reserved.

See [UPGRADE.md](UPGRADE.md) when coming from 1.x.

## Documentation

| File | What it covers |
|---|---|
| [UPGRADE.md](UPGRADE.md) | Moving from 1.x to 2.0 |
| [PROJECT_STATUS.md](PROJECT_STATUS.md) | Current state, verification and open follow-ups |
| [RELEASE_NOTES.md](RELEASE_NOTES.md) | What the package includes and what changed |
| [docs/PLUGIN_GUIDE.md](docs/PLUGIN_GUIDE.md) | Writing and enabling plugins |
| [docs/EXAMPLE_BREAD.md](docs/EXAMPLE_BREAD.md) | A complete BREAD definition |
| [docs/DEMO_FLOW.md](docs/DEMO_FLOW.md) | A walkthrough for demonstrating the panel |
| [docs/constraints.md](docs/constraints.md) | Permanent constraints and gotchas |
| [docs/notes.md](docs/notes.md), [docs/backlog.md](docs/backlog.md) | Open findings and the roadmap |

## License

MIT
