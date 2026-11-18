<?php

declare(strict_types=1);

namespace Tardis;

use Composer\InstalledVersions;
use Illuminate\Support\Collection;
use Tardis\Assets\Asset;
use Tardis\Bread\Action;
use Tardis\Bread\BreadManager;
use Tardis\Manager\ActionManager;
use Tardis\Manager\AssetManager;
use Tardis\Manager\FormfieldManager;
use Tardis\Manager\MediaManager;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;
use Tardis\Manager\SettingsManager;
use Tardis\Manager\ThemeManager;
use Tardis\Manager\WidgetManager;

/**
 * The object behind the Tardis facade: one way in to every manager.
 *
 * Each accessor asks the container every time instead of keeping its own
 * copy, so a binding that changes (a scoped service, a test double) is what
 * the facade returns.
 */
class Tardis
{
    /** Used when Composer cannot tell (a path repository, a dev checkout). */
    public const VERSION = '0.1.0';

    public function plugins(): PluginManager
    {
        return app(PluginManager::class);
    }

    public function menu(): MenuManager
    {
        return app(MenuManager::class);
    }

    public function widgets(): WidgetManager
    {
        return app(WidgetManager::class);
    }

    public function settings(): SettingsManager
    {
        return app(SettingsManager::class);
    }

    public function formfields(): FormfieldManager
    {
        return app(FormfieldManager::class);
    }

    public function bread(): BreadManager
    {
        return app(BreadManager::class);
    }

    public function themes(): ThemeManager
    {
        return app(ThemeManager::class);
    }

    public function assets(): AssetManager
    {
        return app(AssetManager::class);
    }

    public function actions(): ActionManager
    {
        return app(ActionManager::class);
    }

    public function media(): MediaManager
    {
        return app(MediaManager::class);
    }

    /**
     * Load a stylesheet (URL string or Asset) on every admin page.
     */
    public function addCss(Asset|string $asset): void
    {
        $this->assets()->addCss($asset);
    }

    /**
     * Load a script (URL string or Asset) on every admin page.
     */
    public function addJs(Asset|string $asset): void
    {
        $this->assets()->addJs($asset);
    }

    /**
     * Offer an action on a BREAD listing ('*' = every BREAD).
     */
    public function addAction(string $slug, Action|string $action): void
    {
        $this->actions()->add($slug, $action);
    }

    public function replaceAction(string $slug, string $name, Action|string $action): void
    {
        $this->actions()->replace($slug, $name, $action);
    }

    /**
     * @param  \Closure(Collection): Collection  $callback
     */
    public function manipulateActions(string $slug, \Closure $callback): void
    {
        $this->actions()->manipulate($slug, $callback);
    }

    /**
     * The installed package version: Composer's, when it is a tagged release,
     * otherwise VERSION.
     */
    public static function version(): string
    {
        try {
            $installed = InstalledVersions::isInstalled('yellow-three/tardis')
                ? (string) InstalledVersions::getPrettyVersion('yellow-three/tardis')
                : '';
        } catch (\Throwable) {
            $installed = '';
        }

        return preg_match('/^v?(\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?)$/', $installed, $m) === 1 ? $m[1] : self::VERSION;
    }
}
