<?php

namespace Tardis;

use Illuminate\Support\ServiceProvider;

class TardisPluginManagerServiceProvider extends ServiceProvider
{
    public function boot(PluginManager $plugins): void
    {
        $plugins->register('tardis-plugin-manager', TardisPluginManagerPlugin::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tardis-plugin-manager');
    }
}
