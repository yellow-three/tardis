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
 * @method static \Tardis\Manager\BreadManager bread()
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
