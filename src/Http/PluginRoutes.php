<?php

declare(strict_types=1);

namespace Tardis\Http;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Tardis\Contracts\Plugins\Features\Provider\Routes;
use Tardis\Manager\PluginManager;

/**
 * Lets enabled plugins add routes to the admin panel.
 *
 * `provideRoutes()` runs inside the panel's own group: the admin prefix, the
 * `tardis.` name prefix and the `web`, locale and `tardis.admin` middleware, so
 * a plugin's pages are reachable only by someone who may open the panel. A
 * plugin that needs public routes registers them itself, outside this contract.
 *
 * Plugins register in their own service providers, which may boot after this
 * package, so routes are collected once the application has booted.
 */
final class PluginRoutes
{
    public static function define(): void
    {
        $app = app();

        if (! $app->isBooted()) {
            $app->booted(fn () => self::register());

            return;
        }

        self::register();
    }

    private static function register(): void
    {
        $plugins = app(PluginManager::class)->enabledWith(Routes::class);

        if ($plugins->isEmpty()) {
            return;
        }

        Route::middleware(['web', 'tardis.locale', 'tardis.admin'])
            ->prefix(config('tardis.admin.prefix', 'admin'))
            ->name('tardis.')
            ->group(function (Router $router) use ($plugins) {
                foreach ($plugins as $plugin) {
                    $plugin->provideRoutes($router);
                }
            });
    }
}
