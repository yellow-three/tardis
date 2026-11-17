<?php

declare(strict_types=1);

namespace Tardis;

use Illuminate\Support\ServiceProvider;

class TardisPluginManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tardis');
    }
}
