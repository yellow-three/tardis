# Tardis Release Notes

Package version: `1.0.0` (`Tardis\Tardis::version()`). Branch `feat/modern-admin-redesign` carries the unreleased redesign work.

## What the package includes

- Livewire 4 admin shell: dashboard, settings, plugins, media browser, activity log, search, database explorer, roles, permissions
- Dynamic BREAD resources (browse / read / edit / add / delete) generated from JSON definitions, with a BREAD builder, timestamped backups and rollback
- 19 form field types rendered through one registry (`FieldType` enum ↔ `FormfieldManager`), including relations and translatable fields
- Sidebar built from `MenuManager` and plugin-provided items; active-route detection for nested URLs
- Plugin system with Authentication, Authorization, Formfield and Theme plugin contracts plus Provider/Filter feature interfaces
- DaisyUI 5 theme system driven by a Vite-generated manifest, applied before first paint
- Role/permission tables, a `TardisAuthorizationPlugin` that is enabled by default, a Users screen for assigning roles and `tardis:admin` to create the first administrator (README → Authentication and authorization)
- Artisan commands: `tardis:admin`, `tardis:make-bread`, `tardis:make-model`, `tardis:make-plugin`, plus `tardis:bread:migrate` / `tardis:bread:export`

## Architecture decision

Pages follow the Livewire 4 page-first convention: simple screens are SFC, large ones are MFC (`bread/*`, `bread-builder`, `database/*`, `media-browser`, `settings`), and routes are `Route::livewire` routes. Class-based components are not used.

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
- The login form calls `auth()->attempt()` directly instead of the `AuthenticationPlugin`.
- `tardis.admin.middleware`, `tardis.plugins.*`, `tardis.media.*`, `tardis.bread.soft_deletes/timestamps` and `tardis.activity_log.*` config keys are not read anywhere.
- `registerType()` extension point is unreachable from BREAD definitions because `FieldType` is a closed enum.
- Theme manifest loading happens during service-provider registration and logs rather than surfaces failures.

Open decisions and the roadmap live in [docs/notes.md](docs/notes.md) and [docs/backlog.md](docs/backlog.md).

## Verified quality

```bash
composer test   # 561 passed (1391 assertions)
composer lint   # clean
```
