<?php

namespace Tardis\Core\Manager;

use Illuminate\Support\Collection;
use Tardis\Core\Classes\MenuItem;
use Tardis\Core\Contracts\Plugins\Features\Filter\FilterMenuItems;
use Tardis\Core\Contracts\Plugins\Features\Provider\MenuItems;

class MenuManager
{
    protected Collection $items;

    public function __construct()
    {
        $this->items = collect();
    }

    public function addItems(MenuItem ...$items): void
    {
        foreach ($items as $item) {
            $this->items->push($item);
        }
    }

    public function collectFromPlugins(PluginManager $plugins): void
    {
        if ($this->items->isNotEmpty()) {
            return;
        }

        $this->addItems(
            (new MenuItem('Dashboard', 'heroicon-o-home'))
                ->route('tardis.dashboard')
                ->order(0),
            (new MenuItem('Media', 'heroicon-o-photo'))
                ->route('tardis.media')
                ->order(10),
            (new MenuItem('UI Components', 'heroicon-o-squares-2x2'))
                ->route('tardis.ui-components')
                ->order(20),
            (new MenuItem('Settings', 'heroicon-o-cog-6-tooth'))
                ->route('tardis.settings.index')
                ->order(30),
            (new MenuItem('Plugins', 'heroicon-o-puzzle-piece'))
                ->route('tardis.plugins.index')
                ->order(40),
        );

        foreach ($plugins->enabled() as $name => $plugin) {
            $instance = $plugin['instance'];

            if ($instance instanceof MenuItems) {
                $menuItems = $instance->provideMenuItems();
                foreach ($menuItems as $item) {
                    $this->items->push($item);
                }
            }
        }

        $this->applyFilters($plugins);
    }

    protected function applyFilters(PluginManager $plugins): void
    {
        foreach ($plugins->enabled() as $name => $plugin) {
            $instance = $plugin['instance'];

            if ($instance instanceof FilterMenuItems) {
                $this->items = $instance->filterMenuItems($this->items);
            }
        }
    }

    public function all(): Collection
    {
        return $this->items
            ->filter(fn (MenuItem $item) => $item->isVisible())
            ->sortBy(fn (MenuItem $item) => $item->order)
            ->values();
    }

    public function forGroup(string $group): Collection
    {
        return $this->all()
            ->filter(fn (MenuItem $item) => $item->group === $group)
            ->values();
    }

    public function groups(): Collection
    {
        return $this->all()
            ->groupBy(fn (MenuItem $item) => $item->group ?? '')
            ->sortKeys();
    }
}
