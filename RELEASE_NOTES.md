# Tardis Release Notes

Package version: `2.0.0` (`Tardis\Tardis::version()`), the breaking cleanup of the redesign. See [UPGRADE.md](UPGRADE.md) for every change you need to make.

## What the package includes

- Livewire 4 admin shell: dashboard, settings, plugins, media browser, activity log, search, database explorer, roles, permissions
- Dynamic BREAD resources (browse / read / edit / add / delete) generated from JSON definitions, with a BREAD builder, timestamped backups and rollback
- 19 form field types rendered through one registry (`FieldType` enum ↔ `FormfieldManager`), including relations and translatable fields
- Sidebar built from `MenuManager` and plugin-provided items; active-route detection for nested URLs
- Plugin system with Authentication, Authorization, Formfield and Theme plugin contracts plus Provider/Filter feature interfaces
- DaisyUI 5 theme engine: themes are data (`Theme`, `ThemeManager`, `storage/tardis/themes.json`), resolved on the server (user preference → Settings defaults → built-in) and written to `<html data-theme>` before first paint
- Role/permission tables, a `TardisAuthorizationPlugin` that is enabled by default, a Users screen for assigning roles and `tardis:admin` to create the first administrator (README → Authentication and authorization)
- Artisan commands: `tardis:admin`, `tardis:make-bread`, `tardis:make-model`, `tardis:make-plugin`, plus `tardis:bread:migrate` / `tardis:bread:export`

## Architecture decision

Pages follow the Livewire 4 page-first convention: simple screens are SFC, large ones are MFC (`bread/*`, `bread-builder`, `database/*`, `media-browser`, `settings`), and routes are `Route::livewire` routes. Class-based components are not used.

## 2.0 — the cleanup (Faz 0)

- **Field types** come from the `FormfieldManager` registry; the closed `FieldType` enum is gone, so a host-registered type can be used in a BREAD definition.
- **BREAD routes** are generated from the definitions (no `/{slug}` wildcard), so plugin routes are no longer swallowed. Definitions can carry `components`, `policy` and `scope`; slugs reserved for built-in screens are refused.
- **JSON is the only BREAD source**; `config/bread` is a read-only importer.
- **Lifecycle events** (`BreadSaved`, `BreadRemoved`, `BreadRecordCreated/Updated/Deleted`, `tardis.page`) with listeners for permissions and a working **activity log** (it was never written before).
- **Authentication plugin** is used by the login form (`attempt()`), and `BasePolicy` answers through `BreadAuthorization`.
- **Themes** supply validated CSS variables (`getTheme()`); `getStyles()` is removed. `Tardis::addCss()/addJs()` and `Asset` load extra files.
- Config keys nothing read were removed; `tardis:make-plugin` generates a loadable plugin with a valid `composer.json`.

## Authorization, plugins and admin screens (2026-10-03)

- **The panel is closed by default.** `TardisAuthorizationPlugin` is registered and enabled; `tardis.admin` requires the `access admin` ability after login. Run `php artisan tardis:admin you@example.com` after migrating. Set `tardis.authorization.enabled=false` only if something else protects `/admin`.
- **Every fixed screen is gated** by its own ability (`manage settings|plugins|users|roles|database|bread`, `view activity`, `browse|upload|rename|delete media`), checked on every Livewire request; the sidebar hides what the user cannot open (BREAD resources by `browse {slug}`). Saving a BREAD definition provisions its five permissions.
- **Plugins:** on/off state moved from the cache to `storage/tardis/plugins.json`; authentication and authorization plugins are locked. *Upgrade note: switches previously stored in the cache are not migrated, so a plugin that was disabled is enabled again until it is disabled once more.*
- **Users screen** (`/admin/users`): search users, assign roles, the last super administrator cannot be demoted.
- **Database Explorer** hides framework and `tardis_*` tables; the table being edited is locked.
- **Admin shell** renders on a host without `profile.edit`/`logout` routes (every page returned 500 before), and the managers are container singletons so the facade and the container share one instance.
- **Settings import** is all-or-nothing and reports a clash in the modal; `tardis.assets.css|js` add extra stylesheets/scripts.

