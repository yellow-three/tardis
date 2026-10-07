# Tardis Project Status

Last verified: 2026-10-08 — `composer test` → 918 passed (2369 assertions), `composer lint` clean.

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
- Themes are data: the built-ins ship in the compiled CSS, custom ones live in `storage/tardis/themes.json` (`ThemeManager`, edited under **Themes**). The server resolves the theme per request (user choice → Settings defaults → built-ins) and writes `data-theme`; there is no build-time manifest.
- `MenuOverlay` (`menus.json`), `DashboardLayout` (`dashboard.json`) and `UserPreferences` (`preferences.json`) hold the administrator's menu, dashboard and per-user choices on top of what code defines.
- `SettingsManager` persists to `storage/tardis/settings/settings.json`; the preset in `resources/presets/settings.json` is seeded on the first web boot when no file exists.

## Roadmap

**Faz 0 (1.0.0 cleanup) is done** — see `UPGRADE.md`. Remaining phases are in `docs/backlog.md` → *Voyager parity planı* (R22–R33): panel i18n, design system (+ theme/CSS/JS architecture), field system (one field contract; fixes B16), BREAD list, layouts/builder UX, plugins (hash-served assets, JS API), media, menu builder/widgets/appearance, translated content, install/doctor/system tools.

- **R28 — Translated content**: BREAD labels/descriptions and translatable form fields store raw locale maps, resolved only for display; read pages show fallback badges for borrowed labels and values; menu titles are locale-aware with stable IDs; Bread builder preserves locale maps and trims per locale; FieldValidationRules supports 'all'/'active' modes. Coverage added for badges, tabs, and validation modes (857 passed).

Known gaps: `route:cache` needs a rebuild after creating a BREAD (routes are generated from the definitions). Open backlog items: R14 (Graphify MCP for OpenCode, tooling only) and the "açık" tails listed per phase in `docs/backlog.md`.

## Documentation map

`README.md` (usage) · `RELEASE_NOTES.md` (what ships) · this file (state) · `docs/` (guides, constraints, notes, backlog, operations) · `research/` (Voyager study, historical snapshot of 2026-09-29; see its status banner).
