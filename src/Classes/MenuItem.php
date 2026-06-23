<?php

namespace Tardis\Core\Classes;

class MenuItem
{
    public string $title;

    public ?string $icon;

    public ?string $routeName = null;

    public array $routeParams = [];

    public ?string $url = null;

    public ?string $permission = null;

    public ?string $badgeColor = null;

    public ?string $badgeValue = null;

    public int $order = 50;

    public ?string $group = null;

    /** @var MenuItem[] */
    public array $children = [];

    public function __construct(string $title, ?string $icon = null)
    {
        $this->title = $title;
        $this->icon = $icon;
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

    public function permission(string $permission): self
    {
        $this->permission = $permission;

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

    public function group(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function addChildren(MenuItem ...$children): self
    {
        $this->children = array_merge($this->children, $children);

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
            return request()->routeIs($this->routeName);
        }

        if ($this->url && $this->url !== '#') {
            return request()->is(ltrim($this->url, '/'));
        }

        return false;
    }

    public function isVisible(): bool
    {
        if ($this->permission) {
            return auth()->user()?->can($this->permission) ?? false;
        }

        return true;
    }
}