## Earlier changes since the redesign branch started

Security and correctness fixes found in the code audit:

- **BREAD pages:** `slug`, `id`, `bread` and `record` are now `#[Locked]`. Previously a client could rewrite `bread.model` after the page was authorised and run create/edit/delete against any Eloquent model.
- **Search:** results are limited to resources the user may `browse`.
- **Login:** failed attempts are throttled (5 per email + IP); a successful login no longer sets the "failed" error.
- **Media:** uploads are restricted to `tardis-media.allowed_mimes` (the setting existed but was never enforced); uploading a colliding file without an extension no longer crashes; `MediaManager` is bound correctly as a singleton.
- **BREAD storage:** slugs are limited to `[A-Za-z0-9_-]` so a crafted slug cannot read or write outside the BREAD directory.
- **Validation:** rules are passed to Livewire as a list, so `regex:/^(a|b)$/` is no longer split at the pipe.
- **Plugins:** `tardis:make-plugin` output imported classes that do not exist and was never enabled; fixed. Boot-time enabling uses `enableByDefault()`, which no longer overrides a disable made on the Plugins page.
- **Settings page:** fixed a Blade parse error that kept the page from rendering, split into an MFC, and the default preset is now seeded on first web boot.

## Known limitations

- With `tardis.authorization.enabled=false` and no other authorization plugin, any authenticated user can reach the panel.
- The create/edit pages draw field types with an inline `@if` chain, so a custom type's own `render()` view is not used yet (`docs/notes.md` → B16, phase 2).
- With `route:cache`, a BREAD created afterwards needs the route cache rebuilt.
- Theme manifest loading happens during service-provider registration and logs rather than surfaces failures (rebuilt in the theme phase).

Open decisions and the roadmap live in [docs/notes.md](docs/notes.md) and [docs/backlog.md](docs/backlog.md).

## Verified quality

```bash
composer test   # 603 passed (1546 assertions)
composer lint   # clean
```

## 2.0 — panel i18n and design system (Faz 1 / 1b)

- **Translated panel**: every screen reads `tardis::` language files (`lang/en`, `lang/tr`). Publish with the `tardis-lang` tag to override or add a locale; `Locales` lists what is available. The locale is stored per user (`storage/tardis/preferences.json`) and switched from the header. A guard test fails on untranslated visible text in any Blade view.
- **Theme engine**: the Vite theme manifest is gone. Light/dark/system mode and the light and dark theme are chosen per user, with global defaults under the Settings `appearance` keys. Custom themes can be saved with `ThemeManager::saveCustom()`.
- **Core script**: a small IIFE (`dist/assets/app.js`, loaded before Livewire) provides the `theme` and `toasts` Alpine stores and the `window.Tardis` API (`component`, `on`, `toast`, `theme`, `csrf`).
- **Design-system components**: `x-tardis::card`, `badge`, `modal`, `slide-in`, `toasts`, `loading-bar` and `theme-picker`.

## 2.0 — field system (Faz 2, first part)

- **Create/edit render every field through its own view** (`Formfield::render()`), via `x-tardis::form-field`. A type registered by a plugin is now drawn with its own view instead of a text input; translatable fields get one control per locale.
- **`Formfield::configure()`** reads the type-specific definition keys (`options`, `min`/`max`/`step`, `suggestions`, `language`, `with_time`, `from`, relation and file settings), so radio and checkbox options and slider limits reach their controls.
- **`BreadSaver`** owns the field-to-column mapping, the NOT NULL guard, the transaction and the record events; the two pages no longer carry copies.
- New field types: `color` and `hidden`.

## 2.0 — BREAD listing (Faz 3, first part)

- **`BreadQuery`** builds the browse listing: search across the fields flagged `searchable` (LIKE wildcards are escaped), header-click sorting limited to visible `orderable` columns, a page-size selector and soft-delete views (hide / include / only). Nothing from the request reaches the SQL except values checked against the definition.
- **Actions**: `Tardis::addAction($slug|'*', Action)`, `replaceAction()` and `manipulateActions()` through `ActionManager`. Delete, restore and permanent delete are stock actions; any action can be offered per row, and `bulk` actions run on ticked rows. Each record is authorised and looked up through the BREAD's scope, so a bulk run cannot touch a row the user may not act on.

