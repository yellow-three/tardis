# Tardis Project Status

Last verified: 2026-10-04 — `composer test` → 857 passed (2145 assertions), `composer lint` clean.

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

**Faz 0 (2.0.0 cleanup) is done** — see `UPGRADE.md`. Remaining phases are in `docs/backlog.md` → *Voyager parity planı* (R22–R33): panel i18n, design system (+ theme/CSS/JS architecture), field system (one field contract; fixes B16), BREAD list, layouts/builder UX, plugins (hash-served assets, JS API), media, menu builder/widgets/appearance, translated content, install/doctor/system tools.

- **R28 — Translated content**: BREAD labels/descriptions and translatable form fields store raw locale maps, resolved only for display; read pages show fallback badges for borrowed labels and values; menu titles are locale-aware with stable IDs; Bread builder preserves locale maps and trims per locale; FieldValidationRules supports 'all'/'active' modes. Coverage added for badges, tabs, and validation modes (857 passed).

Known gaps: create/edit still draw field types with an inline `@if` chain (B16); `route:cache` needs a rebuild after creating a BREAD; theme loading still reads the manifest at register time (R31).

## Documentation map

`README.md` (usage) · `RELEASE_NOTES.md` (what ships) · this file (state) · `docs/` (guides, constraints, notes, backlog, operations) · `research/` (Voyager study, historical snapshot of 2026-09-29; see its status banner).
