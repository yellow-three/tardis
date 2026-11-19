<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Tardis\Contracts\Plugins\GenericPlugin;
use Tardis\Dashboard\DashboardLayout;
use Tardis\Menu\MenuItem;
use Tardis\Menu\MenuManager;
use Tardis\Menu\MenuOverlay;
use Tardis\Plugins\PluginManager;
use Tardis\Settings\SettingsManager;
use Tardis\Theme\ThemeManager;

// Two instances on the same file stand for two workers of one deployment
// (Octane, queue workers): what one writes, the other must see, and two
// writes at once must both land.

class LongRunningPlugin implements GenericPlugin
{
    public function name(): string
    {
        return 'long-running';
    }

    public function description(): string
    {
        return 'Stands in for any plugin.';
    }
}

test('a setting changed by another worker is read, not the cached value', function () {
    $mine = new SettingsManager;
    $other = new SettingsManager;

    expect($mine->get('admin.title'))->toBe('Tardis Admin');

    $other->set('admin.title', 'Changed elsewhere');

    expect($mine->get('admin.title'))->toBe('Changed elsewhere');
});

test('two workers saving different settings both keep their change', function () {
    $first = new SettingsManager;
    $second = new SettingsManager;
    $first->load();
    $second->load();

    $first->set('admin.title', 'From the first');
    $second->set('admin.sidebar-description', 'From the second');

    $fresh = new SettingsManager;

    expect($fresh->get('admin.title'))->toBe('From the first')
        ->and($fresh->get('admin.sidebar-description'))->toBe('From the second');
});

test('a theme saved by another worker shows up in the cached list', function () {
    $mine = new ThemeManager;
    expect($mine->has('ocean'))->toBeFalse();

    (new ThemeManager)->saveCustom(['name' => 'ocean', 'scheme' => 'dark', 'colors' => ['primary' => '#336699', 'base-100' => '#101820', 'base-content' => '#eeeeee']]);

    expect($mine->has('ocean'))->toBeTrue();
});

test('a plugin switched off and on in another worker follows here', function () {
    $path = storage_path('tardis/plugins.json');
    $mine = new PluginManager($path);
    $mine->register('long-running', LongRunningPlugin::class);
    $mine->enableByDefault('long-running');

    $other = new PluginManager($path);
    $other->register('long-running', LongRunningPlugin::class);
    $other->enableByDefault('long-running');

    $other->disable('long-running');
    expect($mine->isEnabled('long-running'))->toBeFalse();

    $other->enable('long-running');
    expect($mine->isEnabled('long-running'))->toBeTrue();
});

test('menu and dashboard changes made at the same time both land', function () {
    (new MenuOverlay)->change('tardis.dashboard', ['hidden' => true]);
    (new MenuOverlay)->change('tardis.media.index', ['title' => 'Files']);
    (new DashboardLayout)->change('a', ['order' => 1]);
    (new DashboardLayout)->change('b', ['width' => 6]);

    expect((new MenuOverlay)->items())->toHaveKeys(['tardis.dashboard', 'tardis.media.index'])
        ->and((new DashboardLayout)->all())->toBe(['a' => ['order' => 1], 'b' => ['width' => 6]]);
});

test('the menu is rebuilt for each request, in its locale, from the registered items', function () {
    $menu = app(MenuManager::class);
    $menu->addItems(new MenuItem('Host link', 'heroicon-o-link'));

    app()->setLocale('en');
    $menu->collectFromPlugins(app(PluginManager::class));
    expect($menu->all(withHidden: true)->pluck('title')->all())->toContain('Host link', __('tardis::menu.dashboard', [], 'en'));

    // The next request of the same worker, in Turkish.
    app()->instance('request', Request::create('/admin'));
    app()->setLocale('tr');
    $menu->collectFromPlugins(app(PluginManager::class));

    expect($menu->all(withHidden: true)->pluck('title')->all())->toContain('Host link', __('tardis::menu.dashboard', [], 'tr'))
        ->and($menu->all(withHidden: true)->pluck('title')->filter(fn ($title) => $title === 'Host link'))->toHaveCount(1);
});
