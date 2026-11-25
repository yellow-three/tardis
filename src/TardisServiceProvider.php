<?php

declare(strict_types=1);

namespace Tardis;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tardis\Assets\AssetManager;
use Tardis\Auth\RoleAuthorizationPlugin;
use Tardis\Auth\SessionAuthenticationPlugin;
use Tardis\Bread\ActionManager;
use Tardis\Bread\Legacy\LegacyConfigReader;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Commands\ActivityPruneCommand;
use Tardis\Commands\AdminCommand;
use Tardis\Commands\BreadExportCommand;
use Tardis\Commands\BreadImportLegacyCommand;
use Tardis\Commands\DemoCommand;
use Tardis\Commands\DoctorCommand;
use Tardis\Commands\ImportVoyagerCommand;
use Tardis\Commands\InstallCommand;
use Tardis\Commands\MakeBreadCommand;
use Tardis\Commands\MakeModelCommand;
use Tardis\Commands\MakePluginCommand;
use Tardis\Commands\PluginsCommand;
use Tardis\Commands\StateCopyCommand;
use Tardis\Commands\UserKeysCommand;
use Tardis\Dashboard\DashboardLayout;
use Tardis\Dashboard\WidgetManager;
use Tardis\Events\BreadRecordCreated;
use Tardis\Events\BreadRecordDeleted;
use Tardis\Events\BreadRecordRestored;
use Tardis\Events\BreadRecordUpdated;
use Tardis\Events\BreadRemoved;
use Tardis\Events\BreadSaved;
use Tardis\Formfields\FormfieldManager;
use Tardis\Http\Middleware\AdminMiddleware;
use Tardis\Http\Middleware\SecurityHeaders;
use Tardis\Http\Middleware\SetPanelLocale;
use Tardis\Listeners\LogBreadActivity;
use Tardis\Listeners\LogBreadDefinitionChange;
use Tardis\Listeners\ProvisionBreadPermissions;
use Tardis\Media\MediaManager;
use Tardis\Menu\MenuManager;
use Tardis\Menu\MenuOverlay;
use Tardis\Notifications\PanelNotifications;
use Tardis\Notifications\TardisChannel;
use Tardis\Plugins\PluginManager;
use Tardis\Settings\SettingsManager;
use Tardis\State\DatabaseStateStore;
use Tardis\State\FileStateStore;
use Tardis\State\StateStore;
use Tardis\Support\CommandPalette;
use Tardis\Support\LegacyNames;
use Tardis\Support\UserPreferences;
use Tardis\Theme\ThemeManager;
use Tardis\Theme\ThemePreference;

class TardisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/tardis.php',
            'tardis'
        );

        $this->mergeLegacyConfig();

        require_once __DIR__.'/helpers-global.php';

        $this->app->singleton(AssetManager::class);
        $this->app->alias(AssetManager::class, 'tardis.assets');
        $this->app->singleton(UserPreferences::class);

        // One instance per manager: pages resolve them with app(Class::class)
        // while host code goes through the Tardis facade, and anything
        // registered on one copy (a menu item, a field type, a widget) would be
        // invisible to the other.
        $this->app->singleton(MenuOverlay::class);
        $this->app->singleton(MenuManager::class);
        $this->app->scoped(PanelNotifications::class);
        $this->app->scoped(CommandPalette::class);
        $this->app->singleton(DashboardLayout::class);
        $this->app->singleton(WidgetManager::class);
        $this->app->singleton(SettingsManager::class);
        $this->app->singleton(FormfieldManager::class);
        $this->app->singleton(ActionManager::class);
        $this->app->singleton(MediaManager::class);

        // Plugin registrations must outlive the registration call: the manager
        // is resolved again by every consumer (AdminMiddleware, MenuItem,
        // BreadAuthorization), and a fresh instance each time would hand them
        // an empty registry and silently disable every plugin.
        $this->app->singleton(PluginManager::class);

        // Resolved lazily and without I/O: themes are read when first asked for.
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(ThemePreference::class);

        // One BREAD source per request: every consumer (routes, menu, search,
        // authorisation) resolves BreadManager with it, so they share the
        // parsed definitions, and a long-running worker starts each request
        // with a fresh read.
        $this->app->scoped(JsonBreadSource::class);

        // Scoped too: the database driver reads its table once per request or job.
        $this->app->scoped(StateStore::class, fn () => match (config('tardis.state.driver', 'file')) {
            'database' => new DatabaseStateStore(config('tardis.state.connection')),
            'file' => new FileStateStore,
            default => throw new \InvalidArgumentException(sprintf('Unknown tardis.state.driver [%s]; use "file" or "database".', config('tardis.state.driver'))),
        });

        $this->registerBindings();
    }

    public function boot(): void
    {
        // Compiled views keep this code until view:clear, so it names a stable
        // container key rather than the class, which may move.
        Blade::directive('tardisStyles', function ($expression) {
            return "<?php echo app('tardis.assets')->styles(".($expression ?: "'admin'").'); ?>';
        });

        Blade::directive('tardisScripts', function ($expression) {
            return "<?php echo app('tardis.assets')->scripts(".($expression ?: "'admin'").'); ?>';
        });

        // Plugins are registered in boot(): register() only binds, so nothing
        // is resolved (or read from storage) before every provider has run.
        $this->registerDefaultAuthenticationPlugin();
        $this->registerDefaultAuthorizationPlugin();
        $this->registerLivewireNamespaces();
        $this->registerViews();
        $this->registerTranslations();

        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerListeners();
    }

    protected function registerDefaultAuthenticationPlugin(): void
    {
        // Register the default AuthenticationPlugin so auth works out of the box
        // without Fortify or any other auth package.
        $manager = $this->app->make(PluginManager::class);
        $manager->register(SessionAuthenticationPlugin::KEY, SessionAuthenticationPlugin::class);
        $manager->enableByDefault(SessionAuthenticationPlugin::KEY);

    }

    /**
     * Registered in boot(), not register(): it reads host configuration, which
     * is only final once every provider has registered.
     */
    protected function registerDefaultAuthorizationPlugin(): void
    {
        if (! config('tardis.authorization.enabled', true)) {
            return;
        }

        $manager = $this->app->make(PluginManager::class);
        $manager->register(RoleAuthorizationPlugin::KEY, RoleAuthorizationPlugin::class);
        $manager->enableByDefault(RoleAuthorizationPlugin::KEY);
    }

    /**
     * Livewire components live in their own root, so layouts, Blade components,
     * form field and widget views are never mistaken for components (and a
     * component file is never rendered as a plain view). Names stay
     * tardis::pages.* and tardis::components.*.
     */
    protected function registerLivewireNamespaces(): void
    {
        Livewire::addNamespace(
            namespace: 'tardis',
            viewPath: __DIR__.'/../resources/views/livewire',
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

        if (config('tardis.api.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
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

        // Only the Blade views a host can override: Livewire resolves its
        // components from the package alone, so a published page would be
        // silently ignored. Pages are replaced through BREAD components.
        $views = __DIR__.'/../resources/views';

        $this->publishes(collect(['layouts', 'components', 'formfields', 'widgets', 'partials'])
            ->mapWithKeys(fn (string $dir) => ["{$views}/{$dir}" => resource_path("views/vendor/tardis/{$dir}")])
            ->all(), 'tardis-views');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'tardis-migrations');

    }

    /**
     * Before 0.2 the media and icon settings had their own config roots
     * (config/tardis.media.php, config/tardis.icons.php). A host that
     * published them keeps its values until 0.3: they are laid over
     * tardis.media / tardis.icons, with a deprecation notice.
     */
    protected function mergeLegacyConfig(): void
    {
        $config = $this->app->make('config');

        foreach (['tardis-media' => 'tardis.media', 'tardis-icons' => 'tardis.icons'] as $old => $new) {
            $legacy = $config->get($old);

            if (! is_array($legacy) || $legacy === []) {
                continue;
            }

            @trigger_error(sprintf('config/%s.php is deprecated, move its keys to "%s" in config/tardis.php (removed in Tardis 0.3).', $old, $new), E_USER_DEPRECATED);

            $config->set($new, array_replace_recursive((array) $config->get($new, []), $legacy));
        }
    }

    protected function registerBindings(): void
    {
        $this->app->singleton(TardisManager::class);
        $this->app->alias(TardisManager::class, 'tardis');

        // Pre-0.2 class names resolve to the same instances (LegacyNames).
        foreach (LegacyNames::MAP as $old => $new) {
            $this->app->alias($new, $old);
        }

        $this->app->singleton(LegacyConfigReader::class, function () {
            return new LegacyConfigReader(config_path('bread'));
        });
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('tardis.admin', AdminMiddleware::class);
        $router->aliasMiddleware('tardis.locale', SetPanelLocale::class);

        // Livewire sends every request after the first to its own endpoint and
        // re-applies only the route middleware it is told to persist. Without
        // this the panel's guard runs once, on the page load, and a page that
        // was open when the session ended (or the role was removed) keeps
        // answering.
        // The locale too: a failed sign-in or a validation message on any
        // page is rendered by a Livewire update, not the first request.
        Livewire::addPersistentMiddleware([SetPanelLocale::class, AdminMiddleware::class, SecurityHeaders::class]);

        // Livewire replays persistent middleware in an inner pipeline and drops
        // the response it builds, so that pass cannot put headers on the actual
        // update reply. Add the middleware to the update route as well.
        $this->app->booted(function (): void {
            foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
                if (str_ends_with((string) $route->getName(), 'livewire.update')) {
                    $route->middleware(SecurityHeaders::class);
                }
            }
        });
    }

    protected function registerListeners(): void
    {
        // $notifiable->notify(new X) with via() => ['tardis'] reaches the bell.
        Notification::resolved(fn (ChannelManager $channels) => $channels->extend('tardis', fn ($app) => $app->make(TardisChannel::class)));

        Event::listen(BreadSaved::class, ProvisionBreadPermissions::class);
        Event::listen([BreadSaved::class, BreadRemoved::class], LogBreadDefinitionChange::class);

        Event::listen(
            [BreadRecordCreated::class, BreadRecordUpdated::class, BreadRecordDeleted::class, BreadRecordRestored::class],
            LogBreadActivity::class
        );
    }

    protected function registerCommands(): void
    {
        // Not limited to the console: the panel's command runner calls
        // Artisan from a web request and may be allowed to run these.
        $this->commands([
            ActivityPruneCommand::class,
            AdminCommand::class,
            BreadExportCommand::class,
            BreadImportLegacyCommand::class,
            ImportVoyagerCommand::class,
            DemoCommand::class,
            DoctorCommand::class,
            InstallCommand::class,
            MakeBreadCommand::class,
            MakeModelCommand::class,
            MakePluginCommand::class,
            PluginsCommand::class,
            StateCopyCommand::class,
            UserKeysCommand::class,
        ]);
    }
}
