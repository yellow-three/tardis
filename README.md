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
