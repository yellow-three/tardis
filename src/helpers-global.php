<?php

declare(strict_types=1);

// The short helper names, loaded by TardisServiceProvider::register(). Composer
// loads a package's autoload files before the application's own, so declaring
// these there would break a host that defines setting() or menu() itself
// without a function_exists() guard. By the time providers register, the
// host's files are loaded and an existing function wins.

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return tardis_setting($key, $default);
    }
}

if (! function_exists('menu')) {
    /**
     * @return list<array<string, mixed>>
     */
    function menu(string $name): array
    {
        return tardis_menu($name);
    }
}
