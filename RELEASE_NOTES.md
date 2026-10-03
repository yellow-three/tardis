# Tardis Release Notes

Package version: `1.0.0` (`Tardis\Tardis::version()`). Branch `feat/modern-admin-redesign` carries the unreleased redesign work.

## What the package includes

- Livewire 4 admin shell: dashboard, settings, plugins, media browser, activity log, search, database explorer, roles, permissions
- Dynamic BREAD resources (browse / read / edit / add / delete) generated from JSON definitions, with a BREAD builder, timestamped backups and rollback
- 19 form field types rendered through one registry (`FieldType` enum ↔ `FormfieldManager`), including relations and translatable fields
- Sidebar built from `MenuManager` and plugin-provided items; active-route detection for nested URLs
- Plugin system with Authentication, Authorization, Formfield and Theme plugin contracts plus Provider/Filter feature interfaces
- DaisyUI 5 theme system driven by a Vite-generated manifest, applied before first paint
- Role/permission tables and a `TardisAuthorizationPlugin` (not enabled by default — see README → Authentication and authorization)
- Artisan generators: `tardis:make-bread`, `tardis:make-model`, `tardis:make-plugin`, plus `tardis:bread:migrate` / `tardis:bread:export`

## Architecture decision

Pages follow the Livewire 4 page-first convention: simple screens are SFC, large ones are MFC (`bread/*`, `bread-builder`, `database/*`, `media-browser`, `settings`), and routes are `Route::livewire` routes. Class-based components are not used.

## Changes since the redesign branch started

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

- No authorization plugin is enabled by default; any authenticated user can reach the panel until one is registered.
- Roles, Permissions, Plugins, Settings, Database and BREAD-definition screens are not gated by BREAD abilities.
- `registerType()` extension point is unreachable from BREAD definitions because `FieldType` is a closed enum.
- Theme manifest loading happens during service-provider registration and logs rather than surfaces failures.

Open decisions and the roadmap live in [docs/notes.md](docs/notes.md) and [docs/backlog.md](docs/backlog.md).

## Verified quality

```bash
composer test   # 476 passed (1213 assertions)
composer lint   # clean
```
