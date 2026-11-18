<?php

declare(strict_types=1);

namespace Tardis\Contracts\Plugins\Features\Provider;

use Tardis\Menu\MenuItem;

interface MenuItems
{
    /** @return MenuItem[] */
    public function provideMenuItems(): array;
}
