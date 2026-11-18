# Livewire 4 page organization

Tardis pages are Livewire 4 full-page components registered with `Route::livewire`. Only two formats are used:

- **SFC** — single-file component: PHP class and Blade template in one `.blade.php`
- **MFC** — multi-file component: a directory with `name.php` and `name.blade.php`
- Class-based components (a named class plus `render()`) are **not used**.

Both formats use an anonymous class, `new class extends Component`, with no namespace and no `render()` method. The full rules (including `#[Layout]`, locked properties and naming) are in `.claude/AGENTS.md`.

## Which format for which page

Use an SFC while a page is compact; move it to an MFC when it has substantial state, a long template, or its own JS/CSS.

| Format | Pages |
|---|---|
| SFC | dashboard, login, forgot-password, reset-password, permissions, roles, plugins, activity-log, search, ui-components |
| MFC | `bread/{index,create,edit,read,manage}`, `bread-builder`, `database/{index,create,edit}`, `system/{index,logs,commands}`, `media-browser`, `media-edit`, `settings`, `users` |
| Child (MFC) | `components/media-picker` → `tardis::components.media-picker`, embedded by the `media_picker` form field |

`livewire:convert` switches a component between the two without changing its name:

```bash
php artisan livewire:convert tardis::pages.settings --mfc
```

Inside this package, where there is no application, run it through Testbench: `vendor/bin/testbench livewire:convert tardis::pages.settings --mfc`.

## Layout on disk

Livewire components have their own root, `resources/views/livewire/`. Layouts, Blade components, form field and widget views sit next to it, outside the Livewire namespace, so none of them is ever resolved as a component.

```text
resources/views/livewire/pages/dashboard.blade.php          # SFC  -> tardis::pages.dashboard

resources/views/livewire/pages/settings/                    # MFC  -> tardis::pages.settings
├── settings.php                                   #   PHP class
└── settings.blade.php                             #   template

resources/views/livewire/pages/database/index/              # MFC  -> tardis::pages.database
resources/views/livewire/pages/database/create/             # MFC  -> tardis::pages.database.create
resources/views/livewire/components/media-picker/           # MFC  -> tardis::components.media-picker
```

A multi-file component never contains another one: the screen at the root of a group lives in an `index/` directory, which Livewire resolves to the group's own name (`pages.database`). `tests/Feature/LivewireStructureTest.php` keeps it that way.

## Overriding views

`php artisan vendor:publish --tag=tardis-views` publishes `layouts`, `components`, `formfields`, `widgets` and `partials`; a published file replaces the package's. Livewire pages are not published: Livewire resolves them from the package only, so a published copy would be ignored. A BREAD screen is replaced through the definition's `components`.

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
5. Every loop that renders interactive or changing rows gives its root element a `wire:key` built from a stable id (record key, slug, path), never the loop index alone when rows can be reordered or filtered.
6. A value derived from the definition or from state that only the view reads is a `#[Computed]` method. Livewire caches it for the rest of the request, so an action that reads a value and then changes what it depends on (the BREAD builder's field order, for example) must call a plain method instead, or the response shows the old value.
7. A redirect to another panel screen uses `navigate: true`, like the menu's `wire:navigate`. The login page is the exception: after signing in, the session changes and a full page load follows.
