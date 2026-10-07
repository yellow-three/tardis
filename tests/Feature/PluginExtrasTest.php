<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ViewErrorBag;
use Tardis\Assets\Asset;
use Tardis\Contracts\Plugins\Features\Provider\Routes;
use Tardis\Formfields\Formfield;
use Tardis\Manager\AssetManager;
use Tardis\Manager\PluginManager;

class RoutePlugin implements Routes
{
    public function provideRoutes(Router $router): void
    {
        $router->get('/blog-plugin', fn () => 'plugin page')->name('blog.index');
    }
}

class AssetField extends Formfield
{
    public static string $file = '';

    public function type(): string
    {
        return 'assetful';
    }

    public function render(): string
    {
        return 'tardis::formfields.text';
    }

    public function assets(): array
    {
        return [Asset::file(self::$file)];
    }
}

test('an enabled plugin adds routes inside the panel group', function () {
    $plugins = app(PluginManager::class);
    $plugins->register('blog', RoutePlugin::class);
    $plugins->enable('blog');

    reloadAdminRoutes();

    $route = app('router')->getRoutes()->getByName('tardis.blog.index');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('admin/blog-plugin')
        ->and($route->gatherMiddleware())->toContain('web', 'tardis.admin');
});

test('a disabled plugin adds no routes', function () {
    $plugins = app(PluginManager::class);
    $plugins->register('blog', RoutePlugin::class);
    $plugins->disable('blog');

    reloadAdminRoutes();

    expect(app('router')->getRoutes()->getByName('tardis.blog.index'))->toBeNull();
});

test('inline blocks carry the csp nonce when one is configured', function () {
    config(['tardis.csp.nonce' => 'abc123']);
    $assets = new AssetManager(app());
    $assets->addCss(Asset::inlineCss('.x{top:0}'));

    expect($assets->styles())->toContain('<style nonce="abc123">.x{top:0}</style>');
});

test('a closure can supply the nonce and an unsafe value is dropped', function () {
    config(['tardis.csp.nonce' => fn () => 'from-closure']);
    expect((new AssetManager(app()))->nonceAttribute())->toBe(' nonce="from-closure"');

    config(['tardis.csp.nonce' => 'bad"><script>']);
    expect((new AssetManager(app()))->nonceAttribute())->toBe('');
});

test('inline blocks have no nonce attribute by default', function () {
    $assets = new AssetManager(app());
    $assets->addCss(Asset::inlineCss('.x{top:0}'));

    expect($assets->styles())->toContain('<style>.x{top:0}</style>');
});

test('a formfield asset is required once however many fields ask for it', function () {
    $dir = sys_get_temp_dir().'/tardis-field-assets-'.uniqid();
    File::ensureDirectoryExists($dir);
    File::put($dir.'/editor.js', 'window.editor=1');
    AssetField::$file = $dir.'/editor.js';

    $assets = new AssetManager(app());
    app()->instance(AssetManager::class, $assets);

    view()->share('errors', new ViewErrorBag);

    foreach ([new AssetField('a'), new AssetField('b')] as $field) {
        $html = Blade::render('<x-tardis::form-field :field="$field" />', ['field' => $field]);
    }

    expect(substr_count($assets->scripts(), '/_assets/'))->toBe(1);

    File::deleteDirectory($dir);
});

test('tardis:plugins lists plugins and toggles them', function () {
    $plugins = app(PluginManager::class);
    $plugins->register('blog', RoutePlugin::class);
    $plugins->enable('blog');

    Artisan::call('tardis:plugins', ['--json' => true]);
    expect(Artisan::output())->toContain('"name": "blog"')->toContain('"state": "enabled"');

    expect(Artisan::call('tardis:plugins', ['action' => 'disable', 'name' => 'blog']))->toBe(0)
        ->and($plugins->isEnabled('blog'))->toBeFalse()
        ->and(Artisan::call('tardis:plugins', ['action' => 'enable', 'name' => 'nope']))->toBe(1);
});
