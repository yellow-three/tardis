<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Contracts\Plugins\Features\Provider\Routes;
use Tardis\Http\PanelRoutes;
use Tardis\Plugins\PluginManager;

class ShelfRoutePlugin implements Routes
{
    public function provideRoutes(Router $router): void
    {
        $router->get('/shelf', fn () => 'plugin shelf')->name('shelf.index');
    }
}

function writeShelfBread(): void
{
    $path = (string) (config('tardis.bread.path') ?: storage_path('tardis/bread'));
    File::ensureDirectoryExists($path);
    File::put($path.'/shelf.json', json_encode(['slug' => 'shelf', 'model' => 'App\\Models\\Shelf', 'fields' => []]));
}

afterEach(function () {
    File::delete((string) (config('tardis.bread.path') ?: storage_path('tardis/bread')).'/shelf.json');
});

test('panel, BREAD and plugin routes share one middleware list', function () {
    $plugins = app(PluginManager::class);
    $plugins->register('shelf', ShelfRoutePlugin::class);
    $plugins->enable('shelf');

    reloadAdminRoutes();

    $routes = app('router')->getRoutes();

    foreach (['tardis.dashboard', 'tardis.bread.index', 'tardis.shelf.index'] as $name) {
        expect($routes->getByName($name)->gatherMiddleware())->toBe(PanelRoutes::MIDDLEWARE);
    }

    expect($routes->getByName('tardis.login')->gatherMiddleware())->toBe(PanelRoutes::GUEST_MIDDLEWARE);
});

test('a BREAD can not shadow a plugin route with the same first segment', function () {
    writeShelfBread();

    $plugins = app(PluginManager::class);
    $plugins->register('shelf', ShelfRoutePlugin::class);
    $plugins->enable('shelf');

    reloadAdminRoutes();

    $match = app('router')->getRoutes()->match(request()->create('/admin/shelf'));

    expect($match->getName())->toBe('tardis.shelf.index')
        ->and(fn () => app(JsonBreadSource::class)->save(['slug' => 'shelf', 'model' => '', 'fields' => []]))
        ->toThrow(InvalidArgumentException::class);
});

test('the old media browse address redirects to the media screen', function () {
    $route = app('router')->getRoutes()->getByName('tardis.media.browse');

    expect($route->uri())->toBe('admin/media/browse')
        ->and($route->gatherMiddleware())->toBe(PanelRoutes::MIDDLEWARE)
        ->and($route->getAction('uses'))->toBeInstanceOf(Closure::class);
});