## 2.0 — plugin assets (Faz 4, first part)

- **File assets**: `Asset::file($path)` ships a stylesheet or script from inside a package. Tardis serves it at `/admin/_assets/{hash}.css|js` with `Cache-Control: public, max-age=31536000, immutable` and an ETag; the hash comes from the file's content, so an update changes the URL and nothing needs publishing. The route resolves hashes only against registered assets (no request value becomes a path) and runs without middleware.
- **Scope**: `->scope('admin'|'auth'|'both')`, `->routes('tardis.bread.*')` and `->ability('…')` declare where an asset is wanted; the others are not written. The login layout asks for `auth` assets.
- `provideCSS()`/`provideJS()` may return an `Asset` or a list of them (mixed with inline text); plain strings still work.

## 2.0 — plugin extensibility (Faz 4, rest)

- **Routes**: plugins implementing `Provider\Routes` add routes inside the panel group (admin prefix, `tardis.` names, `web` + locale + `tardis.admin` middleware).
- **Settings screen**: `Provider\SettingsComponent` names a Livewire component the Plugins page opens in a dialog.
- **Formfield assets**: `Formfield::assets()` is written only on pages that render the field.
- **CSP**: inline blocks carry a nonce (`tardis.csp.nonce` or Laravel's Vite nonce).
- **Tooling**: `tardis:plugins [list|enable|disable]`; `tardis:make-plugin --with-assets` scaffolds CSS/JS sources, a Vite build and the `Asset::file()` wiring. The JavaScript surface is documented in [docs/JS.md](docs/JS.md).

## 2.0 — menu builder, dashboard widgets, themes (Faz 6)

- **Menu builder** (`manage menus`): `storage/tardis/menus.json` layers hide, rename, section, order and custom links over the code-defined menu; deleting it restores the defaults. Custom link URLs are limited to http(s) and root-relative.
- **Dashboard** is widget-driven: the stock cards are `Widget`s with their own abilities, plugins add more through `Provider\Widgets`, and an edit mode (`manage dashboard`) hides, re-orders and resizes them into `storage/tardis/dashboard.json`.
- **Roles**: the permission picker shows groups and BREAD resources with toggle-all.
- **Theme editor** (`manage appearance`): create, edit and delete custom themes; built-ins can be duplicated.
- New abilities: `manage dashboard`, `manage appearance` (`manage menus` now has a screen). Run the permission seeder to create them.

## 2.0 — install, diagnostics and system tools (Faz 8)

- **`tardis:install`** — idempotent installer: migrate, seed permissions, publish assets, optionally create the first admin (`--email`, `--force`). Re-running it is safe; without `--force` the publish step leaves existing files alone.
- **`tardis:doctor`** — install health report (PHP/Laravel versions, storage, `tardis_*` tables, published assets, route cache, authorization plugin, theme manifest, plugins, BREAD definitions). Prints a table, `--json` for scripts, and exits non-zero on failure so it can gate a deploy.
- **Three system screens**, each behind its own ability rather than one collapsible group: diagnostics (`view system`), a read-only log viewer (`view logs`) and an allowlisted command runner (`run commands`). Every screen re-checks its ability in `boot()`, so a revoked permission takes effect on the next request instead of the next mount.
- **Log viewer** reads only files under `tardis.system.logs.path` whose name matches `filename_pattern`, capped at `max_bytes`, and seeks to the end of the file rather than loading it.
- **Command runner** is disabled by default and stays closed unless the environment is in `environments` (default `local`) *and* the command is in `allowlist` with its permitted arguments. The allowlist is re-checked at submit time rather than trusted from the form; there is no arbitrary command execution. Every allowed run is written to the activity log.
- New abilities: `view system`, `view logs`, `run commands`. Run the permission seeder to create them.
