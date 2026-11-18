<?php

declare(strict_types=1);

use Tardis\Menu\SiteMenuStore;
use Tardis\Settings\SettingsManager;

// Front-end helpers, loaded by Composer (autoload.files). The short setting()
// and menu() names are in helpers-global.php, loaded by the service provider.

if (! function_exists('tardis_setting')) {
    /**
     * A panel setting by its "group.key", e.g. tardis_setting('site.title').
     */
    function tardis_setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsManager::class)->get($key, $default);
    }
}

if (! function_exists('tardis_menu')) {
    /**
     * The items of a site menu built in the panel, as a tree:
     * [['title' => ..., 'url' => ..., 'new_tab' => bool, 'children' => [...]], ...].
     * An unknown menu is an empty list.
     *
     * @return list<array<string, mixed>>
     */
    function tardis_menu(string $name): array
    {
        return app(SiteMenuStore::class)->items($name);
    }
}
