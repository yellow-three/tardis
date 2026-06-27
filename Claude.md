Sen bir Laravel paket geliştirme uzmanısın. Aşağıda `yellow-three/tardis`
projesinin mevcut durumu ve yapılması gereken tüm düzenlemeler detaylıca
açıklanmıştır. Repo'yu klonla, aşağıdaki tüm adımları sırayla uygula.

## REPO

<https://github.com/yellow-three/tardis>

## TECH STACK

- PHP 8.3+
- Laravel 13
- Livewire 4 — Sadece MFC (Multi-File Component) kullanılacak
- Pest 4
- Spatie Laravel Permission 6
- Blade Heroicons 2

- - -
## LİVEWİRE 4 MFC NEDİR

MFC (Multi-File Component): Her component en az iki dosyadan oluşur:

- `component-name.php` → anonymous class, iş mantığı
- `component-name.blade.php` → Blade şablonu

Her iki dosya da `resources/views/livewire/` altında yaşar. `src/Livewire/` gibi
bir klasör YOKTUR. `render()` metodu YOKTUR. ⚡ emoji YOKTUR (paket geliştirmede
Composer sorununa yol açar).

MFC dosya formatı:

```php
<?php // resources/views/livewire/bread/table.php
use Livewire\Component;
new class extends Component {
    public string $slug = '';
    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }
};
{{-- resources/views/livewire/bread/table.blade.php --}}
<div>
    {{-- içerik --}}
</div>
```
- - -
## MEVCUT DURUM

### Var olan ve çalışan dosyalar:

- `composer.json` ✅
- `src/Tardis.php` ✅
- `src/TardisServiceProvider.php` ✅ (düzenleme gerekli)
- `src/Manager/PluginManager.php` ✅
- `src/Manager/MenuManager.php` ✅
- `src/Manager/FormfieldManager.php` ✅
- `src/Manager/MediaManager.php` ✅
- `src/Manager/SettingsManager.php` ✅
- `src/Manager/WidgetManager.php` ✅
- `src/Contracts/Plugins/AuthenticationPlugin.php` ✅
- `src/Contracts/Plugins/AuthorizationPlugin.php` ✅
- `src/Contracts/Plugins/FormfieldPlugin.php` ✅
- `src/Contracts/Plugins/GenericPlugin.php` ✅
- `src/Contracts/Plugins/ThemePlugin.php` ✅
- `config/tardis.php` ✅
- `config/tardis-auth.php` ✅
- `config/tardis-media.php` ✅
- `config/tardis-permissions.php` ✅

- - -
## SORUN 1 — NAMESPACE / DOSYA KONUMU UYUMSUZLUKLARI

Aşağıdaki dosyalar `src/` kökünde duruyor ama namespace'leri alt klasörlere
işaret ediyor. Her birini doğru konuma taşı, eski dosyayı sil:


|Mevcut Dosya|Namespace|Doğru Konum|
|-|-|-|
|`src/TardisAuthPlugin.php`|`Tardis\\\\\\\\Auth`|`src/Auth/TardisAuthPlugin.php`|
|`src/TardisAuthServiceProvider.php`|`Tardis\\\\\\\\Auth`|`src/Auth/TardisAuthServiceProvider.php`|
|`src/MediaPlugin.php`|`Tardis\\\\\\\\Media`|`src/Media/MediaPlugin.php`|
|`src/TardisMediaServiceProvider.php`|`Tardis\\\\\\\\Media`|`src/Media/TardisMediaServiceProvider.php`|
|`src/PermissionsPlugin.php`|`Tardis\\\\\\\\Permissions`|`src/Permissions/PermissionsPlugin.php`|
|`src/TardisPermissionsServiceProvider.php`|`Tardis\\\\\\\\Permissions`|`src/Permissions/TardisPermissionsServiceProvider.php`|
|`src/TardisPluginManagerPlugin.php`|`Tardis\\\\\\\\PluginManager`|`src/PluginManager/TardisPluginManagerPlugin.php`|
|`src/TardisPluginManagerServiceProvider.php`|`Tardis\\\\\\\\PluginManager`|`src/PluginManager/TardisPluginManagerServiceProvider.php`|

Taşıma sonrası `src/Auth/TardisAuthServiceProvider.php` içindeki `
TardisAuthPlugin::class` use statement'ının güncellendiğinden emin ol.

- - -
## SORUN 2 — EKSİK PHP DOSYALARI (OLUŞTURULACAK)

### 2a. `src/Classes/MenuItem.php`

