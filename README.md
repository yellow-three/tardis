# Tardis

TARDIS Admin Panel Framework for Laravel with Livewire 4.

## Requirements

- PHP 8.3+
- Laravel 13+
- Livewire 4+

## Installation

```bash
composer require yellow-three/tardis
```

## Configuration

```bash
php artisan vendor:publish --tag=tardis-config
```

## Theme System

TARDIS uses a build-time manifest system for DaisyUI themes, providing automatic theme discovery and zero-flash theme switching.

### How it works

During build, a Vite plugin extracts theme metadata from your CSS definitions and generates a JSON manifest at `public/tardis-assets/themes-manifest.json`. This manifest is loaded by the service provider on boot and made available to both backend (ThemeManager) and frontend (Alpine store).

### Adding custom themes

To add a custom theme:

1. Define your theme in `resources/css/app.css`:

```css
@plugin "daisyui/theme" {
  name: "my-custom-theme",
  color-scheme: dark;
  --color-primary: oklch(50% 0.2 260);
  --color-secondary: oklch(60% 0.15 180);
  --color-accent: oklch(70% 0.18 80);
  --color-base-100: oklch(25% 0.02 260);
  /* ... other colors */
}
```

2. Rebuild assets:
```bash
npm run build
```

3. The theme will automatically appear in the settings page and be available for selection.

### Configuration

Publish the theme configuration to customize the manifest path:

```bash
php artisan vendor:publish --tag=tardis-themes-config
```

Configuration options in `config/tardis-themes.php`:

- `manifest_path` - Path to the themes manifest JSON file (default: `public_path('tardis-assets/themes-manifest.json')`)

### Publishing theme assets

For package consumers, publish theme assets to your application:

```bash
php artisan vendor:publish --tag=tardis-themes-assets
```

### Backend API

The `ThemeManager` provides these methods:

```php
use Tardis\Facades\Tardis;

// Get all registered themes
$themes = app(\Tardis\Manager\ThemeManager::class)->themes();

// Get a specific theme
$theme = app(\Tardis\Manager\ThemeManager::class)->resolve('tardis-light');

// Get default theme
$default = app(\Tardis\Manager\ThemeManager::class)->default();

// Get currently active theme
$active = app(\Tardis\Manager\ThemeManager::class)->active();

// Load themes from manifest file
app(\Tardis\Manager\ThemeManager::class)->loadManifest($path);
```

### Frontend API

The Alpine store provides theme data:

```javascript
// Get all available themes
$store.theme.availableThemes

// Get light themes only
$store.theme.lightThemes

// Get dark themes only
$store.theme.darkThemes
```

## Usage

```php
use Tardis\Facades\Tardis;

// Access plugin manager
Tardis::plugins()->register('my-plugin', MyPlugin::class);

// Access menu manager
$menuItems = Tardis::menu()->getItems();
```

## Livewire Components

### SFC/MFC Components

```blade
<livewire:tardis::dashboard.stats />
<livewire:tardis::bread.table :slug="'posts'" />
```

### Class-based Components

```blade
<livewire:tardis::dashboard.stats />
```

## License

MIT
