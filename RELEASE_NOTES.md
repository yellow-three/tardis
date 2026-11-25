# Tardis Release Notes

Package version: `0.1.0` (`Tardis\Tardis::version()`), the first pre-release: it is for testing, not production, and the API may change before 1.0. See [UPGRADE.md](UPGRADE.md) for every change you need to make.

## What the package includes

- Livewire 4 admin shell: dashboard, settings, plugins, media browser, activity log, search, database explorer, roles, permissions
- Dynamic BREAD resources (browse / read / edit / add / delete) generated from JSON definitions, with a BREAD builder, timestamped backups and rollback
- 22 form field types rendered through one registry (`FormfieldManager`), including relations, a media picker and translatable fields
- Sidebar built from `MenuManager` and plugin-provided items; active-route detection for nested URLs
- Plugin system with Authentication, Authorization, Formfield and Theme plugin contracts plus Provider/Filter feature interfaces
- DaisyUI 5 theme engine: themes are data (`Theme`, `ThemeManager`, `storage/tardis/themes.json`), resolved on the server (user preference → Settings defaults → built-in) and written to `<html data-theme>` before first paint
- Role/permission tables, a `TardisAuthorizationPlugin` that is enabled by default, a Users screen for assigning roles and `tardis:admin` to create the first administrator (README → Authentication and authorization)
- Artisan commands: `tardis:admin`, `tardis:make-bread`, `tardis:make-model`, `tardis:make-plugin`, plus `tardis:bread:migrate` / `tardis:bread:export`

## Architecture decision

Pages follow the Livewire 4 page-first convention: simple screens are SFC, large ones are MFC (`bread/*`, `bread-builder`, `database/*`, `media-browser`, `settings`), and routes are `Route::livewire` routes. Class-based components are not used.

## 0.1 — the cleanup (Faz 0)

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

