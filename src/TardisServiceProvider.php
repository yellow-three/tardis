<?php

declare(strict_types=1);

namespace Tardis;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tardis\Auth\TardisAuthorizationPlugin;
use Tardis\Bread\Legacy\LegacyConfigReader;
use Tardis\Commands\TardisAdminCommand;
use Tardis\Commands\TardisBreadExportCommand;
use Tardis\Commands\TardisBreadMigrateCommand;
use Tardis\Commands\TardisMakeBreadCommand;
use Tardis\Commands\TardisMakeModelCommand;
use Tardis\Commands\TardisMakePluginCommand;
use Tardis\Events\BreadRecordCreated;
use Tardis\Events\BreadRecordDeleted;
use Tardis\Events\BreadRecordUpdated;
use Tardis\Events\BreadSaved;
use Tardis\Http\Middleware\AdminMiddleware;
use Tardis\Http\Middleware\SetPanelLocale;
use Tardis\Listeners\LogBreadActivity;
use Tardis\Listeners\ProvisionBreadPermissions;
use Tardis\Manager\ActionManager;
use Tardis\Manager\AssetManager;
use Tardis\Manager\FormfieldManager;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;
use Tardis\Manager\SettingsManager;
use Tardis\Manager\ThemeManager;
use Tardis\Manager\WidgetManager;
use Tardis\Plugins\AuthenticationPlugin;
use Tardis\Support\UserPreferences;
use Tardis\Theme\ThemePreference;

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

        $this->app->singleton(AssetManager::class);
        $this->app->singleton(UserPreferences::class);

        // One instance per manager: pages resolve them with app(Class::class)
        // while host code goes through the Tardis facade, and anything
        // registered on one copy (a menu item, a field type, a widget) would be
        // invisible to the other.
        $this->app->singleton(MenuManager::class);
        $this->app->singleton(WidgetManager::class);
        $this->app->singleton(SettingsManager::class);
        $this->app->singleton(FormfieldManager::class);
        $this->app->singleton(ActionManager::class);

        // Plugin registrations must outlive the registration call: the manager
        // is resolved again by every consumer (AdminMiddleware, MenuItem,
        // BreadAuthorization), and a fresh instance each time would hand them
        // an empty registry and silently disable every plugin.
        $this->app->singleton(PluginManager::class);

        // Resolved lazily and without I/O: themes are read when first asked for.
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(ThemePreference::class);

        $this->registerAliases();
        $this->registerPluginServiceProviders();
        $this->registerDefaultAuth();
    }

    public function boot(): void
    {
        Blade::directive('tardisStyles', function ($expression) {
            return '<?php echo app(\\Tardis\\Manager\\AssetManager::class)->styles('.($expression ?: "'admin'").'); ?>';
        });

        Blade::directive('tardisScripts', function ($expression) {
            return '<?php echo app(\\Tardis\\Manager\\AssetManager::class)->scripts('.($expression ?: "'admin'").'); ?>';
        });

        $this->registerDefaultAuthorization();
        $this->registerLivewireNamespaces();
        $this->registerViews();
        $this->registerTranslations();

        Blade::anonymousComponentPath(
            __DIR__.'/../resources/views/components',
            'tardis'
        );

        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerListeners();
        $this->loadDefaultSettings();
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
        $manager->enableByDefault('tardis-auth');

    }

    /**
     * Registered in boot(), not register(): it reads host configuration, which
     * is only final once every provider has registered.
     */
    protected function registerDefaultAuthorization(): void
    {
        if (! config('tardis.authorization.enabled', true)) {
            return;
        }

        $manager = $this->app->make(PluginManager::class);
        $manager->register('tardis-authorization', TardisAuthorizationPlugin::class);
        $manager->enableByDefault('tardis-authorization');
    }

    protected function registerLivewireNamespaces(): void
    {
        Livewire::addNamespace(
            namespace: 'tardis',
            viewPath: __DIR__.'/../resources/views',
        );
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'tardis');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/tardis'),
        ], 'tardis-lang');
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

    }

    protected function registerAliases(): void
    {
        $this->app->singleton('tardis', function () {
            return new Tardis;
        });

        $this->app->singleton(LegacyConfigReader::class, function () {
            return new LegacyConfigReader(config_path('bread'));
        });
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('tardis.admin', AdminMiddleware::class);
        $router->aliasMiddleware('tardis.locale', SetPanelLocale::class);
    }

    protected function registerListeners(): void
    {
        Event::listen(BreadSaved::class, ProvisionBreadPermissions::class);

        Event::listen(
            [BreadRecordCreated::class, BreadRecordUpdated::class, BreadRecordDeleted::class],
            LogBreadActivity::class
        );
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TardisAdminCommand::class,
                TardisBreadExportCommand::class,
                TardisBreadMigrateCommand::class,
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
