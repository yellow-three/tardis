<?php

namespace Tardis\Manager;

use Illuminate\Support\Collection;
use Tardis\Auth\Abilities;
use Tardis\Classes\Widget;
use Tardis\Contracts\Plugins\Features\Filter\FilterWidgets;
use Tardis\Contracts\Plugins\Features\Provider\Widgets;
use Tardis\Dashboard\DashboardLayout;

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
            (new Widget('tardis::widgets.users', __('tardis::dashboard.users')))->key('users')->permission(Abilities::USERS)->width(3)->order(10),
            (new Widget('tardis::widgets.breads', __('tardis::dashboard.breads')))->key('breads')->permission(Abilities::BREAD)->width(3)->order(20),
            (new Widget('tardis::widgets.media', __('tardis::dashboard.media_files')))->key('media')->permission(Abilities::MEDIA_BROWSE)->width(3)->order(30),
            (new Widget('tardis::widgets.activities', __('tardis::dashboard.activities')))->key('activities')->permission(Abilities::ACTIVITY)->width(3)->order(40),
            (new Widget('tardis::widgets.recent-activity', __('tardis::dashboard.recent_activity')))->key('recent-activity')->permission(Abilities::ACTIVITY)->width(12)->order(50),
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
        $this->applyLayout();
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

    /** The administrator's hide/order/width choices go on top of what code provides. */
    protected function applyLayout(): void
    {
        $layout = app(DashboardLayout::class)->all();

        foreach ($this->widgets as $widget) {
            $entry = $layout[$widget->id()] ?? null;

            if ($entry === null) {
                continue;
            }

            $widget->layoutHidden = ! empty($entry['hidden']);
            $widget->order = $entry['order'] ?? $widget->order;
            $widget->width = $entry['width'] ?? $widget->width;
        }
    }

    public function all(bool $withHidden = false): Collection
    {
        return $this->widgets
            ->filter(fn (Widget $widget) => $widget->isVisible() && ($withHidden || ! $widget->layoutHidden))
            ->sortBy(fn (Widget $widget) => $widget->order)
            ->values();
    }
}
