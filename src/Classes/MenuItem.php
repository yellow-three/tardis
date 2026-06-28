<?php

declare(strict_types=1);

namespace Tardis\Classes;

use Illuminate\Support\Collection;
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

    public function __construct(string $title, ?string $icon = null)
    {
        $this->title = $title;
        $this->icon = $icon;
        $this->children = new Collection;
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
            if ($this->activeMode === 'prefix') {
                return request()->routeIs($this->routeName.'*');
            }

            return request()->routeIs($this->routeName);
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
                return $auth->authorize($this->permission, ...$this->permissionArguments);
            }

            return true;
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

    protected function resolveAuthorization(?PluginManager $plugins = null): mixed
    {
        $plugins ??= app(PluginManager::class);

        return $plugins->enabledWith(
            \Tardis\Contracts\Plugins\AuthorizationPlugin::class
        )->first();
    }
}