Open decisions and the roadmap live in [docs/notes.md](https://github.com/yellow-three/tardis/blob/master/docs/notes.md) and [docs/backlog.md](https://github.com/yellow-three/tardis/blob/master/docs/backlog.md).

## Verified quality

```bash
composer test   # 603 passed (1546 assertions)
composer lint   # clean
```

## 0.1 — panel i18n and design system (Faz 1 / 1b)

- **Translated panel**: every screen reads `tardis::` language files (`lang/en`, `lang/tr`). Publish with the `tardis-lang` tag to override or add a locale; `Locales` lists what is available. The locale is stored per user (`storage/tardis/preferences.json`) and switched from the header. A guard test fails on untranslated visible text in any Blade view.
- **Theme engine**: the Vite theme manifest is gone. Light/dark/system mode and the light and dark theme are chosen per user, with global defaults under the Settings `appearance` keys. Custom themes can be saved with `ThemeManager::saveCustom()`.
- **Core script**: a small IIFE (`resources/compiled/assets/app.js`, loaded before Livewire) provides the `theme` and `toasts` Alpine stores and the `window.Tardis` API (`component`, `on`, `toast`, `theme`, `csrf`).
- **Design-system components**: `x-tardis::card`, `badge`, `modal`, `slide-in`, `toasts`, `loading-bar` and `theme-picker`.

## 0.1 — field system (Faz 2, first part)

- **Create/edit render every field through its own view** (`Formfield::render()`), via `x-tardis::form-field`. A type registered by a plugin is now drawn with its own view instead of a text input; translatable fields get one control per locale.
- **`Formfield::configure()`** reads the type-specific definition keys (`options`, `min`/`max`/`step`, `suggestions`, `language`, `with_time`, `from`, relation and file settings), so radio and checkbox options and slider limits reach their controls.
- **`BreadSaver`** owns the field-to-column mapping, the NOT NULL guard, the transaction and the record events; the two pages no longer carry copies.
- New field types: `color` and `hidden`.

## 0.1 — BREAD listing (Faz 3, first part)

- **`BreadQuery`** builds the browse listing: search across the fields flagged `searchable` (LIKE wildcards are escaped), header-click sorting limited to visible `orderable` columns, a page-size selector and soft-delete views (hide / include / only). Nothing from the request reaches the SQL except values checked against the definition.
- **Actions**: `Tardis::addAction($slug|'*', Action)`, `replaceAction()` and `manipulateActions()` through `ActionManager`. Delete, restore and permanent delete are stock actions; any action can be offered per row, and `bulk` actions run on ticked rows. Each record is authorised and looked up through the BREAD's scope, so a bulk run cannot touch a row the user may not act on.

## 0.1 — plugin assets (Faz 4, first part)

- **File assets**: `Asset::file($path)` ships a stylesheet or script from inside a package. Tardis serves it at `/admin/_assets/{hash}.css|js` with `Cache-Control: public, max-age=31536000, immutable` and an ETag; the hash comes from the file's content, so an update changes the URL and nothing needs publishing. The route resolves hashes only against registered assets (no request value becomes a path) and runs without middleware.
- **Scope**: `->scope('admin'|'auth'|'both')`, `->routes('tardis.bread.*')` and `->ability('…')` declare where an asset is wanted; the others are not written. The login layout asks for `auth` assets.
- `provideCSS()`/`provideJS()` may return an `Asset` or a list of them (mixed with inline text); plain strings still work.

## 0.1 — plugin extensibility (Faz 4, rest)

- **Routes**: plugins implementing `Provider\Routes` add routes inside the panel group (admin prefix, `tardis.` names, `web` + locale + `tardis.admin` middleware).
- **Settings screen**: `Provider\SettingsComponent` names a Livewire component the Plugins page opens in a dialog.
- **Formfield assets**: `Formfield::assets()` is written only on pages that render the field.
- **CSP**: inline blocks carry a nonce (`tardis.csp.nonce` or Laravel's Vite nonce).
- **Tooling**: `tardis:plugins [list|enable|disable]`; `tardis:make-plugin --with-assets` scaffolds CSS/JS sources, a Vite build and the `Asset::file()` wiring. The JavaScript surface is documented in [docs/JS.md](https://github.com/yellow-three/tardis/blob/master/docs/JS.md).

## 0.1 — menu builder, dashboard widgets, themes (Faz 6)

- **Menu builder** (`manage menus`): `storage/tardis/menus.json` layers hide, rename, section, order and custom links over the code-defined menu; deleting it restores the defaults. Custom link URLs are limited to http(s) and root-relative.
- **Dashboard** is widget-driven: the stock cards are `Widget`s with their own abilities, plugins add more through `Provider\Widgets`, and an edit mode (`manage dashboard`) hides, re-orders and resizes them into `storage/tardis/dashboard.json`.
- **Roles**: the permission picker shows groups and BREAD resources with toggle-all.
- **Theme editor** (`manage appearance`): create, edit and delete custom themes; built-ins can be duplicated.
- New abilities: `manage dashboard`, `manage appearance` (`manage menus` now has a screen). Run the permission seeder to create them.

## 0.1 — install, diagnostics and system tools (Faz 8)

- **`tardis:install`** — idempotent installer: migrate, seed permissions, publish assets, optionally create the first admin (`--email`, `--force`). Re-running it is safe; without `--force` the publish step leaves existing files alone.
- **`tardis:doctor`** — install health report (PHP/Laravel versions, storage, `tardis_*` tables, published assets, route cache, authorization plugin, theme manifest, plugins, BREAD definitions). Prints a table, `--json` for scripts, and exits non-zero on failure so it can gate a deploy.
- **Three system screens**, each behind its own ability rather than one collapsible group: diagnostics (`view system`), a read-only log viewer (`view logs`) and an allowlisted command runner (`run commands`). Every screen re-checks its ability in `boot()`, so a revoked permission takes effect on the next request instead of the next mount.
- **Log viewer** reads only files under `tardis.system.logs.path` whose name matches `filename_pattern`, capped at `max_bytes`, and seeks to the end of the file rather than loading it.
- **Command runner** is disabled by default and stays closed unless the environment is in `environments` (default `local`) *and* the command is in `allowlist` with its permitted arguments. The allowlist is re-checked at submit time rather than trusted from the form; there is no arbitrary command execution. Every attempt, refused ones included, is written to the activity log.
- New abilities: `view system`, `view logs`, `run commands`. Run the permission seeder to create them.

## Faz 7 — Multilingual content (R28)

- **Translatable BREAD content.** BREAD field values, BREAD definition names and menu titles can hold a raw locale map instead of a single string. The map is stored as-is and only resolved for display, so no data is lost on a round-trip through the builder.
- **Locale-aware fields and formfields** resolve through `resolvedLabel`, and the read page shows a fallback badge when a value is shown in a locale other than the active one.
- **Menu titles** are locale-aware, keep stable `MenuItem` ids across locales and trim per locale.
- **Bread builder round-trip** preserves locale maps and sanitizes each locale independently, so editing one locale never drops the others.
- **Field validation modes**: `all` validates every locale's value, `active` only the current one, configurable per definition (`config/tardis.php`).
- Language files gained `fallback_locale`; `en` and `tr` are both complete.

## Faz 5 — Media field and editing

- **`media_picker` form field** (`Tardis\Formfields\Types\MediaPickerField`), configurable with `mimes`/`allowed`, `min`/`max`, `disk`, `directory` and `show_folders`; it renders through `resources/views/formfields/media-picker.blade.php` and is grouped with `file` in the BREAD builder.
- **Media picker page** with multi-select, enforcing `min`/`max` (and reporting the limit in the active locale), plus per-type filtering.
- **Media edit screen** at `/admin/media/{id}/edit` (ability `rename media`) to edit name, alt text, caption and description.
- **`caption` and `description`** columns added to `tardis_media` (migration `2026_10_04_000001_add_caption_description_to_tardis_media_table`).
- **Configurable upload filename template** in `MediaManager` — tokens `{name}`, `{filename}`, `{ext}`, `{uid}`, `{random:n}` and `{date:...}` — with the resolved name sanitized by the same rules as the template input.
- **`FilterMedia` plugin contract** so a plugin can filter the media listing.
- New `lang/en/common.php` and `lang/tr/common.php`, and admin-screen authorization tests for the picker and edit screens.

## 0.1 — behaviour to know about when upgrading

- **Translatable validation is stricter by default** (`tardis.translation.validation` = `all`): a `required` translatable field must be filled in every locale. Set it to `active` to validate only the locale being edited. See UPGRADE.md.
- **Model map** (`tardis.models`): `Role`, `Permission`, `Media` and `ActivityLog` can be swapped for subclasses; a class that does not exist or does not extend the base model is refused with an error naming the config key.


## 0.1 — relation picker honours the related BREAD

`BelongsToManyField::searchOptions()` listed every record of the related model. When a BREAD manages that model, the picker now applies its `scope` and requires the `browse` ability on it (a user who may not browse it gets no options); an already selected id no longer bypasses either. A related model with no BREAD of its own is listed as before.

## Faz 9 — security and broken flows (R34)

Fixes from the 2026-11-17 review (`docs/notes.md`). Each one is covered by a test that reproduced it first.

- **Panel guard on Livewire updates.** `AdminMiddleware` is now persistent middleware, so every Livewire request after the first is authenticated and checked for `access admin`; before, a page left open after sign-out kept answering. BREAD pages also re-check their ability on every request (`boot()`).
- **Password reset works.** The mail links to the panel's own reset page (`Tardis\Auth\Notifications\ResetPasswordNotification`; the host's reset mails are untouched), the confirmation field is checked correctly, a bad token is shown as an error, the new password is hashed even without a `hashed` cast, the link's `?email=` fills the form, and the request form answers the same for known and unknown addresses.
- **Media extensions.** Renaming a file keeps to `tardis-media.allowed_mimes` (the check lives in `MediaManager`, so upload and rename share it), and a filename template can no longer change the stored extension.
- **BREAD models.** Only a concrete Eloquent model can back a BREAD (`BreadDefinition::isModelClass()`); a definition pointing at any other class is refused when saved and answered with 404.
- **No privilege escalation from Users/Roles.** Nobody hands out more than they hold, only a super administrator gives or takes away the super-admin role, and that role cannot be deleted (`Tardis\Auth\PrivilegeGuard`).
- **Database Explorer.** Column edits apply only what changed and refuse to re-declare a column the editor cannot describe exactly; schema changes are limited to `tardis.database.schema_changes.environments` (local, testing) and written to the activity log. "Create model" never overwrites an existing model.
- **Smaller fixes.** Edit/read pages no longer put undisplayed columns in the page snapshot; global search applies the BREAD `scope`, escapes `%`/`_` and links to a page the user may open; the Spatie bridge treats an unknown permission as a denial and honours `super_admin_roles`; sign-out works without `access admin`; `/admin` leads to the dashboard; `ReservedSlugs` covers every fixed screen.

## Faz 10 — robustness, first part (R35)

- **One permission query per request** instead of one or two per check (a dashboard request went from 67 queries to a handful).
- **Media "download selected" works**: the ZIP is the response, under a unique temporary name that is removed straight away; the server path no longer reaches the browser.
- **BREAD listings leave the host's query log alone** and only count their own queries in the slow/too-many warnings.
- **UUID and ULID keys** in BREAD row/bulk actions, the activity log (`activity_logs.model_id` is now a string; new migration) and the Users page.
- **`AuthenticationPlugin`**: `guard()`, `nameField()` and `avatar()` are used; the three methods nothing called are removed from the contract (see UPGRADE.md).
- **Smaller fixes**: media folder delete/rename escape LIKE wildcards; the Database Explorer list cannot be pointed at a hidden table; sorting adds the key as a tie-breaker; bulk actions are all or nothing; the Users screen is paginated.

## Faz 10 — robustness, second part (R35)

- **Page titles** of the BREAD pages, builder, Database Explorer, settings, media and UI components are translated (they were English in every language); a test checks every page title.
- **`declare(strict_types=1)`** in every class under `src/`, kept by a Pest arch test.
- **BREAD definitions are parsed once per request** (cached by content hash on a per-request `JsonBreadSource`).
- **Plugin switches and user preferences** are written under a file lock and change only what was asked for, so concurrent requests no longer undo each other (`Tardis\Support\JsonFile`).
- **Activity log**: sign-in, failed sign-in, sign-out, role permission changes, user role changes and plugin switches are logged; entries carry IP address and user agent (new migration).
- The test suite sets `memory_limit=512M` in `phpunit.xml.dist`.

## Faz 10b — themes, first part (R56)

- **One source per theme.** Built-in themes live only in `Tardis\Theme\BuiltinThemes`; the stylesheet declares none and `ThemeManager` writes every theme (built-in, plugin, custom).
- **`ThemePlugin::themes()`** adds selectable themes; the old `getTheme()` `:root` override, which beat every theme including dark mode, is gone (see UPGRADE.md).
- **DaisyUI shape variables** (`--radius-*`, `--size-*`, `--border`, `--depth`, `--noise`) are set by every theme, with DaisyUI's defaults; they were never set, so component corners and borders were invalid. A theme can change them with `shape`.
- **Tailwind only scans the package's own templates**, so the committed bundle no longer depends on docs or tests (36 unused selectors dropped).

## Faz 10c — core API fixes (R57, first part)

- **Every request gets the stylesheet and core script.** The "already written" guard moved from the singleton `AssetManager` to the request; a second request in the same process (Octane, a worker, a test) used to come out unstyled.
- **No work in the service provider on each request**: the settings preset is the manager's starting point instead of a file written from a GET; the default auth plugin is registered in `boot()`; the package commands are available to the panel's command runner.
- **Facade**: correct docblock (pinned by a test), accessors always ask the container, new `themes()`, `assets()`, `actions()`, `media()`; `version()` follows Composer for tagged installs.
- **One service provider**: the two sub-providers are folded in.
- **`tardis:install` clears compiled views**, so Livewire recompiles the panel components after an update; `@tardisStyles`/`@tardisScripts` compile to `app('tardis.assets')`.

## Faz 10c — Livewire structure (R57)

- **Components have their own root** (`resources/views/livewire/`), so layouts, Blade components and form field views are never part of the Livewire namespace. Group screens live in `index/` directories (no multi-file component holds another), the media picker is `tardis::components.media-picker`, and `tardis-views` publishes only the views a host can actually override.
- **Stable rows:** interactive loops (BREAD rows and filters, settings, media, roles, permissions, search) carry `wire:key`, so sorting, filtering and paging no longer risk moving state to the wrong row.
- **`#[Computed]`** replaces the legacy `getXProperty()` getters. The BREAD builder's state-derived values stay plain methods: moving a field read the cached order and changed it, which would have shown the old order in the same response.
- **Navigation:** redirects between panel screens keep `wire:navigate`.
- **One route group definition** (`Tardis\Http\PanelRoutes`) for fixed screens, BREAD and plugin routes; plugin routes now come before BREAD routes, and `/media/browse` redirects to the media screen.
- The UI components page honours its `admin.ui-components` setting; the builder's review step moved to `Tardis\Bread\Builder\BuilderReview`.

## Faz 10c — renaming (R57)

- **Classes live in their domain:** `Classes/` and `Manager/` are gone (`Menu\MenuManager`, `Theme\ThemeManager`, `Plugins\PluginManager`, …); the root class is `Tardis\TardisManager`, so it no longer shares a name with the facade; the default plugins are `Auth\SessionAuthenticationPlugin` and `Auth\RoleAuthorizationPlugin`.
- **Commands** follow Laravel's `make:` pattern (`tardis:make:bread`, `tardis:make:model`, `tardis:make:plugin`) and `tardis:bread:migrate` is `tardis:bread:import-legacy`.
- **Route names** separate the builder (`tardis.builder.*`) from records (`tardis.bread.{index,create,show,edit}`); every list screen ends in `.index`.
- Old class and command names work until 0.3 with a deprecation notice; stored menu entries and plugin switches are carried over. Full tables in UPGRADE.md.
- **One config root:** media and icon settings are the `media` and `icons` sections of `config/tardis.php` (one `tardis-config` publish tag); the activity log table is `tardis_activity_logs`, prefixed like every other package table; the name is spelled "Tardis" throughout.

## Faz 11 — relation fields (R36)

- **`belongs_to`**: a foreign key picked from a searchable list of the related records (label column configurable); the listing and detail page show the related label.
- **`has_one`**: pick the one related record that points at this record; the previous one is unlinked.
- Both pickers, like `belongs_to_many`, list only what the related BREAD shows (scope, `browse` permission) and refuse a key outside that list.
- **Builder:** the Relationships tab adds a detected relationship to the form (a belongsTo turns its foreign key into the picker). The unused `relationships` list and its "display type" are gone from definitions.

## Faz 11 — image field and thumbnails (R37)

- **`image` field type** (was an alias of `file`): `resize` fits an upload inside a box (never enlarging), `quality` re-encodes it, `thumbnails` stores named sizes next to it (fit or centre-crop). The form shows a preview of the current image; uploads are validated with Laravel's `image` rule.
- **Media screen thumbnails:** image uploads get a thumbnail sized by `tardis.media.thumbnail_size` (the setting was never read before), stored outside the browsable folder and deleted with the file.
- Processing uses PHP's GD extension and adds no Composer dependency; without GD, originals are stored as before.

## Faz 11 — site menus and front-end helpers (R38)

- **Site menus:** named menus for the host's own pages (`main`, `footer`, …), edited in *Menus → Site menus* with nesting (three levels), ordering, indent/outdent and new-tab links. They are stored in `storage/tardis/site-menus.json` with locked writes. Only http(s), root-relative and `#fragment` URLs are accepted.
- **Helpers:** `menu('main')` returns a menu as a tree, `setting('group.key', $default)` reads a panel setting, and `<x-tardis::site-menu name="main" />` renders nested lists with `aria-current` on the current page. `tardis_menu()` / `tardis_setting()` always exist; the short names step aside for a host function of the same name.

## Faz 11 — user management and profile (R39)

- **Users screen:** create accounts (with roles), edit name, email and password, delete accounts. Taking over an account is guarded like handing out roles: a user manager cannot touch a super administrator or anyone holding more permissions than they do, and nobody deletes themselves or the last super administrator. Every change is in the activity log.
- **Profile page** (`/admin/profile`, linked from the user menu): name, email and password; changing the email or the password needs the current password.
- **`tardis.users.columns`** names the user table's name, email and password columns; the screens leave out a column the table does not have (the Users page no longer assumes `name` and `email`).

## Faz 11 — order page and field options (R40)

- **Order page:** a BREAD with an `order_column` gets an *Order* button on its list. Records are dragged (or moved with arrows) into order and saved as positions 1, 2, 3… Only records inside the BREAD's scope are written, and the page needs `edit` on the BREAD. `order_display_column` picks the label shown per record.
- **Field options:** the builder's field drawer sets help text, placeholder and default value; definitions accept Voyager's `description` as help text and `timestamp` as a type name (→ `datetime`).

## Faz 12 — Voyager import (R41)

- **`tardis:import:voyager`** reads a Voyager 1.x database and imports BREADs (data types and rows, including relationships, image options and validation), menus (except `admin`) as site menus, settings, and roles with their permissions and user assignments.
- `--dry-run` writes nothing, `--only` picks parts, `--connection` reads another database, and `--force` overwrites. Every item that could not be mapped is listed in the report.

## Faz 12 — demo content (R42)

- **`tardis:install --demo`** / **`tardis:demo`** add Categories, Posts and Pages BREADs (belongs_to category picker, image field with a thumbnail, order page), sample records and a `main` site menu, in separate `tardis_demo_*` tables with models inside the package. Existing BREADs and menus with the same names are kept, and `tardis:demo --remove` removes everything the demo added. Outside local/testing it needs `--force`.

## Faz 12 — guides (R43)

- **docs/CUSTOMIZATION.md**: actions, custom field types, accessors, replacing a BREAD screen, overriding views, panel routes, signing in with another guard, soft deletes and access control. Its examples run as `tests/Feature/CustomizationGuideTest.php`.
- **docs/MIGRATING_FROM_VOYAGER.md**: importing a Voyager site, checking the report, replacing Voyager calls in code, what works differently, removing Voyager.
- The README now says what Tardis is and where it stands next to Voyager.

## Faz 12 — definition health (R44)

- **`tardis:doctor`** warns when a BREAD field, its order column or its search key names a column the table no longer has. Relation fields, fields with an `accessor` and fields the forms never write (model accessors) are left out.
- **BREAD builder:** the review step shows the same warning before the definition is saved. Backups and rollback stay on the BREAD management page (the *Backups* menu of each definition).

## Faz 13 — 1.0 gate, first part (R45)

- **Repository files:** `LICENSE` (MIT), `SECURITY.md` (private reports through GitHub), `CHANGELOG.md`, `CONTRIBUTING.md`; `.gitattributes` keeps docs, tests and build files out of the Composer archive.
- **Static analysis:** PHPStan with Larastan at level 5, clean without a baseline, as `composer analyse` and a CI job. It found a wrong import that only worked by luck: `MediaManager` named `Illuminate\Filesystem\DirectoryAttributes`, a class that does not exist, so folders were recognised only through a fallback. It also found dead code in `Media`.
- **Databases:** the test suite runs on MySQL and PostgreSQL too (`TARDIS_TEST_DB`), and CI runs both on every pull request. The first runs found that search boxes (BREAD lists, relation pickers, global search, users, activity log) were case-sensitive on PostgreSQL; they now use `ilike` there. The package migrations also tied `user_id` columns to `users.id` with foreign keys, which are now gone.
- CI's Composer cache key now changes with `composer.json` (it hashed a lock file that is not committed, so the cache never refreshed).
- **Dependency range:** CI tests the lowest versions `composer.json` allows (PHP 8.3, `--prefer-lowest`), and the unreleased branches of Laravel 13 and Livewire 4 as an early warning that does not block a merge.
- **Laravel 14 watch:** a non-blocking job runs the suite on Laravel's `master` branch with Testbench 12, where Laravel 14 will be built, so incompatibilities show up before it is released. `composer.json` allows `^14.0` once a Laravel 14 beta passes it.
- **Browser tests:** sign-in (right and wrong password) and BREAD flows (create, list, edit, sidebar navigation on the demo content) run in Chromium through Pest's browser plugin, as a CI job; they also fail on any JavaScript error on those pages.

## Open findings — long-running processes and state files (B59, B35)

- **No stale reads in long-running workers** (Octane, queue workers): settings, custom themes and plugin switches are re-read when another process changed their file (a file-signature check, one `stat`). The sidebar is rebuilt for every request from the registered items, so its titles follow each request's locale and new BREADs and menu builder changes show up at once.
- **No lost writes:** menu builder changes, the dashboard layout, custom themes, settings and the settings audit log are written under a lock, on top of what is on disk at that moment; two administrators saving at the same time both keep their change.

## Findings — activity log coverage (B38)

- **Restore and force delete** are logged as `restored` and `force_deleted` (a force delete was logged as `deleted`, a restore not at all). `BreadRecordRestored` is a new event; `BreadRecordDeleted` carries `$permanently`. Both actions are in the default `tardis.activity_log.log_events`; a published config that lists events needs them added.
- **Admin changes outside BREAD** are logged too: the menu builder (`tardis.admin_menu`), site menus (`tardis.site_menu`), the theme editor (`tardis.theme`) and the dashboard layout (`tardis.dashboard`). The activity log page filters and colours the new actions.

## Findings — assets (B55, B56, B58, B72)

- **No more publishing:** the core stylesheet and script are served from the package through the content-hash route plugins already used, so an upgraded package is live on the next page and a missing `vendor:publish` no longer leaves the panel unstyled. Hashes are cached by file signature (one `stat` per file and request) instead of an `md5_file()` of both files on every page.
- **Development mode matches production:** `npm run dev` adds the Vite client (stylesheet changes apply without a reload) and loads the core script as the same classic script the build is, at the same point. The hot file follows Vite's `server.origin`, `https` and `host` (or `TARDIS_VITE_URL`) instead of a fixed `http://localhost`.
- **Asset order in one place:** `<x-tardis::head />` and `<x-tardis::scripts />` hold the order the layouts relied on (core script before Livewire).
- **Smaller things:** the theme choice is resolved once per request; the page data is a JSON block that needs no CSP nonce; stale build folders are gone.

## Findings — media listing (B34)

- **One request per folder:** the media screen and the media picker read size, modification time and type from the folder listing (the type from the extension) instead of asking the disk three times per file, which on S3 meant three requests per file.
- **Paging:** both show `tardis.media.per_page` entries (60) at a time; the total stays on screen. A selection on the media screen is per page, the picker keeps it across pages.
- **No SVG by default:** `svg` is out of the default `tardis.media.allowed_mimes`. An SVG can carry script and is served as uploaded; add it back only for uploaders you trust.

## Findings — BREAD page snapshots (B75)

- **The definition stays on the server:** the browse, create, read and edit pages no longer carry the whole BREAD definition in their Livewire snapshot (`#[Locked] array $bread`). They keep the slug and read the definition on every request (`ReadsBreadDefinition`), so the payload is smaller, nothing about the model or fields can be tampered with, and an open page follows a definition changed in the builder on its next request (a removed one is a 404).

## Findings — UUID and ULID users (B29)

- **User columns follow the users table:** `tardis_role_user.user_id`, `tardis_activity_logs.user_id` and `tardis_media.created_by` take the type of the user key (`tardis.users.key_type`; `auto` reads it off the user model: `HasUuids`, `HasUlids`, a string key or an integer). A migration converts them; `php artisan tardis:user-keys` does it again after the type changes, and `tardis:doctor` warns while they do not match.
- **Role links with any user model:** `Role::users()` named its pivot columns from the model's class name, so a user model not called `User` (or a custom role or permission model) looked for columns that do not exist. The keys are named now.

## Theme editor: shape

- The theme editor edits a theme's shape too: corner rounding (`radius-box`, `radius-field`, `radius-selector`), field and selector sizes, border width, depth and noise. Every variable is shown with DaisyUI's default; only the values you change are stored, and a value that is not a length or a number is refused on its own field.

## 1.x — CSV export and "select all matching" (R46, first part)

- **Export:** every BREAD list has an *Export CSV* button. The file holds the columns the list shows, with the current search, filters, sort and trashed filter, and the values as the list shows them (relations as their labels). It streams in chunks, leaves out password fields and the model's hidden attributes, guards against spreadsheet formulas (CSV injection) and is recorded in the activity log. Anyone who may browse the BREAD may export it; `tardis.bread.export` switches the button off.
- **Select all matching:** a header checkbox ticks the page; then *Select all N matching records* makes a bulk action run on every record the search and filters match. Each record is still authorised on its own, the run is all-or-nothing, and `tardis.bread.bulk_limit` (1000) caps it.

## 1.x — CSV import (R46, second part)

- **Import CSV** on every BREAD list, for users who may add or edit records. The header row names the columns by field name or label, so a file exported from the list reads back in. A row carrying the key of an existing record (inside the BREAD's scope) updates it, any other row is added, each authorised as an add or an edit.
- Rows go through the same validation and saving as the forms (field rules, `store()`/`update()` hooks, record events, so the activity log sees every change). Every row is validated first: one invalid row and nothing is written, and the dialog lists each problem with its row number. A valid file is written in one transaction; a duplicate the database refuses rolls it all back.
- File, image, media, password, rich text and translatable fields and to-many relations are left out; `tardis.bread.import_limit` (1000 rows) and `import_max_kb` (5 MB) bound a file, `tardis.bread.import` switches it off.

## 1.x — revision history, undo and edit conflicts (R47)

- **History:** a record's page lists its changes from the activity log (who, when, each field by its label with the old and the new value), limited to the fields that page shows, so the history cannot reveal a value the page hides.
- **Undo:** *Restore these values* on an update puts back what it replaced, through the same saving as the edit form (so the undo is logged too). Needs `edit`; relations, files and passwords are not restored (the log never holds secret values).
- **Edit conflicts:** the edit form remembers the record as it opened it. If someone saves it in the meantime, saving shows a warning instead of overwriting their change; *Save anyway* then overwrites on purpose.

## 1.x — record ownership and field abilities (R48)

- **Own records only:** a definition's `owner_column` (`"owner_column": "user_id"`) limits users without `manage {policy}` to their own records: the list, search, record pages, actions, export and import all see only those, and another user's record is a 404. A new record belongs to whoever creates it, and a limited user cannot hand one to someone else.
- **Fields behind an ability:** a field with `"ability": "see salaries"` is left out of the list, the record pages, the forms, export, import and history for users without that ability, so it is never shown or written by them.
- Saving the definition creates `manage {policy}` and the field abilities, so they appear on the Roles page.
- Import: a key that matches no record the user can reach is reported as an error instead of being added as a new record.

## 1.x — two-factor authentication (R49, first part)

- **TOTP two-factor sign-in:** users turn it on from their profile (scan a QR code or type the key into an authenticator app, then confirm with a code). After the password, sign-in asks for a code from the app or a one-time recovery code; the session is not the user's until then. Wrong codes are throttled (5 a minute) and logged; a code cannot be used twice.
- Eight recovery codes are shown once and stored hashed; new ones can be made with a current code, and turning it off takes one too. The secret is encrypted with the app key, in `tardis_two_factor` (run `php artisan migrate`).
- No new dependency: the codes are computed by the package (RFC 6238). Install `bacon/bacon-qr-code` to get a QR code; without it the key is shown to type in. `tardis.two_factor.enabled` and `issuer` configure it.

## 1.x — sessions and impersonation (R49, second part)

- **Sessions:** the profile lists where the user is signed in (browser, address, last activity, this session) and signs out every other session with the current password. "Remember me" on those browsers stops too. The list needs Laravel's database session driver; with another driver the profile says so.
- **Sign in as another user:** with the new `impersonate users` ability, the Users screen offers *Sign in as*, within the same limits as managing that user (never a super administrator or someone holding more). A banner on every panel page leads back; both ends are in the activity log. Signing in as someone who may not open the panel ends at once and says why, instead of leaving the administrator on a 403.
- Run `php artisan tardis:install` (or the permission seeder) to create `impersonate users` on an existing install; super administrators have it already.

## 1.x — full audit log (R50)

- **Everything an administrator changes is in the activity log:** besides record changes, sign-in/out, roles, users, plugins, menus, themes and the dashboard, it now records settings (old and new value; a password setting's value is written as `[redacted]`), permissions, media uploads/renames/deletes and folders, and BREAD definitions (created, updated, removed). Schema changes from the Database screen were logged already. Each entry keeps the IP address and user agent.
- The Activity log screen filters by kind (`tardis.setting`, `tardis.media`, …) and by any action that occurs, shows the address, and opens the before/after values of an entry.
- **Retention:** `tardis.activity_log.retention_days` (`TARDIS_ACTIVITY_RETENTION_DAYS`, default 365; `null` keeps everything) and `php artisan tardis:activity:prune` (`--days`, `--pretend`). Schedule it: `Schedule::command('tardis:activity:prune')->daily();`.

## 1.x — state store driver (R51)

- **Database state driver:** `TARDIS_STATE_DRIVER=database` keeps settings (and their audit file), themes, the admin menu overlay, site menus, the dashboard layout, plugin switches, user preferences, the demo marker and BREAD definitions with their backups in the new `tardis_state` table instead of `storage/tardis`. Multi-server and ephemeral (container) deploys now share one state. The table is read once per request or queue job; writes lock the row, so two servers changing the same document both keep their change, and long-running workers notice another server's write through the row version.
- The file driver stays the default and behaves as before. A store given its own path (tests, a custom `tardis.bread.path` directory used with the file driver) keeps reading that file.
- `php artisan tardis:state:copy --to=database|file` (`--force`) moves the state between drivers; `tardis:doctor` reports the driver and a missing table.
- Closes B35 in `docs/notes.md`.

## 1.x — Database screen writes migrations (R52)

- **Schema changes become migrations:** creating or dropping a table and adding, renaming, changing or dropping a column on the Database screen now writes a migration file under `database/migrations` and runs it through `php artisan migrate`. The change is recorded in the `migrations` table, and committing the file brings every other environment to the same schema; no more drift between the database you clicked in and the rest.
- The generated `down()` reverses the change where it can (drop the created table or added column, undo a rename or a definition change, re-add a dropped column whose definition was known). Values typed into the editor are exported as strings, never as code. A migration that fails is removed again and the error is shown. Files written in the same second keep their order.
- `tardis.database.migrations.mode` (`TARDIS_DATABASE_MIGRATIONS`): `migrate` (default), `write` (only write the file; the screen says to run `php artisan migrate`), `off` (alter directly, the previous behaviour). `tardis.database.migrations.path` changes the directory. The screen says which mode is on; the activity log entry names the migration file.

## 1.x — JSON API from the BREAD definitions (R53)

- **Headless use of the same BREADs:** with `TARDIS_API=true`, `/api/tardis/{slug}` lists, reads, creates, updates (PUT/PATCH) and deletes records, and `/api/tardis` describes the BREADs the caller may browse. There is no second definition: validation, `scope`, `owner_column`, field `ability`, the read/add/edit switches, record events and the activity log are the panel's.
- The same authorization: "access admin" plus the browse/read/add/edit/delete ability (and the record for record-level rules). A record outside the caller's scope or ownership is a 404. Errors are JSON (401, 403, 404, 422 with `errors` keyed by field name).
- Passwords and `$hidden` attributes never appear in a response; uploads are not accepted over JSON. Lists take `search`, `sort`, `direction`, `per_page`, `filter[]` and `trashed`.
- `tardis.api` configures it: `enabled` (off by default), `prefix`, `middleware` (`auth:sanctum` by default; any guard works), `per_page`, `max_per_page`, `only`.

## 1.x — panel experience (R54)

- **Command palette (Ctrl+K / ⌘K):** jump to any page the sidebar would show you, or to "New …" for the BREADs you may add to, from the keyboard; anything else searches the records. Only what you may open is listed.
- **Notification centre:** the bell in the header lists your latest notifications with an unread count, marks them read when opened and has "mark all as read". `Tardis::notify(...)` and a `tardis` notification channel send them; they are stored in the new `tardis_notifications` table (run `php artisan migrate`). Links must be root-relative or http(s).
- **Chart widget contract:** `Tardis\Dashboard\ChartWidget` (line, bar, doughnut) is drawn on the server as SVG in the theme's colours, with escaped labels and a tooltip per point. The dashboard has a new "Activity, last 14 days" chart (hide it from the dashboard's edit mode).
- **More languages and RTL:** German, French, Spanish and Arabic join English and Turkish. Arabic pages are written right to left (`dir="rtl"`); the views use logical spacing so the layout mirrors.

## 1.x — list views, inline editing, duplicate, media usage and SVG cleaning (R55)

- **Saved list views:** name the current search, sort, page size, filters and deleted-records mode of a BREAD list and bring it back later; stored per user with their preferences. A view cannot bring back a sort, page size or filter the list does not offer.
- **Inline editing:** fields marked `"inline": true` (text, number, email, url) are edited in the list cell with the field's validation and the record's edit ability.
- **Duplicate action:** opens the create form pre-filled from a record the user may read (needs "add"); uploads, passwords and has-one/has-many are not copied.
- **Media usage:** the media details list the records and settings that use a file, and deleting a file that is still used shows a warning.
- **SVG cleaning:** SVG uploads, where `svg` is allowed, are stripped of scripts, event handlers, `javascript:`/`data:` links, foreign HTML, DOCTYPEs and entities before they are stored.

## 1.x — Excel export and import (R46, second part)

- **XLSX next to CSV:** with the optional `openspout/openspout` package installed, BREAD lists offer *Export Excel* (same columns, rows and order as the CSV; cells are text, so nothing is read as a formula) and the import accepts `.xlsx` files (first sheet), validated and authorised exactly like a CSV. Without the package, or with `tardis.bread.xlsx = false`, only CSV is offered. The export is logged with its format.

## 1.x — sign-in pages in the visitor's language

- The login, two-factor and password pages no longer always speak the application default before sign-in: they follow the language chosen in this session, then the one this browser last used (a `tardis_locale` cookie, also written from a signed-in user's saved choice), then the browser's `Accept-Language` when the panel has that translation (`tardis.admin.browser_locale`). They carry a language switcher too.
- Livewire updates keep the language (the locale middleware is persistent), so a failed sign-in or a validation message no longer falls back to English. Signed-in users keep their saved choice, then the application's language, as before.

## 1.x — hidden fields stay out of search and sort

- A field behind an ability (R48) was already left out of the pages, export and import, but the list's search box, its column search and its sort still used it, so a user without the ability could tell the hidden value by searching for it or ordering by it. The JSON API had the same gap. Search and sort now only use the fields the user may see; a legacy `search_key` that names a hidden field is not searched.
