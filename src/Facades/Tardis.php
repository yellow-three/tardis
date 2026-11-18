<?php

declare(strict_types=1);

namespace Tardis\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Tardis\Manager\PluginManager plugins()
 * @method static \Tardis\Manager\MenuManager menu()
 * @method static \Tardis\Manager\WidgetManager widgets()
 * @method static \Tardis\Manager\SettingsManager settings()
 * @method static \Tardis\Manager\FormfieldManager formfields()
 * @method static \Tardis\Bread\BreadManager bread()
 * @method static \Tardis\Manager\ThemeManager themes()
 * @method static \Tardis\Manager\AssetManager assets()
 * @method static \Tardis\Manager\ActionManager actions()
 * @method static \Tardis\Manager\MediaManager media()
 * @method static void addCss(\Tardis\Assets\Asset|string $asset)
 * @method static void addJs(\Tardis\Assets\Asset|string $asset)
 * @method static void addAction(string $slug, \Tardis\Bread\Action|string $action)
 * @method static void replaceAction(string $slug, string $name, \Tardis\Bread\Action|string $action)
 * @method static void manipulateActions(string $slug, \Closure $callback)
 * @method static string version()
 *
 * @see \Tardis\Tardis
 */
class Tardis extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tardis';
    }
}
