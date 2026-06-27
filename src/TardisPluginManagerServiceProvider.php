<?php

namespace Tardis;

use Illuminate\Support\ServiceProvider;

class TardisPluginManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tardis');
    }
}