```php
<?php
declare(strict_types=1);
namespace Tardis\Classes;
class MenuItem
{
    public ?string $route = null;
    public ?string $group = null;
    public int $order = 0;
    public ?string $permission = null;
    protected bool $visible = true;
    public function __construct(
        public string $label,
        public string $icon,
    ) {}
    public function route(string $route): static
    {
        $this->route = $route;
        return $this;
    }
    public function group(string $group): static
    {
        $this->group = $group;
        return $this;
    }
    public function order(int $order): static
    {
        $this->order = $order;
        return $this;
    }
    public function permission(string $permission): static
    {
        $this->permission = $permission;
        return $this;
    }
    public function hide(): static
    {
        $this->visible = false;
        return $this;
    }
    public function isVisible(): bool
    {
        return $this->visible;
    }
}
```
### 2b. `src/Classes/Widget.php`

```php
<?php
declare(strict_types=1);
namespace Tardis\Classes;
class Widget
{
    public int $width = 3;
    public int $order = 0;
    public function __construct(
        public string $view,
        public string $title,
    ) {}
    public function width(int $width): static
    {
        $this->width = $width;
        return $this;
    }
    public function order(int $order): static
    {
        $this->order = $order;
        return $this;
    }
}
```
### 2c. `src/Facades/Tardis.php`

```php
<?php
declare(strict_types=1);
namespace Tardis\Facades;
use Illuminate\Support\Facades\Facade;
class Tardis extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tardis';
    }
}
```
### 2d. `src/Manager/BreadManager.php`

```php
<?php
declare(strict_types=1);
namespace Tardis\Manager;
class BreadManager
{
    protected array $breads = [];
    public function register(string $slug, array $config): void
    {
        $this->breads[$slug] = $config;
    }
    public function all(): array
    {
        return $this->breads;
    }
    public function get(string $slug): ?array
    {
        return $this->breads[$slug] ?? null;
    }
    public function has(string $slug): bool
    {
        return isset($this->breads[$slug]);
    }
}
```
### 2e. `src/Contracts/Plugins/Features/Provider/MenuItems.php`

```php
<?php
declare(strict_types=1);
namespace Tardis\Contracts\Plugins\Features\Provider;
interface MenuItems
{
    public function provideMenuItems(): array;
}
```
### 2f. `src/Contracts/Plugins/Features/Provider/Widgets.php`

```php
<?php
declare(strict_types=1);
namespace Tardis\Contracts\Plugins\Features\Provider;
interface Widgets
{
    public function provideWidgets(): array;
}
```
### 2g. `src/Contracts/Plugins/Features/Filter/FilterMenuItems.php`

```php
<?php
declare(strict_types=1);
namespace Tardis\Contracts\Plugins\Features\Filter;
use Illuminate\Support\Collection;
interface FilterMenuItems
{
    public function filterMenuItems(Collection $items): Collection;
}
```
### 2h. `src/Http/Middleware/TardisAdminMiddleware.php`

```php
<?php
declare(strict_types=1);
namespace Tardis\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Tardis\Contracts\Plugins\AuthenticationPlugin;
use Tardis\Facades\Tardis;
class TardisAdminMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $authPlugin = Tardis::plugins()
            ->enabledWith(AuthenticationPlugin::class)
            ->first();
        if ($authPlugin === null) {
            abort(403, 'No authentication plugin configured.');
        }
        return $authPlugin->handle($request, $next);
    }
}
```
- - -
## SORUN 3 — TardisServiceProvider GÜNCELLEMESİ

`src/TardisServiceProvider.php` dosyasını aşağıdaki şekilde güncelle:

```php
<?php
declare(strict_types=1);
namespace Tardis;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
class TardisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tardis.php', 'tardis');
        $this->registerAliases();
    }
    public function boot(): void
    {
        $this->registerLivewireNamespace();
        $this->registerViews();
        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerMiddleware();
        $this->registerPublishing();
    }
    protected function registerLivewireNamespace(): void
    {
        // MFC için sadece viewPath parametresi yeterli
        Livewire::addNamespace(
            namespace: 'tardis',
            viewPath: __DIR__.'/../resources/views/livewire',
        );
    }
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tardis');
    }
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
    }
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware(
            'tardis.admin',
            \Tardis\Http\Middleware\TardisAdminMiddleware::class,
        );
    }
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }
        $this->publishes([
            __DIR__.'/../config/tardis.php' => config_path('tardis.php'),
        ], 'tardis-config');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/tardis'),
        ], 'tardis-views');
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'tardis-migrations');
    }
    protected function registerAliases(): void
    {
        $this->app->bind('tardis', fn () => new Tardis);
    }
}
```
- - -
## SORUN 4 — ROUTES GÜNCELLEMESİ

