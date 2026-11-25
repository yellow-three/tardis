<?php

declare(strict_types=1);

namespace Tardis\Menu;

use BladeUI\Icons\Factory;
use Illuminate\Support\Collection;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Contracts\Plugins\Features\Filter\FilterMenuItems;
use Tardis\Contracts\Plugins\Features\Provider\MenuItems;
use Tardis\Plugins\PluginManager;
use Tardis\Settings\SettingsManager;

class MenuManager
{
    /** @var Collection<int, MenuItem> */
    protected Collection $items;

    /** @var Collection<int, UserMenuItem> */
    protected Collection $userMenuItems;

    /**
     * Items a host or plugin added with addItems(), usually at boot. They are
     * the registry; the menu itself is rebuilt from them for every request.
     *
     * @var array<int, MenuItem>
     */
    protected array $registered = [];

    /**
     * The request and locale the current menu was built for. A long-running
     * worker serves many requests with one instance: titles are translated,
     * BREADs listed and the menu builder's choices applied per request.
     */
    protected ?string $collectedFor = null;

    public function __construct()
    {
        $this->items = collect();
        $this->userMenuItems = collect();
    }

    /**
     * The UI components reference page follows the admin.ui-components
     * setting ("show" or "hide").
     */
    public static function uiComponentsEnabled(): bool
    {
        return app(SettingsManager::class)->get('admin.ui-components', 'show') !== 'hide';
    }

    public function addItems(MenuItem ...$items): void
    {
        foreach ($items as $item) {
            $this->registered[] = $item;

            // Listed right away as well; once a menu has been built from the
            // registry, as a copy, like the items it was built from.
            $this->push($this->collectedFor === null ? $item : clone $item);
        }
    }

    /**
     * Rebuild the menu on the next collectFromPlugins() even within this
     * request, after its inputs changed (the menu builder saving a choice).
     */
    public function forgetCollected(): void
    {
        $this->collectedFor = null;
    }

