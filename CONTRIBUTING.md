# Contributing

Thanks for helping. Bug reports, fixes and documentation are all welcome.

## Setting up

```bash
git clone https://github.com/yellow-three/tardis.git
cd tardis
composer install
npm install
```

The package has no application of its own: tests run on Orchestra Testbench, and `vendor/bin/testbench` runs Artisan commands against it.

## Before you open a pull request

```bash
composer test      # Pest, SQLite in memory
composer lint      # Pint (composer lint-fix applies it)
composer analyse   # PHPStan with Larastan
npm run build      # only when you changed CSS, JS or Blade classes; commit resources/compiled
```

Browser tests (sign-in and BREAD flows in Chromium, with the exact Playwright version `pest-plugin-browser` speaks to: keep `playwright` pinned to it) live in `tests/Browser` and are not part of `composer test`. Run them with `npm ci && npx playwright install chromium && vendor/bin/pest tests/Browser`. They need PHP and the browser on the same system, so they do not run where PHP is inside a container.

CI also runs the suite on MySQL and PostgreSQL. To run it locally against one, set `TARDIS_TEST_DB=mysql` (or `pgsql`) and the usual `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Every test starts from an empty database, so use a database you can throw away.

## Conventions

- Livewire components are anonymous single-file or multi-file components under `resources/views/livewire/` (see [docs/LIVEWIRE_PAGE_ORGANIZATION.md](docs/LIVEWIRE_PAGE_ORGANIZATION.md)); no class-based components.
- Every user-facing string goes through `lang/` (English and Turkish); a test fails on hard-coded text in views.
- A behaviour change comes with a test; a bug fix with a test that failed before it.
- Commit messages follow Conventional Commits (`feat:`, `fix:`, `docs:`, …) and say why, not only what.
- Changes a host has to act on go into [UPGRADE.md](UPGRADE.md), and user-visible changes into [RELEASE_NOTES.md](RELEASE_NOTES.md).

## Reporting security problems

See [SECURITY.md](SECURITY.md); please do not open a public issue.
