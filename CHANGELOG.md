# Changelog

All notable changes to this package. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow [Semantic Versioning](https://semver.org/); before 1.0, a minor version may break things, and [UPGRADE.md](UPGRADE.md) lists what to change. The detailed, phase-by-phase notes are in [RELEASE_NOTES.md](RELEASE_NOTES.md).

## [1.0.0]

First stable release (not tagged yet). Everything since 0.1.0:

### Added
- Relation fields `belongs_to` and `has_one`; the builder's Relationships tab adds a detected relation to the form.
- `image` field type with resizing and thumbnails; media screen thumbnails.
- Site menus with `menu()`, `setting()` and `<x-tardis::site-menu>` for the host's pages.
- User accounts on the Users screen and a profile page.
- Order page for BREADs with an order column; help text, placeholder and default in the builder.
- `tardis:import:voyager` to import a Voyager 1.x installation.
- `tardis:install --demo` / `tardis:demo` sample content.
- `tardis:doctor` and the builder warn about fields whose column is gone.
- Customization and Voyager migration guides.
- PHPStan (Larastan) in CI, and test runs on MySQL and PostgreSQL.
- CSV and, with the optional `openspout/openspout` package, Excel (.xlsx) export and import on BREAD lists; "select all matching" for bulk actions (R46).
- Record history with undo, and a warning when someone else saved the record while it was being edited (R47).
- Own-records-only BREADs (`owner_column`) and fields behind an ability (R48).
- TOTP two-factor sign-in, a session list with "sign out other sessions", and signing in as another user (R49).
- A full audit log of administrator changes with retention and `tardis:activity:prune` (R50).
- A database state store (`TARDIS_STATE_DRIVER=database`) and `tardis:state:copy` (R51).
- The Database screen writes migration files for its schema changes (R52).
- A JSON API generated from the BREAD definitions (`TARDIS_API=true`) (R53).
- A command palette (Ctrl+K), a notification centre with a `tardis` notification channel, a chart widget contract, German, French, Spanish and Arabic with right-to-left layout (R54).
- Saved list views, inline editing, a duplicate action, media usage tracking and SVG cleaning on upload (R55).
- The sign-in pages follow the visitor's language and carry a language switcher.

### Changed
- Classes moved to their domain folders (`Tardis\TardisManager`, `Tardis\Menu\MenuManager`, …), generator commands renamed to `tardis:make:*`, builder routes renamed to `tardis.builder.*`; old class and command names keep working until 0.3.
- One config root (`tardis.media.*`, `tardis.icons.*`); the activity log table is `tardis_activity_logs`.
- Livewire components live in `resources/views/livewire/`; `tardis-views` publishes only overridable views.

### Fixed
- Security and robustness fixes of phases 9 and 10, the theme engine of phase 10b and the service provider of phase 10c; see RELEASE_NOTES.md.
- A field hidden by its ability can no longer be searched, column-searched or sorted on, in the panel or the JSON API.
- Livewire updates on the sign-in pages keep the chosen language.
- The user foreign key upgrade migration drops SQLite keys by columns, keeping rows and columns intact.
- JSON API requests without an `Accept` header stay JSON (401 instead of a redirect or a missing login route).
- The import dialog says "Import CSV or Excel" and explains `.xlsx` when Excel support is available.
- SVG cleaning checks unprefixed attribute names and removes external `<use>`, `<image>` and `<feImage>` references.

### Security
- Two-factor sign-in could be skipped when a custom authentication plugin used its own guard.
- A media file name containing a quote could run script on the media screen (stored XSS); values in Livewire/Alpine expressions are now passed with `@js()`.
- A user with only "manage bread" could expose another model (for example the user model) under a policy key they already held.
- Named filters, `belongs_to_many` writes, `has_many` child rows and relation labels in lists and exports now respect field abilities, the related BREAD's restrictions and the declared child fields.
- The owner restriction no longer switches off for a panel user that is not an Eloquent model.
- The media picker checks "browse media" in every action; SVG uploads lose `<style>` and `xml:base`; XLSX cells are always text; password reset links use `app.url` instead of the request's Host header; an unknown BREAD icon no longer breaks every panel page.

## [0.1.0]

First pre-release. See RELEASE_NOTES.md.