    protected function push(MenuItem ...$items): void
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
     * Build the menu: the registered items, the defaults, the BREADs, plugin
     * items and the menu builder's links and choices. Built once per request
     * (and locale); a later request rebuilds it from the registry.
     */
    public function collectFromPlugins(PluginManager $plugins): void
    {
        $for = spl_object_id(app('request')).'|'.app()->getLocale();

        if ($this->collectedFor === $for) {
            return;
        }

        $this->collectedFor = $for;
        $this->items = collect();
        $this->userMenuItems = collect();

        // Copies, so the overlay and filters applied below never change the
        // registered items another request starts from.
        $this->push(...array_map(fn (MenuItem $item) => clone $item, $this->registered));

        // Register default sidebar menu items
        $this->push(
            (new MenuItem(__('tardis::menu.dashboard'), 'heroicon-o-home'))
                ->route('tardis.dashboard')
                ->section(__('tardis::menu.sections.overview'))
                ->order(0),
            (new MenuItem(__('tardis::menu.media'), 'heroicon-o-photo'))
                ->route('tardis.media.index')
                ->permission(Abilities::MEDIA_BROWSE)
                ->section(__('tardis::menu.sections.overview'))
                ->activeMode('prefix')
                ->order(10),
            (new MenuItem(__('tardis::menu.ui_components'), 'heroicon-o-squares-2x2'))
                ->route('tardis.ui-components')
                ->visibleWhen(fn (): bool => self::uiComponentsEnabled())
                ->section(__('tardis::menu.sections.overview'))
                ->order(20),
            (new MenuItem(__('tardis::menu.settings'), 'heroicon-o-cog-6-tooth'))
                ->route('tardis.settings.index')
                ->permission(Abilities::SETTINGS)
                ->section(__('tardis::menu.sections.management'))
                ->activeMode('prefix')
                ->order(30),
            (new MenuItem(__('tardis::menu.plugins'), 'heroicon-o-puzzle-piece'))
                ->route('tardis.plugins.index')
                ->permission(Abilities::PLUGINS)
                ->section(__('tardis::menu.sections.management'))
                ->order(40),
            (new MenuItem(__('tardis::menu.theme_editor'), 'heroicon-o-swatch'))
                ->route('tardis.themes.index')
                ->permission(Abilities::APPEARANCE)
                ->section(__('tardis::menu.sections.management'))
                ->order(43),
            (new MenuItem(__('tardis::menu.menu_builder'), 'heroicon-o-bars-3'))
                ->route('tardis.menus.index')
                ->permission(Abilities::MENUS)
                ->section(__('tardis::menu.sections.management'))
                ->order(42),
            (new MenuItem(__('tardis::menu.database_explorer'), 'heroicon-o-circle-stack'))
                ->route('tardis.database.index')
                ->permission(Abilities::DATABASE)
                ->section(__('tardis::menu.sections.management'))
                ->activeMode('prefix')
                ->order(45),
            (new MenuItem(__('tardis::menu.system'), 'heroicon-o-wrench-screwdriver'))
                ->route('tardis.system.index')
                ->permission(Abilities::SYSTEM)
                ->section(__('tardis::menu.sections.management'))
                ->order(47),
            // Siblings rather than children of the entry above: a parent with
            // visible children renders as a toggle without an href, which
            // strands the diagnostics link as soon as either sub-screen is
            // allowed. Siblings also let a user who holds only logs or only
            // commands reach the one screen they can actually open.
            (new MenuItem(__('tardis::menu.system_logs'), 'heroicon-o-document-text'))
                ->route('tardis.system.logs')
                ->permission(Abilities::LOGS)
                ->section(__('tardis::menu.sections.management'))
                ->order(48),
            (new MenuItem(__('tardis::menu.system_commands'), 'heroicon-o-command-line'))
                ->route('tardis.system.commands')
                ->permission(Abilities::COMMANDS)
                ->section(__('tardis::menu.sections.management'))
                ->order(49),
            (new MenuItem(__('tardis::menu.bread'), 'heroicon-o-table-cells'))
                ->route('tardis.builder.index')
                ->permission(Abilities::BREAD)
                ->section(__('tardis::menu.sections.management'))
                // The builder screens share the tardis.bread.* prefix with the
                // BREAD resource screens, so they are listed explicitly here
                // instead of relying on a prefix match.
                ->activeOnRoutes([
                    'tardis.builder.index',
                    'tardis.builder.create',
                    'tardis.builder.edit',
                ])
                ->order(50),
            MenuItem::makeDivider(),
            (new MenuItem(__('tardis::menu.permissions'), 'heroicon-o-lock-closed'))
                ->route('tardis.permissions.index')
                ->permission(Abilities::ROLES)
                ->section(__('tardis::menu.sections.access'))
                ->order(60),
            (new MenuItem(__('tardis::menu.users'), 'heroicon-o-users'))
                ->route('tardis.users.index')
                ->permission(Abilities::USERS)
                ->section(__('tardis::menu.sections.access'))
                ->order(55),
            (new MenuItem(__('tardis::menu.roles'), 'heroicon-o-user-group'))
                ->route('tardis.roles.index')
                ->permission(Abilities::ROLES)
                ->section(__('tardis::menu.sections.access'))
                ->order(70),
        );

        // Register a sidebar entry for every BREAD definition
        $this->push(...$this->breadMenuItems());

        // The panel's own profile page: name, email and password.
        $this->push(
            (new UserMenuItem(__('tardis::menu.profile'), 'heroicon-o-user'))
                ->route('tardis.profile')
                ->order(0),
        );

        $this->push(
            (new UserMenuItem(__('tardis::menu.logout'), 'heroicon-o-arrow-left-on-rectangle'))
                ->route('tardis.logout')
                ->method('POST')
                ->divider()
                ->order(100),
        );

        // Collect from plugins that provide menu items
        foreach ($plugins->enabledWith(MenuItems::class) as $instance) {
            $menuItems = $instance->provideMenuItems();
            foreach ($menuItems as $item) {
                $this->push($item);
            }
        }

        // Links the administrator added in the menu builder
        $this->push(...$this->customMenuItems());

        // Apply permission validation
        $this->validatePermissions($plugins);

        // Apply menu filters
        $this->applyFilters($plugins);

        // The administrator's hide/rename/re-order choices go last
        $this->applyOverlay();
    }

