<?php

declare(strict_types=1);

namespace Tardis;

use Illuminate\Support\Collection;
use Tardis\Assets\Asset;
use Tardis\Bread\Action;
use Tardis\Bread\BreadManager;
use Tardis\Manager\ActionManager;
use Tardis\Manager\AssetManager;
use Tardis\Manager\FormfieldManager;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;
use Tardis\Manager\SettingsManager;
use Tardis\Manager\WidgetManager;

class Tardis
{
    protected ?PluginManager $pluginManager = null;

    protected ?MenuManager $menuManager = null;

    protected ?WidgetManager $widgetManager = null;

    protected ?SettingsManager $settingsManager = null;

    protected ?FormfieldManager $formfieldManager = null;

    protected ?BreadManager $breadManager = null;

    public function plugins(): PluginManager
    {
        return $this->pluginManager ??= app(PluginManager::class);
    }

    public function menu(): MenuManager
    {
        return $this->menuManager ??= app(MenuManager::class);
    }

    public function widgets(): WidgetManager
    {
        return $this->widgetManager ??= app(WidgetManager::class);
    }

    public function settings(): SettingsManager
    {
        return $this->settingsManager ??= app(SettingsManager::class);
    }

    public function formfields(): FormfieldManager
    {
        return $this->formfieldManager ??= app(FormfieldManager::class);
    }

    public function bread(): BreadManager
    {
        return $this->breadManager ??= app(BreadManager::class);
    }

    /**
     * Load a stylesheet (URL string or Asset) on every admin page.
     */
    public function addCss(Asset|string $asset): void
    {
        app(AssetManager::class)->addCss($asset);
    }

    /**
     * Load a script (URL string or Asset) on every admin page.
     */
    public function addJs(Asset|string $asset): void
    {
        app(AssetManager::class)->addJs($asset);
    }

    /**
     * Offer an action on a BREAD listing ('*' = every BREAD).
     */
    public function addAction(string $slug, Action|string $action): void
    {
        app(ActionManager::class)->add($slug, $action);
    }

    public function replaceAction(string $slug, string $name, Action|string $action): void
    {
        app(ActionManager::class)->replace($slug, $name, $action);
    }

    /**
     * @param  \Closure(Collection): Collection  $callback
     */
    public function manipulateActions(string $slug, \Closure $callback): void
    {
        app(ActionManager::class)->manipulate($slug, $callback);
    }

    public static function version(): string
    {
        return '0.1.0';
    }
}
