# Plugin Guide

Tardis plugins can register menu items, provide settings, and participate in the admin shell without hardcoding one-off route logic.

## 1. Create a plugin

You can scaffold a plugin with the built-in command:

```bash
php artisan tardis:make-plugin blog --with-menu --with-settings
```

This creates a package under `packages/Tardis/Blog` (change it with `--package-dir`, and pass `--namespace` to avoid the default `Tardis\Blog`) containing a service provider, the plugin class, config, a routes file, a starter page and optional model/migration. Follow the three steps the command prints to require the package from your application.

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

## 3. Register and enable it

A plugin only contributes anything while it is **enabled**. Register it in a service provider's `boot()` and mark it enabled by default:

```php
<?php

use App\Plugins\BlogPlugin;
use Tardis\Manager\PluginManager;

public function boot(PluginManager $plugins): void
{
    $plugins->register('blog', BlogPlugin::class);
    $plugins->enableByDefault('blog');
}
```

`enableByDefault()` respects a disable made on the admin Plugins page (stored in `storage/tardis/plugins.json`, so a `cache:clear` does not undo it), so the plugin stays off across requests until someone enables it there. Do not call `enable()` from a provider: that is the explicit user action and would clear the stored disable on every request. The generated service provider already does both calls.

## 4. Add a route

```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'tardis.admin'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::livewire('/blog/posts', 'tardis-blog::pages.admin.posts')->name('blog.posts');
    });
```

The generated provider registers a Livewire namespace for the plugin (`tardis-blog`), so pages live in the plugin's own `resources/views/pages/admin/` rather than in the `tardis::` namespace.

> **Route order matters.** Tardis ends with the wildcard `Route::livewire('/{slug}', ...)` under the same prefix. A plugin route such as `/admin/blog/posts` has two segments and is matched by `/{slug}/{id}` unless it is registered first, so load plugin routes before Tardis' or use a distinct prefix.

Authentication and authorization plugins are **locked**: they guard the panel, so the Plugins page shows "Required" instead of a Disable button and `PluginManager::disable()` throws for them.

## Themes, assets and events

- **Theme plugins** implement `ThemePlugin` and return CSS custom properties from `getTheme()` (`['--color-primary' => 'oklch(45% 0.2 260)']`). Names must be `--custom-properties` and values may only hold colour/length/number characters; Tardis writes the `:root` rule itself.
- **Extra files:** call `Tardis::addCss()` / `Tardis::addJs()` with a URL or an `Asset` from your provider's `boot()`.
- **Hooks:** listen to `BreadSaved`, `BreadRecordCreated|Updated|Deleted` and `tardis.page` instead of patching core code.
- **Authentication plugins** implement `attempt(array $credentials, bool $remember): bool`; register after the built-in plugin and yours is used.

## 5. Best practices

- keep plugin names stable and unique
- expose menu items through the `MenuItems` contract
- do not add one-off route conflicts into the core admin route file
- use the plugin system to keep feature registration modular
- prefer `section()` grouping so the sidebar stays readable
- gate plugin pages yourself: `tardis.admin` only proves the user is logged in; use `MenuItem::permission()` for visibility and `BreadAuthorization` / your own check for access

## 6. Plugin lifecycle

The plugin manager handles registration and activation state. Once enabled, a plugin can contribute:

- menu entries
- admin views
- settings providers
- feature hooks

This keeps the package extension-friendly and avoids a monolithic admin implementation.
