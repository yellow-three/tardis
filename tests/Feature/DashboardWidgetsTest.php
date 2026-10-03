<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tardis\Classes\Widget;
use Tardis\Contracts\Plugins\Features\Provider\Widgets;
use Tardis\Contracts\Plugins\GenericPlugin;
use Tardis\Dashboard\DashboardLayout;
use Tardis\Manager\PluginManager;
use Tardis\Manager\WidgetManager;

function dashboardWidgets(bool $withHidden = false): Collection
{
    $manager = new WidgetManager;
    $manager->collectFromPlugins(app(PluginManager::class));

    return $manager->all($withHidden);
}

test('the default dashboard has the stock widgets in order', function () {
    expect(dashboardWidgets()->map->id()->all())->toBe(['users', 'breads', 'media', 'activities', 'recent-activity']);
});

test('a hidden widget leaves the dashboard but can still be listed for editing', function () {
    app(DashboardLayout::class)->change('media', ['hidden' => true]);

    expect(dashboardWidgets()->map->id()->all())->not->toContain('media')
        ->and(dashboardWidgets(withHidden: true)->map->id()->all())->toContain('media');

    app(DashboardLayout::class)->change('media', ['hidden' => false]);
    expect(app(DashboardLayout::class)->isEmpty())->toBeTrue();
});

test('order and width come from the saved layout and only known widths are accepted', function () {
    $layout = app(DashboardLayout::class);
    $layout->change('recent-activity', ['order' => 1, 'width' => 6]);
    $layout->change('users', ['width' => 5]);

    $widgets = dashboardWidgets();

    expect($widgets->first()->id())->toBe('recent-activity')
        ->and($widgets->first()->width)->toBe(6)
        ->and($widgets->firstWhere(fn (Widget $w) => $w->id() === 'users')->width)->toBe(3);
});

test('a plugin widget is added to the dashboard', function () {
    $plugins = app(PluginManager::class);
    $plugins->register('widgets', DashboardWidgetPlugin::class);
    $plugins->enable('widgets');

    expect(dashboardWidgets()->map->id()->all())->toContain('hello');
});

class DashboardWidgetPlugin implements GenericPlugin, Widgets
{
    public function name(): string
    {
        return 'w';
    }

    public function description(): string
    {
        return 'w';
    }

    public function provideWidgets(): array
    {
        return [(new Widget('tardis::widgets.users', 'Hello'))->key('hello')];
    }
}

test('the dashboard renders its widgets', function () {
    Livewire::test('tardis::pages.dashboard')
        ->assertSee(__('tardis::dashboard.users'))
        ->assertSee(__('tardis::dashboard.recent_activity'))
        ->assertSee(__('tardis::dashboard.customize'));
});

test('edit mode hides, moves and resizes widgets and restores the defaults', function () {
    $page = Livewire::test('tardis::pages.dashboard')
        ->call('toggleEditing')
        ->assertSet('editing', true)
        ->call('toggleWidget', 'media')
        ->call('resize', 'users', 6)
        ->call('move', 'breads', -1);

    expect(dashboardWidgets()->map->id()->all())->toBe(['breads', 'users', 'activities', 'recent-activity'])
        ->and(dashboardWidgets()->firstWhere(fn (Widget $w) => $w->id() === 'users')->width)->toBe(6);

    $page->call('resetLayout');
    expect(app(DashboardLayout::class)->isEmpty())->toBeTrue();
});
