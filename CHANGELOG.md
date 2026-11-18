# Changelog

All notable changes to this package. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow [Semantic Versioning](https://semver.org/); before 1.0, a minor version may break things, and [UPGRADE.md](UPGRADE.md) lists what to change. The detailed, phase-by-phase notes are in [RELEASE_NOTES.md](RELEASE_NOTES.md).

## [Unreleased]

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

### Changed
- Classes moved to their domain folders (`Tardis\TardisManager`, `Tardis\Menu\MenuManager`, …), generator commands renamed to `tardis:make:*`, builder routes renamed to `tardis.builder.*`; old class and command names keep working until 0.3.
- One config root (`tardis.media.*`, `tardis.icons.*`); the activity log table is `tardis_activity_logs`.
- Livewire components live in `resources/views/livewire/`; `tardis-views` publishes only overridable views.

### Fixed
- Security and robustness fixes of phases 9 and 10, the theme engine of phase 10b and the service provider of phase 10c; see RELEASE_NOTES.md.

## [0.1.0]

First pre-release. See RELEASE_NOTES.md.
