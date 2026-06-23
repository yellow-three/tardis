<?php

namespace Tardis\Core\Manager;

use Illuminate\Support\Collection;
use Tardis\Core\Classes\Widget;
use Tardis\Core\Contracts\Plugins\Features\Filter\FilterWidgets;
use Tardis\Core\Contracts\Plugins\Features\Provider\Widgets;

class WidgetManager
{
    protected Collection $widgets;

    public function __construct()
    {
        $this->widgets = collect();
    }

    public function addWidgets(Widget ...$widgets): void
    {
        foreach ($widgets as $widget) {
            $this->widgets->push($widget);
        }
    }

    public function collectFromPlugins(PluginManager $plugins): void
    {
        $this->widgets = collect();

        $this->addWidgets(
            (new Widget('widgets.stats-media', 'Media Files'))
                ->width(3)
                ->order(30),
            (new Widget('widgets.stats-plugins', 'Active Plugins'))
                ->width(3)
                ->order(40),
        );

        foreach ($plugins->enabled() as $name => $plugin) {
            $instance = $plugin['instance'];

            if ($instance instanceof Widgets) {
                $widgets = $instance->provideWidgets();
                foreach ($widgets as $widget) {
                    $this->widgets->push($widget);
                }
            }
        }

        $this->applyFilters($plugins);
    }

    protected function applyFilters(PluginManager $plugins): void
    {
        foreach ($plugins->enabled() as $name => $plugin) {
            $instance = $plugin['instance'];

            if ($instance instanceof FilterWidgets) {
                $this->widgets = $instance->filterWidgets($this->widgets);
            }
        }
    }

    public function all(): Collection
    {
        return $this->widgets
            ->filter(fn (Widget $widget) => $widget->isVisible())
            ->sortBy(fn (Widget $widget) => $widget->order)
            ->values();
    }
}
