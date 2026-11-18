# Tardis Project Status

> **Status and plans are not kept here.** The single source for what is done, what is open and what comes next is [`docs/backlog.md`](docs/backlog.md) (*Current State* and *1.0 yol haritası*); open findings are in [`docs/notes.md`](docs/notes.md). This file is only a short architecture summary and changes when the architecture does.

## Overview

Tardis is a Laravel 13 / Livewire 4 admin framework package (DaisyUI 5, Vite, dynamic BREAD). The modern admin redesign and the Voyager parity phases 0–8 are merged into `master` (PRs #10–#29). The goal is a maintained successor to Voyager 1 (`thedevdojo/voyager`) and Voyager 2 (`voyager-admin/voyager`) on current Laravel.

## Architecture

### Fixed admin screens (Livewire pages, `Route::livewire`)
dashboard, plugins, media browser, activity log, database explorer (list/create/edit), settings, search, permissions, roles, UI components, BREAD manage and BREAD builder.

### Dynamic BREAD resources
Declared last in `routes/admin.php` so they never shadow the fixed screens: `/admin/{slug}`, `/{slug}/create`, `/{slug}/{id}`, `/{slug}/{id}/edit`. Definitions live in JSON (`JsonBreadSource`, timestamped backups, rollback, slug-validated file names). `config/bread/*.php` is read only by `LegacyConfigReader`, for the migration command and a warning on the manage screen.

### Components
Only SFC and MFC are allowed (see `.claude/AGENTS.md`). Large pages are MFC: `bread/*`, `bread-builder`, `database/*`, `media-browser`, `settings`.

### Security model
- `tardis.admin` middleware → `AuthenticationPlugin` (default: `Auth::check()`) then the `access admin` ability; login is rate limited.
- `RoleAuthorizationPlugin` is enabled by default (`tardis.authorization.enabled`); `tardis:admin` creates the first super administrator. With no authorization plugin at all the guard fails open.
- BREAD pages authorise `"{action} {slug}"`; every other fixed screen authorises a fixed ability in `boot()` (so each Livewire request is checked); the sidebar hides what the user may not open.
- BREAD page properties that authorisation depends on are `#[Locked]`.
- Plugin switches live in `storage/tardis/plugins.json`; authentication/authorization plugins are locked.
- Media uploads are restricted to `tardis-media.allowed_mimes`.

### Menu, themes, settings
- `MenuManager` groups the sidebar and detects active nested URLs.
- Themes are DaisyUI `data-theme` palettes held as data: two built-ins compiled into the stylesheet, custom ones in `storage/tardis/themes.json` (`ThemeManager`, edited under **Themes**). The server resolves the theme per request (user choice → Settings defaults → built-ins) and writes `data-theme`. Details and the rules to follow: `docs/constraints.md` → *Tema sistemi* and *Varlıklar*.
- `MenuOverlay` (`menus.json`), `DashboardLayout` (`dashboard.json`) and `UserPreferences` (`preferences.json`) hold the administrator's menu, dashboard and per-user choices on top of what code defines.
- `SettingsManager` persists to `storage/tardis/settings/settings.json`; the preset in `resources/presets/settings.json` is seeded on the first web boot when no file exists.

## Documentation map

`README.md` (usage) · `RELEASE_NOTES.md` (what ships) · this file (architecture summary) · `docs/backlog.md` (status and roadmap, single source) · `docs/notes.md` (open findings) · `docs/` (guides, constraints, notes, backlog, operations) · `research/` (Voyager study, historical snapshot of 2026-09-29; see its status banner).
