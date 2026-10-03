# Tardis Project Status

Last verified: 2026-10-03 — `composer test` → 561 passed (1391 assertions), `composer lint` clean.

## Overview

Tardis is a Laravel 13 / Livewire 4 admin framework package (DaisyUI 5, Vite, dynamic BREAD). The modern admin redesign is in progress on `feat/modern-admin-redesign`.

## Architecture

### Fixed admin screens (Livewire pages, `Route::livewire`)
dashboard, plugins, media browser, activity log, database explorer (list/create/edit), settings, search, permissions, roles, UI components, BREAD manage and BREAD builder.

### Dynamic BREAD resources
Declared last in `routes/admin.php` so they never shadow the fixed screens: `/admin/{slug}`, `/{slug}/create`, `/{slug}/{id}`, `/{slug}/{id}/edit`. Definitions live in JSON (`JsonBreadSource`, timestamped backups, rollback, slug-validated file names). `ConfigBreadSource` (`config/bread/*.php`) is a legacy read path used only by the migration command and a warning on the manage screen.

### Components
Only SFC and MFC are allowed (see `.claude/AGENTS.md`). Large pages are MFC: `bread/*`, `bread-builder`, `database/*`, `media-browser`, `settings`.

### Security model
- `tardis.admin` middleware → `AuthenticationPlugin` (default: `Auth::check()`) then the `access admin` ability; login is rate limited.
- `TardisAuthorizationPlugin` is enabled by default (`tardis.authorization.enabled`); `tardis:admin` creates the first super administrator. With no authorization plugin at all the guard fails open.
- BREAD pages authorise `"{action} {slug}"`; every other fixed screen authorises a fixed ability in `boot()` (so each Livewire request is checked); the sidebar hides what the user may not open.
- BREAD page properties that authorisation depends on are `#[Locked]`.
- Plugin switches live in `storage/tardis/plugins.json`; authentication/authorization plugins are locked.
- Media uploads are restricted to `tardis-media.allowed_mimes`.

### Menu, themes, settings
- `MenuManager` groups the sidebar and detects active nested URLs.
- `ThemeManager` reads the Vite-generated `themes-manifest.json` (dev: Vite URL with disk fallback; prod: disk).
- `SettingsManager` persists to `storage/tardis/settings/settings.json`; the preset in `resources/presets/settings.json` is seeded on the first web boot when no file exists.

## Roadmap

The next release is **2.0.0**: a Voyager 1/2 parity plan in nine phases (`docs/backlog.md` → *Voyager parity planı*, R21–R30). Decided on 2026-10-03: JSON is the single BREAD source, the field-type enum is replaced by a registry + `FormfieldPlugin`, backwards compatibility may break once (with `UPGRADE.md`), translations stay in JSON columns, the menu builder stores `storage/tardis/menus.json`, and the panel gets `lang/en` + `lang/tr`.

Still open and not part of a phase decision: `ThemeManager` boot-time I/O (R19).

## Documentation map

`README.md` (usage) · `RELEASE_NOTES.md` (what ships) · this file (state) · `docs/` (guides, constraints, notes, backlog, operations) · `research/` (Voyager study, historical snapshot of 2026-09-29; see its status banner).
