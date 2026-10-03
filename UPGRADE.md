# Upgrading to 2.0

2.0 removes a set of APIs that were either unreachable, unused or unsafe. Everything here is a breaking change; the commit history groups them by `!` commits.

## Before you upgrade

```bash
composer require yellow-three/tardis:^2.0
php artisan migrate
php artisan tardis:admin you@example.com   # authorization is on by default, see below
```

If you use `php artisan route:cache`, rebuild it after upgrading **and after creating a BREAD** (see "BREAD routes").

## Authorization is on by default

`TardisAuthorizationPlugin` is registered and enabled, and the admin middleware requires the `access admin` ability. A user with no role cannot open the panel. Run `tardis:admin` once. Set `tardis.authorization.enabled` to `false` only if you register your own `AuthorizationPlugin` or protect `/admin` another way.

Plugin on/off state moved from the cache to `storage/tardis/plugins.json`; switches stored in the cache are not migrated (a plugin you had disabled is enabled again until you disable it once more). Authentication and authorization plugins cannot be disabled.

## BREAD

| Was | Now |
|---|---|
| `Tardis\Bread\FieldType` enum | `FormfieldManager` registry: `types()`, `has()`, `normalize()`, `assertRegistered()`. `registerType()` makes a type usable in BREAD definitions straight away |
| `ConfigBreadSource` (read/write PHP config) | Removed. `config/bread/*.php` is read-only through `Bread\Legacy\LegacyConfigReader`; import it with `tardis:bread:migrate` |
| `/{slug}`, `/{slug}/{id}` wildcard routes | Routes generated from the definitions and constrained to existing slugs (`Tardis\Http\BreadRoutes`). Names are unchanged (`tardis.bread.index` + `slug`) |
| a BREAD slug such as `users`, `settings`, `bread` | Reserved slugs (`Bread\ReservedSlugs`) cannot be saved or routed; rename the BREAD |
| `Events\BreadCreated/BreadUpdated/BreadDeleted` (barely dispatched) | `BreadRecordCreated/Updated/Deleted` from the create, edit and delete pages; `BreadSaved`/`BreadRemoved` for definitions |
| permissions created inside `BreadManager::save()` | `ProvisionBreadPermissions` listener on `BreadSaved` |
| nothing wrote the activity log | `LogBreadActivity` listener; `tardis.activity_log.enabled` / `log_events` now apply; passwords and hidden attributes are never stored |

New optional definition keys: `components` (replace the Livewire component behind `browse|add|edit|read`), `policy` (the word abilities are built from, default the slug) and `scope` (a model scope applied to listings and every record lookup).

### BREAD routes

BREAD routes are registered from the definitions when routes load. With `route:cache`, a BREAD created afterwards is not routable until you run `php artisan route:cache` again.

## Authentication

`AuthenticationPlugin::authenticate(Request): Request` is replaced by `attempt(array $credentials, bool $remember = false): bool`. The login form and the middleware go through `PluginManager::authenticationPlugin()`, which returns the plugin registered **last**, so a host plugin overrides the built-in one.

## Policies

`BasePolicy` now answers through `BreadAuthorization`, the same check the BREAD pages use. Abilities are `browse posts`, not `browse PostPolicy`; the slug comes from the class (`PostPolicy` → `posts`, `BlogPostPolicy` → `blog_posts`) or from `protected ?string $slug`. Without an authorization plugin a policy allows, like the pages.

## Themes and assets

- `ThemePlugin::getStyles()` is removed. Return CSS custom properties from `getTheme()` (`['--color-primary' => 'oklch(…)']`); Tardis validates them and writes the `:root` rule.
- New: `Tardis::addCss()` / `Tardis::addJs()` and `Tardis\Assets\Asset` (url or inline, optional `integrity`/`defer`).

## Configuration

Removed because nothing read them: `tardis.admin.middleware`, `tardis.plugins.*`, `tardis.media.*`, `tardis.bread.soft_deletes`, `tardis.bread.timestamps`. New: `tardis.authorization.enabled`, `tardis.database.hidden_tables`, `tardis.assets.css|js`.

## Plugins you generated with `tardis:make-plugin`

Generated plugins from 1.x did not load: they imported `Tardis\Core\…` classes and required a package that does not exist. Regenerate, or change the imports to `Tardis\Contracts\…` / `Tardis\Manager\…`, require `yellow-three/tardis: ^2.0`, and call `$plugins->enableByDefault('your-plugin')` after `register()`.

## Tooling

`composer lint` ignores `research/`. `tests/TestCase.php` isolates storage per test; a test that saves a BREAD definition and then requests a URL should call `reloadAdminRoutes()` (`tests/Pest.php`).

## Theme and language changes

| Was | Now |
|---|---|
| `vite-plugins/themes-manifest.js`, `config/tardis-themes.php`, the `tardis-themes-assets` publish tag | Removed. Built-in themes live in `Tardis\Theme\BuiltinThemes` and must be declared in your compiled CSS (`@plugin "daisyui" { themes: false; }` plus the `tardis-light`/`tardis-dark` blocks) |
| `AssetManager::availableThemes()` | `ThemeManager::all()` / `find()` / `names()` |
| inline Alpine theme store in the admin layout | `window.Tardis.theme` from the core script; run `npm run build` to refresh `dist/` |
| hard-coded English strings | `tardis::` translations. Publish `tardis-lang` to override; `#[Title]` attributes now hold translation keys |