`routes/admin.php` dosyasını güncelle. MFC component'leri doğrudan route'a
bağlanamaz — route'lar page view'larına yönlendirir, page view'ları ise `
\\\\\\\<livewire:tardis::...>` ile component'leri embed eder:

```php
<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['web', 'tardis.admin', 'verified'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::view('/dashboard', 'tardis::pages.dashboard')
            ->name('dashboard');
        Route::view('/plugins', 'tardis::pages.plugins.index')
            ->name('plugins.index');
        Route::view('/settings', 'tardis::pages.settings.index')
            ->name('settings.index');
        Route::view('/ui-components', 'tardis::pages.ui-components')
            ->name('ui-components');
        // BREAD — slug'ı view'a geçirmek için closure kullan
        Route::get('/{slug}', fn (string $slug) => view('tardis::pages.bread.index', compact('slug')))
            ->name('bread.index');
        Route::get('/{slug}/create', fn (string $slug) => view('tardis::pages.bread.create', compact('slug')))
            ->name('bread.create');
        Route::get('/{slug}/{id}/read', fn (string $slug, int $id) => view('tardis::pages.bread.read', compact('slug', 'id')))
            ->name('bread.read');
        Route::get('/{slug}/{id}/edit', fn (string $slug, int $id) => view('tardis::pages.bread.edit', compact('slug', 'id')))
            ->name('bread.edit');
    });
```
- - -
## SORUN 5 — VIEW DOSYALARI (OLUŞTURULACAK)

### Dizin yapısı:

resources/views/

├── layouts/

│   └── admin.blade.php

├── pages/

│   ├── dashboard.blade.php

│   ├── ui-components.blade.php

│   ├── bread/

│   │   ├── index.blade.php

│   │   ├── create.blade.php

│   │   ├── read.blade.php

│   │   └── edit.blade.php

│   ├── plugins/

│   │   └── index.blade.php

│   └── settings/

│       └── index.blade.php

└── livewire/

├── dashboard/

│   ├── stats.php

│   └── stats.blade.php

├── bread/

│   ├── table.php

│   ├── table.blade.php

│   ├── form.php

│   ├── form.blade.php

│   ├── read.php

│   └── read.blade.php

├── plugins/

│   ├── index.php

│   └── index.blade.php

└── settings/

├── index.php

└── index.blade.php

### Layout: `resources/views/layouts/admin.blade.php`

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }}</title>
        @livewireStyles
    </head>
    <body>
        {{ $slot }}
        @livewireScripts
    </body>
</html>
```
### Page views (iskelet — her biri aynı pattern):

`resources/views/pages/dashboard.blade.php`:

```blade
<x-tardis::layouts.admin>
    <livewire:tardis::dashboard.stats />
</x-tardis::layouts.admin>
```
`resources/views/pages/plugins/index.blade.php`:

```blade
<x-tardis::layouts.admin>
    <livewire:tardis::plugins.index />
</x-tardis::layouts.admin>
```
`resources/views/pages/settings/index.blade.php`:

```blade
<x-tardis::layouts.admin>
    <livewire:tardis::settings.index />
</x-tardis::layouts.admin>
```
`resources/views/pages/bread/index.blade.php`:

```blade
<x-tardis::layouts.admin>
    <livewire:tardis::bread.table :slug="$slug" />
</x-tardis::layouts.admin>
```
`resources/views/pages/bread/create.blade.php`:

```blade
<x-tardis::layouts.admin>
    <livewire:tardis::bread.form :slug="$slug" />
</x-tardis::layouts.admin>
```
`resources/views/pages/bread/edit.blade.php`:

```blade
<x-tardis::layouts.admin>
    <livewire:tardis::bread.form :slug="$slug" :id="$id" />
</x-tardis::layouts.admin>
```
`resources/views/pages/bread/read.blade.php`:

```blade
<x-tardis::layouts.admin>
    <livewire:tardis::bread.read :slug="$slug" :id="$id" />
</x-tardis::layouts.admin>
```
`resources/views/pages/ui-components.blade.php`:

```blade
<x-tardis::layouts.admin>
    <div>UI Components</div>
</x-tardis::layouts.admin>
```
### MFC Component'leri (PHP dosyaları — iskelet):

`resources/views/livewire/dashboard/stats.php`:

```php
<?php
use Livewire\Component;
new class extends Component {
    // dashboard widget'ları buraya gelecek
};
```
`resources/views/livewire/bread/table.php`:

```php
<?php
use Livewire\Component;
new class extends Component {
    public string $slug = '';
    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }
};
```
`resources/views/livewire/bread/form.php`:

