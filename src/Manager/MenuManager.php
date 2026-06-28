<?php

declare(strict_types=1);

namespace Tardis\Manager;

use Illuminate\Support\Collection;
use Tardis\Classes\MenuItem;
use Tardis\Classes\UserMenuItem;
use Tardis\Contracts\Plugins\Features\Filter\FilterMenuItems;
use Tardis\Contracts\Plugins\Features\Provider\MenuItems;

class MenuManager
{
    /** @var Collection<int, MenuItem> */
    protected Collection $items;

    /** @var Collection<int, UserMenuItem> */
    protected Collection $userMenuItems;

    protected bool $collected = false;

    public function __construct()
    {
        $this->items = collect();
        $this->userMenuItems = collect();
    }

    public function addItems(MenuItem ...$items): void
    {
        foreach ($items as $item) {
            if ($item instanceof UserMenuItem) {
                $this->userMenuItems->push($item);
            } else {
                $this->items->push($item);
            }
        }
    }

    /**
     * Collect menu items from plugins and register defaults.
     * Only runs once.
     */
    public function collectFromPlugins(PluginManager $plugins): void
    {
        if ($this->collected) {
            return;
        }

        $this->collected = true;

        // Register default sidebar menu items
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
            (new MenuItem('BREAD', 'heroicon-o-table-cells'))
                ->route('tardis.bread.manage')
                ->order(50),
            MenuItem::makeDivider(),
            (new MenuItem('Permissions', 'heroicon-o-lock-closed'))
                ->route('tardis.permissions')
                ->order(60),
            (new MenuItem('Roles', 'heroicon-o-user-group'))
                ->route('tardis.roles')
                ->order(70),
        );

        // Register default user menu items
        $this->addItems(
            (new UserMenuItem('Profile', 'heroicon-o-user'))
                ->route('profile.edit')
                ->order(0),
            (new UserMenuItem('Logout', 'heroicon-o-arrow-left-on-rectangle'))
                ->url('#')
                ->method('POST')
                ->divider()
                ->order(100),
        );

        // Collect from plugins that provide menu items
        foreach ($plugins->enabledWith(MenuItems::class) as $instance) {
            $menuItems = $instance->provideMenuItems();
            foreach ($menuItems as $item) {
                $this->addItems($item);
            }
        }

        // Apply permission validation
        $this->validatePermissions($plugins);

        // Apply menu filters
        $this->applyFilters($plugins);
    }

    /**
     * Recursively validate permissions on all items.
     */
    protected function validatePermissions(PluginManager $plugins): void
    {
        $this->items = $this->items
            ->filter(fn (MenuItem $item) => $item->isVisible())
            ->map(fn (MenuItem $item) => $item->validatePermissions($plugins))
            ->values();
    }

    protected function applyFilters(PluginManager $plugins): void
    {
        foreach ($plugins->enabledWith(FilterMenuItems::class) as $instance) {
            $this->items = $instance->filterMenuItems($this->items);
        }
    }

    /**
     * Get all sidebar menu items as a flat collection (after permission validation).
     */
    public function all(): Collection
    {
        return $this->items
            ->filter(fn (MenuItem $item) => $item->isVisible())
            ->sortBy(fn (MenuItem $item) => $item->order)
            ->values();
    }

    /**
     * Get the recursive tree of menu items (parent-child structure).
     * Returns only root-level items (items without parents).
     */
    public function tree(): Collection
    {
        return $this->all();
    }

    /**
     * Get user menu items.
     */
    public function userMenu(): Collection
    {
        return $this->userMenuItems
            ->filter(fn (UserMenuItem $item) => $item->isVisible())
            ->sortBy(fn (UserMenuItem $item) => $item->order)
            ->values();
    }

    /**
     * Find a menu item by its route name.
     */
    public function findByRoute(string $routeName): ?MenuItem
    {
        return $this->findInItems($this->items, $routeName);
    }

    protected function findInItems(Collection $items, string $routeName): ?MenuItem
    {
        foreach ($items as $item) {
            if ($item->routeName === $routeName) {
                return $item;
            }

            if ($item->children->isNotEmpty()) {
                $found = $this->findInItems($item->children, $routeName);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
