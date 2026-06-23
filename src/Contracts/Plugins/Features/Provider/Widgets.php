<?php

namespace Tardis\Contracts\Plugins\Features\Provider;

use Tardis\Classes\Widget;

interface Widgets
{
    /** @return Widget[] */
    public function provideWidgets(): array;
}
