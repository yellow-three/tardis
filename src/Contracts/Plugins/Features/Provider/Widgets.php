<?php

namespace Tardis\Core\Contracts\Plugins\Features\Provider;

use Tardis\Core\Classes\Widget;

interface Widgets
{
    /** @return Widget[] */
    public function provideWidgets(): array;
}
