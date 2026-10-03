# Tardis Project Status

Last verified: 2026-10-03 on `refactor/settings-mfc-and-cleanup` — `composer test` → 441 passed (1116 assertions), `composer lint` clean.

## Overview

Tardis is a Laravel 13 / Livewire 4 admin framework package (DaisyUI 5, Vite, dynamic BREAD). The modern admin redesign is in progress on `feat/modern-admin-redesign`.

## Architecture

### Fixed admin screens (Livewire pages, `Route::livewire`)
dashboard, plugins, media browser, activity log, database manager (list/create/edit), settings, search, permissions, roles, UI components, BREAD manage and BREAD builder.

### Dynamic BREAD resources
Declared last in `routes/admin.php` so they never shadow the fixed screens:
`/admin/{slug}`, `/{slug}/create`, `/{slug}/{id}`, `/{slug}/{id}/edit`. Definitions live in JSON (`JsonBreadSource`, timestamped backups + rollback) or config (`ConfigBreadSource`).

### Components
Only SFC and MFC are allowed (see `.claude/AGENTS.md`). Large pages are MFC: `bread/*`, `bread-builder`, `database/*`, `media-browser`, `settings`.

### Menu, themes, settings
- `MenuManager` groups the sidebar and detects active nested URLs.
- `ThemeManager` reads the Vite-generated `themes-manifest.json` (dev: Vite URL with disk fallback; prod: disk).
- `SettingsManager` persists to `storage/tardis/settings/settings.json`; the preset in `resources/presets/settings.json` is seeded on the first web boot when no file exists.

## Known follow-ups

- `pages/settings/settings.blade.php` (~670 lines) and `bread-builder` are still the largest views; candidates for extracting partials.
- Theme manifest loading in `TardisServiceProvider::register()` does I/O at register time and swallows failures (logged only).
