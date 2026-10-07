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

## Formfield views

| Was | Now |
|---|---|
| `Formfield::viewData()` overridden by subclasses with `array_merge(parent::viewData(), …)` | Override `extraViewData()`; `viewData(array $context = [])` adds `model` (the Livewire path, `form.<name>` by default), `id` and `extraAttributes` (was `attributes`) |
| field views bound `wire:model="<name>"` and drew their own label | A field view renders **only the control** bound to `$model`; label, help text, error and per-locale repetition come from `x-tardis::form-field`. `BelongsToManyField`/`HasManyField` expose the related class as `relatedModel` (was `model`) |
| options of radio/checkbox and slider limits ignored by BREAD pages | `Formfield::$configurable` maps definition keys onto properties |

## BREAD actions

`Tardis\Bread\Action` was unused and changed shape: it now works on one record (`handle(Model $record, string $slug)`, bulk runs loop over the selected rows) and is registered with `Tardis::addAction()`. `method`, `route` and `download` are gone. The `BREAD index` component's `executionMs`/`warnings` public properties became computed; the warning texts are translated.

## Provider\CSS / Provider\JS

The return type is now `string|Asset|array`. A plugin that returns a string keeps working; implementations that declare `: string` stay valid. `@tardisStyles`/`@tardisScripts` take an optional scope (`@tardisStyles('auth')`) and `AssetManager::styles()/scripts()` take it as an argument (default `admin`).

## New abilities

`manage menus` (existing, now gates the menu builder), `manage dashboard` and `manage appearance` are new fixed abilities. A user who is not a super admin needs them granted on a role (re-run `php artisan db:seed --class="Tardis\\Database\\Seeders\\PermissionSeeder"` to create the rows). `MenuManager::all()` and `WidgetManager::all()` take an optional `$withHidden` argument; `Widget::$component` is now a Blade view name (`tardis::widgets.users`) instead of an unused label.

Three more ship with the system tooling below: `view system` (diagnostics), `view logs` (log viewer) and `run commands` (command runner). They are separate abilities on purpose — one per screen — and each needs to be granted on the role.

## Installation and system tooling

Two new artisan commands ship with 2.0:

- `php artisan tardis:install` — idempotent installer: migrate, seed permissions, publish assets and optionally create the first admin (`--email`, `--force`). Safe to re-run; nothing is overwritten unless you pass `--force`.
- `php artisan tardis:doctor` — install health report covering PHP and Laravel versions, storage permissions, `tardis_*` tables, published assets, route cache, the authorization plugin, the theme manifest, installed plugins and BREAD definitions. Prints a table by default, `--json` for scripts, and exits non-zero when a check fails so it can gate a deploy.

Three screens ship alongside them: **System diagnostics** (`view system`), a read-only **log viewer** (`view logs`) and an **allowlisted command runner** (`run commands`). They are three separate sidebar entries rather than one collapsible group, so revoking one ability never hides or strands the other two.

The log viewer only reads files under `tardis.system.logs.path` (default `storage/logs`) whose name matches `filename_pattern`, and caps a single read at `max_bytes`. It seeks to the end of the file rather than loading it, so opening a multi-gigabyte log costs a seek, not a full read.

The command runner is **disabled by default**, and turning it on is not enough on its own: a command runs only when the app environment is listed in `tardis.system.commands.environments` (default `local`) *and* the command appears in `tardis.system.commands.allowlist` together with the arguments it may receive. An empty allowlist allows nothing.

```php
'commands' => [
    'enabled' => true,
    'environments' => ['local'],
    'allowlist' => [
        'cache:clear' => [],
        'queue:work' => ['--once', '--queue=default'],
    ],
],
```

Allowed entries are options only (`--flag` or `--option=value`); positional arguments are not supported.

There is no arbitrary command execution: the allowlist is checked again at submit time, not trusted from the form. Every attempt is written to the activity log (`tardis.command`, action `executed` or `denied`, with the command, its options and the exit code), refused ones included, unless `tardis.activity_log.enabled` is false.

## Translatable fields: validation now looks at every locale

A translatable field holds one value per locale, and its rules (including `required`) are now applied to **each locale**. Before, `required` only asked whether the array was non-empty, so a record with one language filled in passed. After upgrading, a record that has a `required` translatable field with an empty locale cannot be saved until that locale is filled in.

To keep the old, looser behaviour set `tardis.translation.validation` to `'active'` (only the locale being edited is validated), or set `"validation_mode": "active"` on a single field of a BREAD definition. Other changes from the same release: the create and edit forms show locales as tabs (`tardis.translation.tabs`, default on); BREAD names, descriptions, field labels and menu titles may be a locale map (`{"en": "Posts", "tr": "Yazılar"}`) or a translation key, and plain strings keep working.
