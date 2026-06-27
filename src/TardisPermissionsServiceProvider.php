<?php

namespace Tardis;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tardis\Manager\PluginManager;

class TardisPermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tardis-permissions.php', 'tardis-permissions');
    }

    public function boot(PluginManager $plugins): void
    {
        $plugins->register('tardis-permissions', PermissionsPlugin::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tardis-permissions');

        Livewire::addNamespace('tardis-permissions', __DIR__.'/../resources/views');

        $this->publishes([
            __DIR__.'/../config/tardis-permissions.php' => config_path('tardis-permissions.php'),
        ], 'tardis-permissions-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/tardis-permissions'),
        ], 'tardis-permissions-views');

        $this->registerMiddleware();
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }
}
