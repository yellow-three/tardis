<?php

declare(strict_types=1);

namespace Tardis\Facades;

use Illuminate\Support\Facades\Facade;
use Tardis\TardisManager;

/**
 * @method static \Tardis\Plugins\PluginManager plugins()
 * @method static \Tardis\Menu\MenuManager menu()
 * @method static \Tardis\Dashboard\WidgetManager widgets()
 * @method static \Tardis\Settings\SettingsManager settings()
 * @method static \Tardis\Formfields\FormfieldManager formfields()
 * @method static \Tardis\Bread\BreadManager bread()
 * @method static \Tardis\Theme\ThemeManager themes()
 * @method static \Tardis\Assets\AssetManager assets()
 * @method static \Tardis\Bread\ActionManager actions()
 * @method static \Tardis\Media\MediaManager media()
 * @method static void addCss(\Tardis\Assets\Asset|string $asset)
 * @method static void addJs(\Tardis\Assets\Asset|string $asset)
 * @method static void addAction(string $slug, \Tardis\Bread\Action|string $action)
 * @method static void replaceAction(string $slug, string $name, \Tardis\Bread\Action|string $action)
 * @method static void manipulateActions(string $slug, \Closure $callback)
 * @method static string version()
 *
 * @see TardisManager
 */
class Tardis extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tardis';
    }
}