    /** @return array<int, MenuItem> */
    protected function customMenuItems(): array
    {
        return array_map(function (array $link) {
            $title = $link['title'] ?? '';

            $item = (new MenuItem(is_array($title) ? $title : (string) $title, $link['icon'] ?? 'heroicon-o-link'))
                ->url((string) $link['url'])
                ->section($link['section'] ?? null)
                ->order((int) ($link['order'] ?? 90));

            $item->key = (string) $link['id'];
            $item->newTab = (bool) ($link['new_tab'] ?? false);

            if (! empty($link['permission'])) {
                $item->permission((string) $link['permission']);
            }

            return $item;
        }, app(MenuOverlay::class)->custom());
    }

    protected function applyOverlay(): void
    {
        $changes = app(MenuOverlay::class)->items();

        if ($changes === []) {
            return;
        }

        $apply = function (MenuItem $item) use ($changes, &$apply): void {
            $entry = $item->isDivider ? null : ($changes[$item->id()] ?? null);

            if ($entry !== null) {
                $item->overlayHidden = ! empty($entry['hidden']);
                $item->title = $entry['title'] ?? $item->title;
                $item->order = $entry['order'] ?? $item->order;
                $item->section = $entry['section'] ?? $item->section;
            }

            $item->children->each($apply);
        };

        $this->items->each($apply);
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
            ->map(fn (BreadDefinition $bread) => (new MenuItem($bread->resolvedNamePlural(), $this->breadMenuIcon($bread->icon)))
                ->route('tardis.bread.index', ['slug' => $bread->slug])
                ->permission(BreadAuthorization::ability('browse', $bread->permissionKey()))
                ->section(__('tardis::menu.sections.bread'))
                ->activeOnRoutes([
                    'tardis.bread.index',
                    'tardis.bread.create',
                    'tardis.bread.show',
                    'tardis.bread.edit',
                ])
                ->order(100))
            ->values()
            ->all();
    }

    /**
     * BREAD definitions store a short icon name (e.g. "link"), but the menu
     * partial renders icons as Blade components, so the heroicon prefix has to
     * be added to keep them resolvable. Only resolvable components go through:
     * one bad definition must not make every panel page throw.
     */
    protected function breadMenuIcon(?string $icon): string
    {
        $default = 'heroicon-o-table-cells';
        $icon = trim((string) $icon);

        if (str_starts_with($icon, 'heroicon-') && preg_match('/^[a-z0-9-]+$/', $icon) === 1) {
            // Already a fully qualified heroicon component.
            $component = $icon;
        } elseif (preg_match('/^[a-z0-9-]+$/', $icon) === 1) {
            $component = 'heroicon-o-'.$icon;
        } elseif (preg_match('/^[a-z0-9-]+:[a-z0-9.-]+$/', $icon) === 1) {
            // Already a fully qualified Blade component alias.
            $component = $icon;
        } else {
            return $default;
        }

        return $this->componentExists($component) ? $component : $default;
    }

    /** Ask Blade/the icon factory whether this component can actually render. */
    private function componentExists(string $component): bool
    {
        try {
            if (array_key_exists($component, app('blade.compiler')->getClassComponentAliases())
                || view()->exists($component)) {
                return true;
            }

            // The icon aliases are registered when the icon factory resolves,
            // which may be later than menu collection. svg() checks the set
            // directly and throws when the icon does not exist.
            if (str_starts_with($component, 'heroicon-')) {
                app(Factory::class)->svg($component);

                return true;
            }
        } catch (\Throwable) {
            // An unknown component is a bad BREAD definition, not a 500.
        }

        return false;
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
    public function all(bool $withHidden = false): Collection
    {
        return $this->items
            ->filter(fn (MenuItem $item) => $item->isVisible() && ($withHidden || ! $item->overlayHidden))
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
