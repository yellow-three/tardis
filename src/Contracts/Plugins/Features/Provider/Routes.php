<?php

namespace Tardis\Contracts\Plugins\Features\Provider;

use Illuminate\Routing\Router;

interface Routes
{
    public function provideRoutes(Router $router): void;
}
