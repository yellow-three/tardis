# Tardis

TARDIS is a Laravel admin framework package built around Livewire 4, DaisyUI and a modular plugin/menu system.

## Requirements

- PHP 8.3+
- Laravel 13+
- Livewire 4+
- Vite + Tailwind/DaisyUI for frontend styling

## Installation

```bash
composer require yellow-three/tardis
```

Publish the package configuration if needed:

```bash
php artisan vendor:publish --tag=tardis-config
```

Publish the compiled admin assets and theme manifest as well:

```bash
php artisan vendor:publish --tag=tardis-assets --force
php artisan vendor:publish --tag=tardis-themes-assets --force
```

Run these commands again after rebuilding the package assets with `npm run build`.

## Current architecture

The package follows the Livewire 4 page-first pattern:

- Static management screens are routed as Livewire pages
- Complex admin screens use the Multi-File Component (MFC) structure when they become large
- Simpler screens stay in the Single-File Component (SFC) format
- Sidebar/menu entries are derived from the MenuManager and plugin registrations
- Themes are discovered from a Vite manifest and exposed through the admin layout

This keeps the framework flexible while matching the official Livewire 4 convention:

- fixed screens like dashboard, settings, plugins, roles and permissions work as page components
- more complex screens like BREAD management and media flows can be split into MFC folders for clearer logic and template separation

## Route structure

The admin routes are defined in `routes/admin.php` and follow a clear split:

- Livewire routes for fixed pages
  - `/admin/dashboard`
  - `/admin/plugins`
  - `/admin/settings`
  - `/admin/permissions`
  - `/admin/roles`
  - `/admin/bread`
  - `/admin/bread/create`

- Controller routes for dynamic BREAD resource CRUD
  - `/admin/{slug}`
  - `/admin/{slug}/create`
  - `/admin/{slug}/{id}`
  - `/admin/{slug}/{id}/edit`

This keeps the system unambiguous and avoids wildcard route conflicts for the admin family.

## Menu system

The sidebar is built from the `MenuManager` and plugin-provided items.

```php
use Tardis\Facades\Tardis;

$menu = Tardis::menu();
```

Default entries include:

- Dashboard
- Media
- UI Components
- Settings
- Plugins
- BREAD
- Permissions
- Roles

Menu items can be grouped by section and can participate in active-route detection across nested admin pages.

## Theme system

TARDIS uses a build-time manifest system for DaisyUI themes.

### How it works

During build, a Vite plugin extracts theme metadata from CSS definitions and generates a manifest at `public/tardis-assets/themes-manifest.json`. The service provider loads this manifest and the admin layout exposes it to the Alpine theme store.

### Adding a custom theme

```css
@plugin "daisyui/theme" {
  name: "my-custom-theme",
  color-scheme: dark;
  --color-primary: oklch(50% 0.2 260);
  --color-secondary: oklch(60% 0.15 180);
  --color-accent: oklch(70% 0.18 80);
  --color-base-100: oklch(25% 0.02 260);
}
```

Then rebuild assets:

```bash
npm run build
```

## BREAD usage

A BREAD definition is a plain PHP config file under `config/bread/{slug}.php`. The management screens then render list/detail/edit/create screens from that metadata instead of hardcoding one-off admin pages.

The fastest way to create one is from an existing model:

```bash
php artisan tardis:make-bread "App\Models\Post"
```

This writes `config/bread/posts.php` with the fields detected from the model (fillable + schema nullability):

```php
<?php

/*
|--------------------------------------------------------------------------
| BREAD definition: posts
|--------------------------------------------------------------------------
| Managed through the Tardis admin BREAD builder. The array shape is
| compatible with BreadDefinition::fromArray().
*/

return [
    'slug' => 'posts',
    'model' => App\Models\Post::class,
    'name' => 'Post',
    'name_plural' => 'Posts',
    'fields' => [],
];
```

Definitions are read through the `BreadManager`:

```php
use Tardis\Bread\BreadManager;

app(BreadManager::class)->find('posts'); // ?BreadDefinition
app(BreadManager::class)->all();         // Collection of BreadDefinition
```

## Livewire usage

Livewire is used for the admin shell and fixed page screens, for example:

```blade
<livewire:tardis::pages.dashboard />
<livewire:tardis::pages.settings />
<livewire:tardis::pages.bread.manage />
```

For larger screens, the project follows the Livewire 4 recommendation and prefers MFC for complex pages, while keeping smaller screens in SFC. This avoids the older controller-per-page pattern for the admin UI layer and keeps the app aligned with the official component model.

## Testing

The package includes feature tests for the core admin flows and BREAD behavior.

```bash
composer test
composer test-coverage
composer lint
composer lint-fix
```

## Development workflow

Use the project scripts defined in `composer.json` as the default workflow for validation and formatting:

```bash
composer test
composer lint
```

For local coverage or formatting fixes:

```bash
composer test-coverage
composer lint-fix
```

## License

MIT
