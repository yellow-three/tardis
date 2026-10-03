<?php

declare(strict_types=1);

namespace Tardis\Manager;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
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
                ->section('Overview')
                ->order(0),
            (new MenuItem('Media', 'heroicon-o-photo'))
                ->route('tardis.media')
                ->permission(Abilities::MEDIA_BROWSE)
                ->section('Overview')
                ->activeMode('prefix')
                ->order(10),
            (new MenuItem('UI Components', 'heroicon-o-squares-2x2'))
                ->route('tardis.ui-components')
                ->section('Overview')
                ->order(20),
            (new MenuItem('Settings', 'heroicon-o-cog-6-tooth'))
                ->route('tardis.settings.index')
                ->permission(Abilities::SETTINGS)
                ->section('Management')
                ->activeMode('prefix')
                ->order(30),
            (new MenuItem('Plugins', 'heroicon-o-puzzle-piece'))
                ->route('tardis.plugins.index')
                ->permission(Abilities::PLUGINS)
                ->section('Management')
                ->order(40),
            (new MenuItem('Database Explorer', 'heroicon-o-circle-stack'))
                ->route('tardis.database.index')
                ->permission(Abilities::DATABASE)
                ->section('Management')
                ->activeMode('prefix')
                ->order(45),
            (new MenuItem('BREAD', 'heroicon-o-table-cells'))
                ->route('tardis.bread.manage')
                ->permission(Abilities::BREAD)
                ->section('Management')
                // The builder screens share the tardis.bread.* prefix with the
                // BREAD resource screens, so they are listed explicitly here
                // instead of relying on a prefix match.
                ->activeOnRoutes([
                    'tardis.bread.manage',
                    'tardis.bread.create',
                    'tardis.bread.edit',
                ])
                ->order(50),
            MenuItem::makeDivider(),
            (new MenuItem('Permissions', 'heroicon-o-lock-closed'))
                ->route('tardis.permissions')
                ->permission(Abilities::ROLES)
                ->section('Access')
                ->order(60),
            (new MenuItem('Users', 'heroicon-o-users'))
                ->route('tardis.users.index')
                ->permission(Abilities::USERS)
                ->section('Access')
                ->order(55),
            (new MenuItem('Roles', 'heroicon-o-user-group'))
                ->route('tardis.roles')
                ->permission(Abilities::ROLES)
                ->section('Access')
                ->order(70),
        );

        // Register a sidebar entry for every BREAD definition
        $this->addItems(...$this->breadMenuItems());

        // Register default user menu items
        // The profile screen belongs to the host application (Breeze, Jetstream,
        // Fortify...), so the link is only offered when that route exists.
        if (Route::has('profile.edit')) {
            $this->addItems(
                (new UserMenuItem('Profile', 'heroicon-o-user'))
                    ->route('profile.edit')
                    ->order(0),
            );
        }

        $this->addItems(
            (new UserMenuItem('Logout', 'heroicon-o-arrow-left-on-rectangle'))
                ->route('tardis.logout')
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
     * Build a sidebar menu item for every BREAD definition.
     *
     * Each item points at the resource's list screen
     * (`tardis.bread.index` with the definition's slug), and stays active
     * across that resource's own create/read/edit routes.
     *
     * The route names are listed explicitly rather than matched by prefix:
     * `tardis.bread.*` also covers the BREAD builder pages, which carry the
     * same `{slug}`, so a prefix match would highlight the "posts" resource
     * while the posts *definition* was being edited.
     *
     * @return array<int, MenuItem>
     */
    protected function breadMenuItems(): array
    {
        return app(BreadManager::class)
            ->all()
            ->map(fn (BreadDefinition $bread) => (new MenuItem($bread->namePlural, $this->breadMenuIcon($bread->icon)))
                ->route('tardis.bread.index', ['slug' => $bread->slug])
                ->permission(BreadAuthorization::ability('browse', $bread->slug))
                ->section('BREAD')
                ->activeOnRoutes([
                    'tardis.bread.index',
                    'tardis.bread.add',
                    'tardis.bread.read',
                    'tardis.bread.edit.item',
                ])
                ->order(100))
            ->values()
            ->all();
    }

    /**
     * BREAD definitions store a short icon name (e.g. "link"), but the menu
     * partial renders icons as Blade components, so the heroicon prefix has to
     * be added to keep them resolvable.
     */
    protected function breadMenuIcon(?string $icon): string
    {
        $icon = trim((string) $icon);

        if ($icon === '') {
            return 'heroicon-o-table-cells';
        }

        // Already a fully qualified Blade component.
        if (str_starts_with($icon, 'heroicon') || str_contains($icon, ':')) {
            return $icon;
        }

        return 'heroicon-o-'.$icon;
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

    public function tree(): Collection
    {
        return $this->all();
    }

    /**
     * Get menu items grouped by section, with section labels.
     * Items without a section are returned as a flat list.
     *
     * @return Collection<int, array{section: ?string, items: Collection}|MenuItem>
     */
    public function treeBySection(): Collection
    {
        $items = $this->all();

        $grouped = $items->groupBy(fn (MenuItem $item) => $item->section ?? '__nosection__');

        $result = collect();

        foreach ($grouped as $section => $groupItems) {
            if ($section === '__nosection__') {
                // Items without a section — add directly
                foreach ($groupItems as $item) {
                    $result->push($item);
                }
            } else {
                $result->push((object) [
                    'section' => $section,
                    'items' => $groupItems,
                ]);
            }
        }

        return $result;
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
