<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Assets\Asset;
use Tardis\Contracts\Plugins\Features\Provider\CSS;
use Tardis\Contracts\Plugins\Features\Provider\JS;
use Tardis\Manager\AssetManager;
use Tardis\Manager\PluginManager;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/tardis-file-assets-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    File::put($this->dir.'/plugin.css', '.plugin{color:red}');
    File::put($this->dir.'/plugin.js', 'window.pluginLoaded=1;');

    $this->assets = new AssetManager(app());
    app()->instance(AssetManager::class, $this->assets);
    reloadAdminRoutes();
});

afterEach(fn () => File::deleteDirectory($this->dir));

test('a file asset is served from its content hash with an immutable cache header', function () {
    // Other suites leave global middleware behind that rewrites Cache-Control; the route itself has none.
    $this->withoutMiddleware();

    $asset = Asset::file($this->dir.'/plugin.css');
    $this->assets->addCss($asset);

    $response = $this->get('/admin/_assets/'.$asset->hash().'.css');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('immutable')->toContain('max-age=31536000')
        ->and($response->getContent())->toBe('.plugin{color:red}');
});

test('a repeat request with the etag is answered 304', function () {
    $asset = Asset::file($this->dir.'/plugin.js');
    $this->assets->addJs($asset);

    $first = $this->get('/admin/_assets/'.$asset->hash().'.js');

    $this->get('/admin/_assets/'.$asset->hash().'.js', ['If-None-Match' => $first->headers->get('ETag')])->assertStatus(304);
});

test('only registered hashes resolve and the extension must match', function () {
    $asset = Asset::file($this->dir.'/plugin.css');
    $this->assets->addCss($asset);

    $this->get('/admin/_assets/'.str_repeat('a', 16).'.css')->assertNotFound();
    $this->get('/admin/_assets/'.$asset->hash().'.js')->assertNotFound();
    $this->get('/admin/_assets/..%2F..%2F.env.css')->assertNotFound();
});

test('a changed file gets a new url', function () {
    $before = Asset::file($this->dir.'/plugin.css')->hash();
    File::put($this->dir.'/plugin.css', '.plugin{color:blue}');

    expect(Asset::file($this->dir.'/plugin.css')->hash())->not->toBe($before);
});

test('only css and js files can be assets', function () {
    Asset::file($this->dir.'/secrets.env');
})->throws(InvalidArgumentException::class);

test('a registered file asset is written as a hashed link and script', function () {
    $this->assets->addCss(Asset::file($this->dir.'/plugin.css'));
    $this->assets->addJs(Asset::file($this->dir.'/plugin.js'));

    expect($this->assets->styles())->toMatch('#<link rel="stylesheet" href="[^"]*/_assets/[a-f0-9]{16}\.css">#')
        ->and($this->assets->scripts())->toMatch('#<script src="[^"]*/_assets/[a-f0-9]{16}\.js" defer></script>#');
});

test('an asset scoped to the login pages is not written on admin pages', function () {
    $this->assets->addCss(Asset::file($this->dir.'/plugin.css')->scope('auth'));

    expect($this->assets->styles('admin'))->not->toContain('_assets');

    app()->instance(AssetManager::class, $auth = new AssetManager(app()));
    $auth->addCss(Asset::file($this->dir.'/plugin.css')->scope('auth'));

    expect($auth->styles('auth'))->toContain('_assets');
});

test('an asset can be limited to routes and to an ability', function () {
    $asset = Asset::file($this->dir.'/plugin.css')->routes('tardis.bread.*')->ability('edit posts');
    $allow = fn () => true;
    $deny = fn () => false;

    expect($asset->wantedOn('admin', 'tardis.bread.index', $allow))->toBeTrue()
        ->and($asset->wantedOn('admin', 'tardis.dashboard', $allow))->toBeFalse()
        ->and($asset->wantedOn('admin', null, $allow))->toBeFalse()
        ->and($asset->wantedOn('admin', 'tardis.bread.index', $deny))->toBeFalse()
        ->and($asset->wantedOn('auth', 'tardis.bread.index', $allow))->toBeFalse()
        ->and($asset->scope('both')->wantedOn('auth', 'tardis.bread.index', $allow))->toBeTrue();
});

class FileAssetPlugin implements CSS, JS
{
    public static string $dir = '';

    public function provideCSS(): string|Asset|array
    {
        return [Asset::file(self::$dir.'/plugin.css'), '.inline{top:0}'];
    }

    public function provideJS(): string|Asset|array
    {
        return Asset::file(self::$dir.'/plugin.js');
    }
}

test('a plugin can provide file assets next to inline text and they are served', function () {
    FileAssetPlugin::$dir = $this->dir;
    $plugins = app(PluginManager::class);
    $plugins->register('file-assets', FileAssetPlugin::class);
    $plugins->enable('file-assets');

    $css = Asset::file($this->dir.'/plugin.css');

    expect($this->assets->styles())->toContain('/_assets/'.$css->hash().'.css')->toContain('.inline{top:0}')
        ->and($this->assets->scripts())->toContain('/_assets/'.Asset::file($this->dir.'/plugin.js')->hash().'.js');

    $this->get('/admin/_assets/'.$css->hash().'.css')->assertOk();
});
