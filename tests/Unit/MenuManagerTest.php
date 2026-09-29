<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Classes\MenuItem;
use Tardis\Classes\UserMenuItem;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;

test('menu manager can be instantiated', function () {
    $manager = new MenuManager;

    expect($manager)->toBeInstanceOf(MenuManager::class);
});

test('menu manager all starts empty', function () {
    $manager = new MenuManager;

    expect($manager->all())->toHaveCount(0);
});

test('menu manager addItems adds items', function () {
    $manager = new MenuManager;
    $item1 = new MenuItem('Dashboard');
    $item2 = new MenuItem('Settings');

    $manager->addItems($item1, $item2);

    expect($manager->all())->toHaveCount(2);
});

test('menu manager all returns sorted by order', function () {
    $manager = new MenuManager;
    $item1 = (new MenuItem('Settings'))->order(10);
    $item2 = (new MenuItem('Dashboard'))->order(1);

    $manager->addItems($item1, $item2);

    $items = $manager->all();
    expect($items->first()->title)->toBe('Dashboard')
        ->and($items->last()->title)->toBe('Settings');
});

test('menu manager tree returns all items', function () {
    $manager = new MenuManager;
    $item1 = new MenuItem('Users');
    $item2 = new MenuItem('Posts');

    $manager->addItems($item1, $item2);

    $tree = $manager->tree();
    expect($tree)->toHaveCount(2);
});

test('menu manager userMenu returns user menu items', function () {
    $manager = new MenuManager;
    $userItem = (new UserMenuItem('Profile'))->route('profile.edit');
    $sidebarItem = new MenuItem('Dashboard');

    $manager->addItems($userItem, $sidebarItem);

    $userMenu = $manager->userMenu();
    expect($userMenu)->toHaveCount(1)
        ->and($userMenu->first()->title)->toBe('Profile');
});

// The global beforeEach in Pest.php does not reach this file, so the temp
// store is set up here. MenuManager resolves BreadManager from the container,
// so the source has to be bound to the same path the definitions are saved to.
beforeEach(function () {
    $this->menuBreadPath = sys_get_temp_dir().'/tardis-bread-menu-'.uniqid();

    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->menuBreadPath));
});

afterEach(function () {
    File::deleteDirectory($this->menuBreadPath);
});

function saveBread(string $path, array $attributes): void
{
    (new JsonBreadSource($path))->save($attributes + [
        'model' => 'App\\Models\\Post',
        'name' => 'Post',
        'fields' => [],
    ]);
}

function matchBreadRoute(string $uri): void
{
    $request = Request::create($uri, 'GET');
    $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));
    app()->instance('request', $request);
}

function collectedMenu(): MenuManager
{
    $manager = new MenuManager;
    $manager->collectFromPlugins(new PluginManager);

    return $manager;
}

test('bread definitions appear in the menu linking to their list page', function () {
    saveBread($this->menuBreadPath, ['slug' => 'posts', 'name_plural' => 'Posts']);

    $item = collectedMenu()->all()->firstWhere('title', 'Posts');

    expect($item)->not->toBeNull()
        ->and($item->routeName)->toBe('tardis.bread.index')
        ->and($item->routeParams)->toBe(['slug' => 'posts'])
        ->and($item->href())->toEndWith('/admin/posts')
        ->and($item->section)->toBe('BREAD');
});

test('bread menu icons are prefixed so the menu partial can render them', function () {
    saveBread($this->menuBreadPath, ['slug' => 'posts', 'name_plural' => 'Posts', 'icon' => 'link']);
    saveBread($this->menuBreadPath, ['slug' => 'users', 'name_plural' => 'Users', 'icon' => 'user-group']);
    saveBread($this->menuBreadPath, ['slug' => 'pages', 'name_plural' => 'Pages', 'icon' => 'heroicon-o-document']);
    saveBread($this->menuBreadPath, ['slug' => 'notes', 'name_plural' => 'Notes']);

    $items = collectedMenu()->all();

    expect($items->firstWhere('title', 'Posts')->icon)->toBe('heroicon-o-link')
        ->and($items->firstWhere('title', 'Users')->icon)->toBe('heroicon-o-user-group')
        // Already-qualified components are left untouched.
        ->and($items->firstWhere('title', 'Pages')->icon)->toBe('heroicon-o-document')
        // Definitions without an icon fall back rather than rendering nothing.
        ->and($items->firstWhere('title', 'Notes')->icon)->toBe('heroicon-o-table-cells');
});

test('only the bread menu item matching the current slug is active', function () {
    saveBread($this->menuBreadPath, ['slug' => 'posts', 'name_plural' => 'Posts']);
    saveBread($this->menuBreadPath, ['slug' => 'users', 'name_plural' => 'Users']);

    $items = collectedMenu()->all();
    matchBreadRoute('/admin/posts');

    // Every BREAD shares tardis.bread.index, so without parameter matching both
    // items would light up on the same page.
    expect($items->firstWhere('title', 'Posts')->isActive())->toBeTrue()
        ->and($items->firstWhere('title', 'Users')->isActive())->toBeFalse();
});

test('a bread menu item stays active on its own create, read and edit routes', function () {
    saveBread($this->menuBreadPath, ['slug' => 'posts', 'name_plural' => 'Posts']);
    $item = collectedMenu()->all()->firstWhere('title', 'Posts');

    matchBreadRoute('/admin/posts/create');
    expect($item->isActive())->toBeTrue();

    matchBreadRoute('/admin/posts/1');
    expect($item->isActive())->toBeTrue();

    matchBreadRoute('/admin/posts/1/edit');
    expect($item->isActive())->toBeTrue();
});

test('the BREAD manager entry is not highlighted while a BREAD resource is open', function () {
    matchBreadRoute('/admin/posts');

    // tardis.bread.* also matches tardis.bread.index, so a prefix match here
    // would highlight the manager and the resource at the same time.
    expect(collectedMenu()->all()->firstWhere('title', 'BREAD')->isActive())->toBeFalse();
});

test('menu items without route parameters keep matching on the route name alone', function () {
    $item = (new MenuItem('Media'))->route('tardis.media')->activeMode('prefix');

    matchBreadRoute('/admin/bread/create');
    expect($item->isActive())->toBeFalse();

    matchBreadRoute('/admin/dashboard');
    expect((new MenuItem('Dashboard'))->route('tardis.dashboard')->isActive())->toBeTrue();
});
