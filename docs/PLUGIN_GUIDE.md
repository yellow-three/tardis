# Plugin Guide

Tardis plugins can register menu items, provide settings, and participate in the admin shell without hardcoding one-off route logic.

## 1. Create a plugin

You can scaffold a plugin with the built-in command:

```bash
php artisan tardis:make-plugin blog --with-menu --with-settings
```

This creates the base plugin folder structure and a starter service provider.

## 2. Implement the plugin contract

```php
<?php

namespace App\Plugins;

use Tardis\Classes\MenuItem;
use Tardis\Contracts\Plugins\Features\Provider\MenuItems;
use Tardis\Contracts\Plugins\GenericPlugin;

class BlogPlugin implements GenericPlugin, MenuItems
{
    public function name(): string
    {
        return 'Blog';
    }

    public function description(): string
    {
        return 'Blog management plugin for Tardis.';
    }

    public function provideMenuItems(): array
    {
        return [
            (new MenuItem('Posts', 'heroicon-o-document-text'))
                ->route('tardis.blog.posts')
                ->section('Management')
                ->order(75),
        ];
    }
}
```

## 3. Register it

Register the plugin in your service provider or application bootstrap logic:

```php
<?php

use App\Plugins\BlogPlugin;
use Tardis\Manager\PluginManager;

app(PluginManager::class)->register('blog', BlogPlugin::class);
```

You can also register the plugin from a package service provider during boot.

## 4. Add a route

```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'tardis.admin'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::livewire('/blog/posts', 'tardis::pages.blog.posts')->name('blog.posts');
    });
```

## 5. Best practices

- keep plugin names stable and unique
- expose menu items through the `MenuItems` contract
- do not add one-off route conflicts into the core admin route file
- use the plugin system to keep feature registration modular
- prefer `section()` grouping so the sidebar stays readable

## 6. Plugin lifecycle

The plugin manager handles registration and activation state. Once enabled, a plugin can contribute:

- menu entries
- admin views
- settings providers
- feature hooks

This keeps the package extension-friendly and avoids a monolithic admin implementation.
