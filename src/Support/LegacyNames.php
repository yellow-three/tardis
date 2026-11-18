<?php

declare(strict_types=1);

namespace Tardis\Support;

use Tardis\Assets\AssetManager;
use Tardis\Auth\RoleAuthorizationPlugin;
use Tardis\Auth\SessionAuthenticationPlugin;
use Tardis\Bread\ActionManager;
use Tardis\Commands\AdminCommand;
use Tardis\Commands\BreadExportCommand;
use Tardis\Commands\BreadImportLegacyCommand;
use Tardis\Commands\DoctorCommand;
use Tardis\Commands\InstallCommand;
use Tardis\Commands\MakeBreadCommand;
use Tardis\Commands\MakeModelCommand;
use Tardis\Commands\MakePluginCommand;
use Tardis\Commands\PluginsCommand;
use Tardis\Dashboard\Widget;
use Tardis\Dashboard\WidgetManager;
use Tardis\Formfields\DynamicInput;
use Tardis\Formfields\FormfieldManager;
use Tardis\Media\MediaManager;
use Tardis\Menu\MenuItem;
use Tardis\Menu\MenuManager;
use Tardis\Menu\UserMenuItem;
use Tardis\Plugins\PluginManager;
use Tardis\Settings\Setting;
use Tardis\Settings\SettingsManager;
use Tardis\TardisManager;
use Tardis\Theme\ThemeManager;

/**
 * Class names from before the phase 10c renaming, kept working for one minor
 * release. An old name is bound to its new class only when something asks for
 * it, and asking logs a deprecation; nothing is loaded up front.
 *
 * Remove in 0.3 together with src/legacy-aliases.php.
 */
final class LegacyNames
{
    /** Old class name => new class name. */
    public const MAP = [
        'Tardis\\Auth\\TardisAuthorizationPlugin' => RoleAuthorizationPlugin::class,
        'Tardis\\Classes\\DynamicInput' => DynamicInput::class,
        'Tardis\\Classes\\MenuItem' => MenuItem::class,
        'Tardis\\Classes\\Setting' => Setting::class,
        'Tardis\\Classes\\Translation' => Translation::class,
        'Tardis\\Classes\\UserMenuItem' => UserMenuItem::class,
        'Tardis\\Classes\\Widget' => Widget::class,
        'Tardis\\Commands\\TardisAdminCommand' => AdminCommand::class,
        'Tardis\\Commands\\TardisBreadExportCommand' => BreadExportCommand::class,
        'Tardis\\Commands\\TardisBreadMigrateCommand' => BreadImportLegacyCommand::class,
        'Tardis\\Commands\\TardisDoctorCommand' => DoctorCommand::class,
        'Tardis\\Commands\\TardisInstallCommand' => InstallCommand::class,
        'Tardis\\Commands\\TardisMakeBreadCommand' => MakeBreadCommand::class,
        'Tardis\\Commands\\TardisMakeModelCommand' => MakeModelCommand::class,
        'Tardis\\Commands\\TardisMakePluginCommand' => MakePluginCommand::class,
        'Tardis\\Commands\\TardisPluginsCommand' => PluginsCommand::class,
        'Tardis\\Manager\\ActionManager' => ActionManager::class,
        'Tardis\\Manager\\AssetManager' => AssetManager::class,
        'Tardis\\Manager\\FormfieldManager' => FormfieldManager::class,
        'Tardis\\Manager\\MediaManager' => MediaManager::class,
        'Tardis\\Manager\\MenuManager' => MenuManager::class,
        'Tardis\\Manager\\PluginManager' => PluginManager::class,
        'Tardis\\Manager\\SettingsManager' => SettingsManager::class,
        'Tardis\\Manager\\ThemeManager' => ThemeManager::class,
        'Tardis\\Manager\\WidgetManager' => WidgetManager::class,
        'Tardis\\Plugins\\AuthenticationPlugin' => SessionAuthenticationPlugin::class,
        'Tardis\\Tardis' => TardisManager::class,
    ];

    public static function autoload(string $class): void
    {
        $target = self::MAP[$class] ?? null;

        if ($target === null) {
            return;
        }

        @trigger_error(sprintf('%s is deprecated, use %s instead (removed in Tardis 0.3).', $class, $target), E_USER_DEPRECATED);

        class_alias($target, $class);
    }
}
