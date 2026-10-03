<?php

declare(strict_types=1);

namespace Tardis\Classes;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Manager\PluginManager;

class MenuItem
{
    public string $title;

    public ?string $icon = null;

    public ?string $routeName = null;

    public array $routeParams = [];

    public ?string $url = null;

    /**
     * Permission ability required to see this menu item.
     * Uses the registered AuthorizationPlugin for checking.
     */
    public ?string $permission = null;

    /** @var array<int, mixed> Permission arguments passed to authorize() */
    public array $permissionArguments = [];

    public ?string $badgeColor = null;

    public ?string $badgeValue = null;

    public int $order = 50;

    /** @var Collection<int, MenuItem> */
    public Collection $children;

    /**
     * Active matching mode.
     * 'exact' — matches the exact route name.
     * 'prefix' — matches if the current route starts with this route name.
     */
    public string $activeMode = 'exact';

    /**
     * Explicit route names that mark this item active.
     *
     * Takes precedence over activeMode when set. Needed when a route name and
     * its prefix are shared by unrelated screens: every BREAD resource uses
     * `tardis.bread.index` with its own `{slug}`, and the BREAD builder pages
     * sit under the same `tardis.bread.*` prefix while carrying the same
     * `{slug}` — so no prefix heuristic can tell a resource screen apart from
     * the builder screen for the definition of the same name.
     *
     * @var array<int, string>
     */
    public array $activeRouteNames = [];

    public bool $isDivider = false;

    public ?string $section = null;

    /** Set when the administrator's menu overlay hides this item. */
    public bool $overlayHidden = false;

    /** The item's own opening in a new tab (custom links only). */
    public bool $newTab = false;

    /** Explicit stable id; see id(). */
    public ?string $key = null;

    public function __construct(string $title, ?string $icon = null)
    {
        $this->title = $title;
        $this->icon = $icon;
        $this->children = new Collection;
    }

    /**
     * A stable identity for the menu overlay: the explicit key, else the route
     * name with its parameters (every BREAD shares a route and differs by slug),
     * else the url, else the title.
     */
    public function id(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        if ($this->routeName !== null) {
            return $this->routeName.($this->routeParams === [] ? '' : ':'.implode(',', array_map('strval', $this->routeParams)));
        }

        return Str::slug($this->url ?? $this->title);
    }

    public static function makeDivider(): self
    {
        $item = new self('');
        $item->isDivider = true;

        return $item;
    }

    public function route(string $route, array $params = []): self
    {
        $this->routeName = $route;
        $this->routeParams = $params;

        return $this;
    }

    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Set permission required to see this menu item.
     * Arguments are passed to the AuthorizationPlugin's authorize().
     */
    public function permission(string $ability, mixed ...$arguments): self
    {
        $this->permission = $ability;
        $this->permissionArguments = $arguments;

        return $this;
    }

    public function badge(string $color, ?string $value = null): self
    {
        $this->badgeColor = $color;
        $this->badgeValue = $value;

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Set the active matching mode.
     */
    public function activeMode(string $mode): self
    {
        $this->activeMode = $mode;

        return $this;
    }

    /**
     * Set the exact route names that mark this item active.
     *
     * Use this instead of activeMode when a prefix match would also catch
     * unrelated routes.
     *
     * @param  array<int, string>  $routeNames
     */
    public function activeOnRoutes(array $routeNames): self
    {
        $this->activeRouteNames = $routeNames;

        return $this;
    }

    /**
     * Set the menu section/group label.
     * Sections are rendered as menu-title headers in grouped views.
     */
    public function section(?string $section): self
    {
        $this->section = $section;

        return $this;
    }

    /**
     * Add child menu items.
     */
    public function addChildren(MenuItem ...$children): self
    {
        foreach ($children as $child) {
            $this->children->push($child);
        }

        return $this;
    }

    /**
     * Recursively validate permissions for this item and its children.
     * Removes children the user cannot see.
     */
    public function validatePermissions(?PluginManager $plugins = null): self
    {
        $auth = $this->resolveAuthorization($plugins);

        // Validate children first (recursive)
        $this->children = $this->children
            ->filter(fn (MenuItem $child) => $child->isVisible())
            ->map(fn (MenuItem $child) => $child->validatePermissions($plugins))
            ->values();

        return $this;
    }

    public function href(): string
    {
        if ($this->routeName) {
            return route($this->routeName, $this->routeParams);
        }

        return $this->url ?? '#';
    }

    public function isActive(): bool
    {
        if ($this->routeName) {
            if ($this->activeRouteNames !== []) {
                return request()->routeIs($this->activeRouteNames) && $this->routeParamsMatch();
            }

            if ($this->activeMode === 'prefix') {
                $prefixRoute = $this->getParentRoute();

                if ($prefixRoute && request()->routeIs($prefixRoute.'.*') && $this->routeParamsMatch()) {
                    return true;
                }

                return request()->routeIs($this->routeName.'*') && $this->routeParamsMatch();
            }

            return request()->routeIs($this->routeName) && $this->routeParamsMatch();
        }

        if ($this->url && $this->url !== '#') {
            return request()->is(ltrim($this->url, '/'));
        }

        // Check if any child is active
        foreach ($this->children as $child) {
            if ($child->isActive()) {
                return true;
            }
        }

        return false;
    }

    public function isVisible(): bool
    {
        if ($this->permission !== null) {
            $auth = $this->resolveAuthorization();

            if ($auth) {
                return $auth->can($this->permission, ...$this->permissionArguments);
            }

            return true;
        }

        return true;
    }

    public function hasPermission(string $ability, mixed ...$arguments): bool
    {
        $auth = $this->resolveAuthorization();

        if ($auth) {
            return $auth->can($ability, ...$arguments);
        }

        return true;
    }

    /**
     * Get the parent route name for active state matching.
     */
    public function getParentRoute(): ?string
    {
        if ($this->routeName === null) {
            return null;
        }

        // Return the route prefix (e.g. 'tardis.settings' from 'tardis.settings.index')
        $parts = explode('.', $this->routeName);

        if (count($parts) > 2) {
            array_pop($parts);

            return implode('.', $parts);
        }

        return $this->routeName;
    }

    /**
     * Check whether the current request's route parameters match this item's
     * route parameters.
     *
     * Menu items can share a route name and differ only by a parameter — every
     * BREAD resource uses `tardis.bread.index` with its own `{slug}` — so the
     * route name on its own cannot tell them apart.
     */
    protected function routeParamsMatch(): bool
    {
        if ($this->routeParams === []) {
            return true;
        }

        $route = request()->route();

        if ($route === null) {
            return false;
        }

        foreach ($this->routeParams as $key => $value) {
            if ((string) $route->parameter($key) !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    protected function resolveAuthorization(?PluginManager $plugins = null): mixed
    {
        $plugins ??= app(PluginManager::class);

        return $plugins->enabledWith(
            AuthorizationPlugin::class
        )->first();
    }
}
