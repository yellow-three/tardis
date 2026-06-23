<?php

namespace Tardis\Core\Contracts\Plugins\Features\Provider;

use Tardis\Core\Classes\MenuItem;

interface MenuItems
{
    /** @return MenuItem[] */
    public function provideMenuItems(): array;
}
