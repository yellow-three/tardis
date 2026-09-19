# Livewire 4 page organization

This project follows the Livewire 4 guidance for page components and keeps the structure limited to the supported formats:

- SFC = single-file component
- MFC = multi-file component
- no class-based page components for the admin shell

## Rule of thumb

### Use SFC for simple pages

Use a single-file component when the page is compact and keeps its logic inside one file.

Good candidates in this project:

- dashboard
- login
- forgot-password
- reset-password
- permissions
- roles
- settings (moderate complexity)

These are straightforward admin screens where a single file keeps the page readable.

### Use MFC for complex pages

Use a multi-file component when a page has substantial state, nested UI, data-heavy forms, or more than one concern.

Recommended candidates in this project:

- media-browser
- database
- search
- bread-builder
- bread management
- bread create / edit / read flows

These pages benefit from separation into:

- PHP class file
- Blade view
- JS if needed
- scoped CSS if needed
- optional tests

## Project pattern

The admin shell is already page-first, which matches the Livewire 4 docs:

```php
Route::livewire('/dashboard', 'tardis::pages.dashboard');
Route::livewire('/bread', 'tardis::pages.bread.manage');
```

This is the correct pattern for full-page screens.

## Recommended file layout

### SFC example

```text
resources/views/pages/dashboard.blade.php
```

### MFC example

```text
resources/views/pages/bread/
├── manage.blade.php
├── manage/
│   ├── manage.php
│   ├── manage.blade.php
│   ├── manage.js
│   └── manage.css
```

This gives the project a clean split between simple pages and more complex pages without introducing class-based components or controller-driven page rendering.

## Important constraint

The project should not mix in class-based components for normal page screens unless there is a specific migration need. The docs recommend SFC and MFC for new Livewire 4 projects, and this package is already aligned with that model.

## Practical recommendation

1. Leave simple pages as SFC
2. Convert the heavier CRUD/admin screens to MFC
3. Keep route layer as Livewire page routes, not controller page routes
4. Reserve controller usage for non-page backend tasks, not for the page shell itself

This keeps the project consistent with the official Livewire 4 documentation and the existing architecture.
