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
        // SFC/MFC components
        Livewire::addNamespace(
            namespace: 'tardis',
            viewPath: __DIR__.'/../resources/views/livewire',
        );

        // Class-based components
        Livewire::addNamespace(
            namespace: 'tardis',
            classNamespace: 'Tardis\\Livewire',
            classPath: __DIR__.'/Livewire',
            classViewPath: __DIR__.'/../resources/views/livewire',
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
}
