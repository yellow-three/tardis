<?php

namespace Tardis\PluginManager;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tardis\Manager\PluginManager;

class TardisPluginManagerServiceProvider extends ServiceProvider
{
    public function boot(PluginManager $plugins): void
    {
        $plugins->register('tardis-plugin-manager', TardisPluginManagerPlugin::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tardis-plugin-manager');

        Livewire::addNamespace('tardis-plugin-manager', __DIR__.'/../resources/views');

        Route::middleware(config('tardis.admin.middleware', ['web', 'auth']))
            ->prefix(config('tardis.admin.prefix', 'admin'))
            ->group(function () {
                Route::livewire('/plugins', 'tardis-plugin-manager::pages.admin.⚡plugins')
                    ->name('tardis.plugins');
            });
    }
}
