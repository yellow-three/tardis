<?php

namespace Tardis\Classes;

use Tardis\Auth\BreadAuthorization;

class Widget
{
    public string $component;

    public string $title;

    public int $width = 6;

    public ?string $icon = null;

    public ?string $permission = null;

    public array $parameters = [];

    public int $order = 50;

    /** Set when the dashboard layout hides this widget. */
    public bool $layoutHidden = false;

    /** Explicit stable id; see id(). */
    public ?string $key = null;

    public function __construct(string $component, string $title)
    {
        $this->component = $component;
        $this->title = $title;
    }

    /** Stable identity for the saved dashboard layout. */
    public function id(): string
    {
        return $this->key ?? $this->component;
    }

    public function key(string $key): self
    {
        $this->key = $key;

        return $this;
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
        // The same check every screen uses: with no authorization plugin deciding,
        // a widget is shown; with one, the user needs the ability.
        return $this->permission === null
            || app(BreadAuthorization::class)->allowsAbility($this->permission);
    }
}
