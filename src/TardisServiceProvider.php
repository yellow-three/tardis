<?php

declare(strict_types=1);

namespace Tardis;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tardis\Bread\Sources\ConfigBreadSource;
use Tardis\Commands\TardisMakeBreadCommand;
use Tardis\Commands\TardisMakeModelCommand;
use Tardis\Commands\TardisMakePluginCommand;
use Tardis\Http\Middleware\AdminMiddleware;
use Tardis\Manager\AssetManager;
use Tardis\Manager\PluginManager;
use Tardis\Manager\SettingsManager;
use Tardis\Manager\ThemeManager;
use Tardis\Plugins\AuthenticationPlugin;

class TardisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/tardis.php',
            'tardis'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/tardis-icons.php',
            'tardis-icons'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/tardis-themes.php',
            'tardis-themes'
        );

        $this->app->singleton(AssetManager::class);

        $this->app->singleton(ThemeManager::class, function ($app) {
            $manager = new ThemeManager;

            $hotPath = AssetManager::packageHotPath();

            if (file_exists($hotPath)) {
                // Dev mode — try Vite dev server first, fallback to package disk
                $viteUrl = rtrim((string) file_get_contents($hotPath), '/');
                try {
                    $manager->loadManifestFromUrl($viteUrl.'/tardis-assets/themes-manifest.json');
                } catch (\Throwable $e) {
                    // Vite dev server may not be reachable from Docker — read from disk
                    $packageManifest = AssetManager::packageManifestPath();
                    if (file_exists($packageManifest)) {
                        try {
                            $manager->loadManifest($packageManifest);
                        } catch (\Throwable $e2) {
                            Log::debug(
                                'Vite dev manifest (disk fallback) not available: '.$e2->getMessage()
                            );
                        }
                    }
                }
            } else {
                // Production — read from disk
                $manifestPath = config(
                    'tardis-themes.manifest_path',
                    public_path('tardis-assets/themes-manifest.json')
                );

                if (file_exists($manifestPath)) {
                    try {
                        $manager->loadManifest($manifestPath);
                    } catch (\Throwable $e) {
                        Log::warning(
                            'Failed to load theme manifest: '.$e->getMessage()
                        );
                    }
                }
            }

            return $manager;
        });

        $this->registerAliases();
        $this->registerPluginServiceProviders();
        $this->registerDefaultAuth();
    }

    public function boot(): void
    {
        Blade::directive('tardisStyles', function () {
            return '<?php echo app(\\Tardis\\Manager\\AssetManager::class)->styles(); ?>';
        });

        Blade::directive('tardisScripts', function () {
            return '<?php echo app(\\Tardis\\Manager\\AssetManager::class)->scripts(); ?>';
        });

        $this->registerLivewireNamespaces();
        $this->registerViews();

        Blade::anonymousComponentPath(
            __DIR__.'/../resources/views/components',
            'tardis'
        );

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
                $manager = $this->app->make(SettingsManager::class);
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
        $manager = $this->app->make(PluginManager::class);
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

        $this->publishes([
            __DIR__.'/../config/tardis-icons.php' => config_path('tardis-icons.php'),
        ], 'tardis-icons-config');

        $this->publishes([
            __DIR__.'/../public/tardis-assets' => public_path('tardis-assets'),
        ], 'tardis-themes-assets');
    }

    protected function registerAliases(): void
    {
        $this->app->singleton('tardis', function () {
            return new Tardis;
        });

        $this->app->singleton(ConfigBreadSource::class, function () {
            return new ConfigBreadSource(config_path('bread'));
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
                TardisMakeBreadCommand::class,
                TardisMakeModelCommand::class,
                TardisMakePluginCommand::class,
            ]);
        }
    }

    protected function registerPluginServiceProviders(): void
    {
        $this->app->register(TardisPluginManagerServiceProvider::class);
        $this->app->register(TardisMediaServiceProvider::class);
    }
}
