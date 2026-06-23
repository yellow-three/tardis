<?php

namespace Tardis\Core\Classes;

class Widget
{
    public string $component;

    public string $title;

    public int $width = 6;

    public ?string $icon = null;

    public ?string $permission = null;

    public array $parameters = [];

    public int $order = 50;

    public function __construct(string $component, string $title)
    {
        $this->component = $component;
        $this->title = $title;
    }

    public function width(int $width): self
    {
        $this->width = max(3, min(12, $width));

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function permission(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function parameters(array $params): self
    {
        $this->parameters = $params;

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function isVisible(): bool
    {
        if ($this->permission) {
            return auth()->user()?->can($this->permission) ?? false;
        }

        return true;
    }
}
