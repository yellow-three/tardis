<?php

namespace Tardis;

use Illuminate\Support\ServiceProvider;
use Tardis\Manager\PluginManager;

class TardisMediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tardis-media.php', 'tardis-media');

        $this->app->singleton(MediaManager::class, fn () => new MediaManager);
    }

    public function boot(PluginManager $plugins): void
    {
        $plugins->register('tardis-media', MediaPlugin::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tardis-media');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/tardis-media.php' => config_path('tardis-media.php'),
        ], 'tardis-media-config');
    }
}
