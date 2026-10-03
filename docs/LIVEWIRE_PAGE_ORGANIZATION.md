# Livewire 4 page organization

TARDIS pages are Livewire 4 full-page components registered with `Route::livewire`. Only two formats are used:

- **SFC** — single-file component: PHP class and Blade template in one `.blade.php`
- **MFC** — multi-file component: a directory with `name.php` and `name.blade.php`
- Class-based components (a named class plus `render()`) are **not used**.

Both formats use an anonymous class, `new class extends Component`, with no namespace and no `render()` method. The full rules (including `#[Layout]`, locked properties and naming) are in `.claude/AGENTS.md`.

## Which format for which page

Use an SFC while a page is compact; move it to an MFC when it has substantial state, a long template, or its own JS/CSS.

| Format | Pages |
|---|---|
| SFC | dashboard, login, forgot-password, reset-password, permissions, roles, plugins, activity-log, search, ui-components |
| MFC | `bread/{index,create,edit,read,manage}`, `bread-builder`, `database` (+ `create`, `edit`), `media-browser`, `settings` |

`livewire:convert` switches a component between the two without changing its name:

```bash
php artisan livewire:convert tardis::pages.settings --mfc
```

Inside this package, where there is no application, run it through Testbench: `vendor/bin/testbench livewire:convert tardis::pages.settings --mfc`.

## Layout on disk

```text
resources/views/pages/dashboard.blade.php          # SFC  -> tardis::pages.dashboard

resources/views/pages/settings/                    # MFC  -> tardis::pages.settings
├── settings.php                                   #   PHP class
└── settings.blade.php                             #   template
```

The component name is identical for both formats, so routes and `<livewire:tardis::...>` tags never change when a page is converted.

```php
Route::livewire('/dashboard', 'tardis::pages.dashboard')->name('dashboard');
Route::livewire('/bread', 'tardis::pages.bread.manage')->name('bread.manage');
```

## Rules worth remembering

1. Page components declare `#[Layout('tardis::layouts.admin')]`; embedded child components do not declare a layout.
2. Public properties are client-writable. Anything authorisation depends on (`slug`, `id`, `bread`, `record` on the BREAD pages) must be `#[Locked]`.
3. Keep routes as Livewire page routes; reserve controllers for non-page backend work (the logout route is the only one).
4. Do not use a `⚡` emoji in file names — the package is published through Composer.
