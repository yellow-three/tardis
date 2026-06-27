<?php

declare(strict_types=1);

namespace Tardis;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tardis\Bread\Sources\DatabaseBreadSource;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Http\Middleware\AdminMiddleware;

class TardisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/tardis.php',
            'tardis'
        );

        $this->registerAliases();
        $this->registerPluginServiceProviders();
    }

    public function boot(): void
    {
        $this->registerLivewireNamespaces();
        $this->registerViews();
        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerMiddleware();
    }

    protected function registerLivewireNamespaces(): void
    {
        Livewire::addNamespace(
            namespace: 'tardis',
            viewPath: __DIR__.'/../resources/views',
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

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/tardis.php' => config_path('tardis.php'),
        ], 'tardis-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/tardis'),
        ], 'tardis-views');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'tardis-migrations');

        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/tardis'),
        ], 'tardis-assets');
    }

    protected function registerAliases(): void
    {
        $this->app->singleton('tardis', function () {
            return new Tardis;
        });

        $this->app->singleton(JsonBreadSource::class, function () {
            return new JsonBreadSource(storage_path('tardis/bread'));
        });

        $this->app->singleton(DatabaseBreadSource::class, function () {
            return new DatabaseBreadSource;
        });
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('tardis.admin', AdminMiddleware::class);
    }

    protected function registerPluginServiceProviders(): void
    {
        $this->app->register(TardisPluginManagerServiceProvider::class);
        $this->app->register(TardisMediaServiceProvider::class);
        $this->app->register(TardisAuthServiceProvider::class);
        $this->app->register(TardisPermissionsServiceProvider::class);
    }
}
