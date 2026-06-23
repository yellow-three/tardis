<?php

declare(strict_types=1);

namespace Tardis;

use Tardis\Manager\PluginManager;
use Tardis\Manager\MenuManager;
use Tardis\Manager\WidgetManager;
use Tardis\Manager\SettingsManager;
use Tardis\Manager\FormfieldManager;
use Tardis\Manager\BreadManager;

class Tardis
{
    protected PluginManager $pluginManager;
    protected MenuManager $menuManager;
    protected WidgetManager $widgetManager;
    protected SettingsManager $settingsManager;
    protected FormfieldManager $formfieldManager;
    protected BreadManager $breadManager;

    public function __construct(
        PluginManager $pluginManager,
        MenuManager $menuManager,
        WidgetManager $widgetManager,
        SettingsManager $settingsManager,
        FormfieldManager $formfieldManager,
        BreadManager $breadManager
    ) {
        $this->pluginManager = $pluginManager;
        $this->menuManager = $menuManager;
        $this->widgetManager = $widgetManager;
        $this->settingsManager = $settingsManager;
        $this->formfieldManager = $formfieldManager;
        $this->breadManager = $breadManager;
    }

    public function plugins(): PluginManager
    {
        return $this->pluginManager;
    }

    public function menu(): MenuManager
    {
        return $this->menuManager;
    }

    public function widgets(): WidgetManager
    {
        return $this->widgetManager;
    }

    public function settings(): SettingsManager
    {
        return $this->settingsManager;
    }

    public function formfields(): FormfieldManager
    {
        return $this->formfieldManager;
    }

    public function bread(): BreadManager
    {
        return $this->breadManager;
    }

    public static function version(): string
    {
        return '1.0.0';
    }
}
