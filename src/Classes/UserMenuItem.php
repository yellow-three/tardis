<?php

declare(strict_types=1);

namespace Tardis\Classes;

class UserMenuItem extends MenuItem
{
    /**
     * User menu items always appear in the user dropdown (not sidebar).
     * They are rendered in the admin header user menu.
     */
    public bool $inUserMenu = true;

    /**
     * Optional divider before this item.
     */
    public bool $divider = false;

    /**
     * HTTP method for the link (for logout forms etc.).
     */
    public ?string $method = null;

    public function divider(bool $divider = true): self
    {
        $this->divider = $divider;

        return $this;
    }

    public function method(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function toForm(): string
    {
        $method = $this->method ?? 'GET';

        if ($method === 'GET') {
            return '';
        }

        return '<form method="POST" action="'.e($this->href()).'" style="display:none;">'
            .csrf_field()
            .method_field($method)
            .'</form>';
    }
}
