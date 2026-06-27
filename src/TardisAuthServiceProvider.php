<?php

namespace Tardis;

use Illuminate\Support\ServiceProvider;
use Tardis\Manager\PluginManager;

class TardisAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tardis-auth.php', 'tardis-auth');
    }

    public function boot(PluginManager $plugins): void
    {
        $plugins->register('tardis-auth', TardisAuthPlugin::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tardis-auth');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tardis-auth.php' => config_path('tardis-auth.php'),
            ], 'tardis-auth-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/tardis-auth'),
            ], 'tardis-auth-views');
        }
    }
}
