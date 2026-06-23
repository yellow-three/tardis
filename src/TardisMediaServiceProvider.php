<?php

namespace Tardis\Media;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tardis\Core\Manager\PluginManager;
use Tardis\Media\Livewire\StatsMedia;
use Tardis\Media\Manager\MediaManager;

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

        Livewire::addNamespace(
            namespace: 'tardis-media',
            classNamespace: 'Tardis\\Media\\Livewire',
            classPath: __DIR__.'/Livewire',
            classViewPath: __DIR__.'/../resources/views/livewire',
        );

        Livewire::component('widgets.stats-media', StatsMedia::class);

        $this->publishes([
            __DIR__.'/../config/tardis-media.php' => config_path('tardis-media.php'),
        ], 'tardis-media-config');

        Route::middleware(config('tardis.admin.middleware', ['web', 'auth']))
            ->prefix(config('tardis.admin.prefix', 'admin'))
            ->group(function () {
                Route::livewire('/media', 'tardis-media::media-index')
                    ->name('tardis.media');
            });
    }
}
