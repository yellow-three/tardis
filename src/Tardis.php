<?php

declare(strict_types=1);

namespace Tardis;

use Tardis\Core\Manager\BreadManager;
use Tardis\Core\Manager\FormfieldManager;
use Tardis\Core\Manager\MenuManager;
use Tardis\Core\Manager\PluginManager;
use Tardis\Core\Manager\SettingsManager;
use Tardis\Core\Manager\WidgetManager;

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
        return $this->pluginManager ??= new PluginManager;
    }

    public function menu(): MenuManager
    {
        return $this->menuManager ??= new MenuManager;
    }

    public function widgets(): WidgetManager
    {
        return $this->widgetManager ??= new WidgetManager;
    }

    public function settings(): SettingsManager
    {
        return $this->settingsManager ??= new SettingsManager;
    }

    public function formfields(): FormfieldManager
    {
        return $this->formfieldManager ??= new FormfieldManager;
    }

    public function bread(): BreadManager
    {
        return $this->breadManager ??= new BreadManager;
    }

    public static function version(): string
    {
        return '1.0.0';
    }
}
