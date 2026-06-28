<?php

declare(strict_types=1);

namespace Tardis;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tardis\Bread\Sources\DatabaseBreadSource;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Http\Middleware\AdminMiddleware;
use Tardis\Plugins\AuthenticationPlugin;

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
        $this->registerDefaultAuth();
    }

    public function boot(): void
    {
        $this->registerLivewireNamespaces();
        $this->registerViews();
        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerMiddleware();
        $this->registerCommands();
    }

    /**
     * Load default settings from the preset file on first install.
     */
    protected function loadDefaultSettings(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $presetPath = __DIR__.'/../resources/presets/settings.json';

        if (! file_exists(storage_path('tardis/settings/settings.json'))) {
            try {
                $manager = $this->app->make(\Tardis\Manager\SettingsManager::class);
                $manager->loadPreset($presetPath);
            } catch (\Throwable) {
                // Storage not available yet (e.g. during package discovery)
            }
        }
    }

    protected function registerDefaultAuth(): void
    {
        // Register the default AuthenticationPlugin so auth works out of the box
        // without Fortify or any other auth package.
        $manager = $this->app->make(\Tardis\Manager\PluginManager::class);
        $manager->register('tardis-auth', AuthenticationPlugin::class);
        $manager->enable('tardis-auth');
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

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Tardis\Commands\TardisMakePluginCommand::class,
            ]);
        }
    }

    protected function registerPluginServiceProviders(): void
    {
        $this->app->register(TardisPluginManagerServiceProvider::class);
        $this->app->register(TardisMediaServiceProvider::class);
    }

}