```php
<?php
use Livewire\Component;
new class extends Component {
    public string $slug = '';
    public ?int $id = null;
    public function mount(string $slug, ?int $id = null): void
    {
        $this->slug = $slug;
        $this->id = $id;
    }
};
```
`resources/views/livewire/bread/read.php`:

```php
<?php
use Livewire\Component;
new class extends Component {
    public string $slug = '';
    public int $id = 0;
    public function mount(string $slug, int $id): void
    {
        $this->slug = $slug;
        $this->id = $id;
    }
};
```
`resources/views/livewire/plugins/index.php`:

```php
<?php
use Livewire\Component;
new class extends Component {
    // plugin listesi buraya gelecek
};
```
`resources/views/livewire/settings/index.php`:

```php
<?php
use Livewire\Component;
new class extends Component {
    // ayarlar buraya gelecek
};
```
### MFC Component view'ları (Blade dosyaları — iskelet):

Her `.blade.php` için basit placeholder:

`resources/views/livewire/dashboard/stats.blade.php`:

```blade
<div>
    {{-- Dashboard Stats --}}
</div>
```
`resources/views/livewire/bread/table.blade.php`:

```blade
<div>
    {{-- Bread Table: {{ $slug }} --}}
</div>
```
`resources/views/livewire/bread/form.blade.php`:

```blade
<div>
    {{-- Bread Form: {{ $slug }} {{ $id ?? 'new' }} --}}
</div>
```
`resources/views/livewire/bread/read.blade.php`:

```blade
<div>
    {{-- Bread Read: {{ $slug }} #{{ $id }} --}}
</div>
```
`resources/views/livewire/plugins/index.blade.php`:

```blade
<div>
    {{-- Plugins Index --}}
</div>
```
`resources/views/livewire/settings/index.blade.php`:

```blade
<div>
    {{-- Settings Index --}}
</div>
```
- - -
## SORUN 6 — PEST YAPILANDIRMASI

### `tests/Pest.php`

```php
<?php
uses(Tests\TestCase::class)->in('Feature', 'Unit');
```
### `tests/TestCase.php`

```php
<?php
namespace Tests;
use Orchestra\Testbench\TestCase as Orchestra;
use Tardis\TardisServiceProvider;
class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TardisServiceProvider::class,
        ];
    }
    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }
}
```
### `tests/Feature/TardisServiceProviderTest.php`

```php
<?php
it('registers the tardis service', function () {
    expect(app('tardis'))->toBeInstanceOf(\Tardis\Tardis::class);
});
it('registers the plugin manager', function () {
    expect(\Tardis\Facades\Tardis::plugins())
        ->toBeInstanceOf(\Tardis\Manager\PluginManager::class);
});
it('registers the menu manager', function () {
    expect(\Tardis\Facades\Tardis::menu())
        ->toBeInstanceOf(\Tardis\Manager\MenuManager::class);
});
it('registers the bread manager', function () {
    expect(\Tardis\Facades\Tardis::bread())
        ->toBeInstanceOf(\Tardis\Manager\BreadManager::class);
});
```
- - -
## SORUN 7 — composer.json'a suggest ekle

```json
"suggest": {
    "laravel/fortify": "Built-in TardisAuthPlugin için gerekli (^1.37)"
},
```
- - -
## ÖZET KONTROL LİSTESİ

Tüm adımlar tamamlandıktan sonra şunları doğrula:

1.  `composer dump-autoload` hatasız çalışıyor
2.  `vendor/bin/pest` çalışıyor ve 4 test geçiyor
3.  `vendor/bin/pint` kod stilini düzeltiyor
4.  `src/` altında hiçbir dosyada namespace ↔ konum uyumsuzluğu yok
5.  `src/Livewire/` diye bir klasör YOK — tüm component'ler `
    resources/views/livewire/` altında
6.  `resources/views/livewire/` altında ⚡ emoji içeren dosya YOK
7.  `routes/admin.php`'de `Route::livewire()` kullanımı YOK
8.  Tüm MFC `.php` dosyaları `new class extends Component` anonymous class
    formatında
9.  MFC `.php` dosyalarında `render()` metodu YOK
10. `database/migrations/` en az bir `.gitkeep` içeriyor

Her commit'i mantıklı gruplara ayır:

- `fix: resolve namespace/file location mismatches`
- `feat: add missing Classes (MenuItem, Widget)`
- `feat: add Facades, BreadManager, Contracts`
- `feat: add TardisAdminMiddleware`
- `refactor: update TardisServiceProvider for MFC`
- `feat: add MFC components and page views`
- `feat: update routes for MFC architecture`
- `test: add Pest setup and initial tests`
