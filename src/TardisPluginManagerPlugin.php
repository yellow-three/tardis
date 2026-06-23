<?php

namespace Tardis\PluginManager;

use Tardis\Core\Classes\MenuItem;
use Tardis\Core\Classes\Widget;
use Tardis\Core\Contracts\Plugins\Features\Provider\MenuItems;
use Tardis\Core\Contracts\Plugins\Features\Provider\Widgets;
use Tardis\Core\Contracts\Plugins\GenericPlugin;

class TardisPluginManagerPlugin implements GenericPlugin, MenuItems, Widgets
{
    public function name(): string
    {
        return 'tardis-plugin-manager';
    }

    public function description(): string
    {
        return 'Admin UI for managing TARDIS plugins - list, enable, disable';
    }

    public function provideMenuItems(): array
    {
        return [
            (new MenuItem('Plugins', 'heroicon-o-puzzle-piece'))
                ->route('tardis.plugins')
                ->group('System')
                ->order(25),
        ];
    }

    public function provideWidgets(): array
    {
        return [
            (new Widget('widgets.stats-plugins', 'Active Plugins'))
                ->width(3)
                ->order(40),
        ];
    }
}
