<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tardis\Contracts\Plugins\Features\Provider\CSS;
use Tardis\Contracts\Plugins\Features\Provider\JS;
use Tardis\Contracts\Plugins\ThemePlugin;
use Tardis\Manager\AssetManager;
use Tardis\Manager\PluginManager;

test('styles returns HTML with link tag', function () {
    $hotPath = AssetManager::packageHotPath();
    @unlink($hotPath);

    $manager = app(AssetManager::class);
    $html = $manager->styles();
    expect($html)->toContain('<link rel="stylesheet"');
    expect($html)->toContain('vendor/tardis/assets/app.css');
});

test('scripts returns HTML header', function () {
    $manager = app(AssetManager::class);
    $html = $manager->scripts();
    expect($html)->toContain('<!-- TARDIS Scripts -->');
});

test('styles duplicate guard returns empty on second call', function () {
    $manager = app(AssetManager::class);
    $firstCall = $manager->styles();
    $secondCall = $manager->styles();
    expect($firstCall)->not->toBeEmpty();
    expect($secondCall)->toBeEmpty();
});

test('scripts duplicate guard returns empty on second call', function () {
    $manager = app(AssetManager::class);
    $firstCall = $manager->scripts();
    $secondCall = $manager->scripts();
    expect($firstCall)->not->toBeEmpty();
    expect($secondCall)->toBeEmpty();
});

test('plugin CSS is included when plugin implements CSS interface', function () {
    $pluginManager = app(PluginManager::class);
    app()->instance(PluginManager::class, $pluginManager);

    $cssPlugin = new class implements CSS
    {
        public function provideCSS(): string
        {
            return '.test-css{color:red}';
        }
    };

    $pluginManager->register('test-css', $cssPlugin::class);
    $pluginManager->enable('test-css');

    $assetManager = app(AssetManager::class);
    $html = $assetManager->styles();
    expect($html)->toContain('.test-css{color:red}');
});

test('plugin JS is included when plugin implements JS interface', function () {
    $pluginManager = app(PluginManager::class);
    app()->instance(PluginManager::class, $pluginManager);

    $jsPlugin = new class implements JS
    {
        public function provideJS(): string
        {
            return 'console.log("test-js");';
        }
    };

    $pluginManager->register('test-js', $jsPlugin::class);
    $pluginManager->enable('test-js');

    $assetManager = app(AssetManager::class);
    $html = $assetManager->scripts();
    expect($html)->toContain('console.log("test-js");');
});

test('styles uses Vite dev server URL when hot file exists', function () {
    $hotPath = AssetManager::packageHotPath();
    $hotDir = dirname($hotPath);

    if (! is_dir($hotDir)) {
        mkdir($hotDir, 0755, true);
    }

    file_put_contents($hotPath, 'http://localhost:5173');

    try {
        $manager = new AssetManager(app());
        $html = $manager->styles();

        expect($html)->toContain('http://localhost:5173/resources/css/app.css');
        expect($html)->not->toContain('vendor/tardis/assets/app.css');
    } finally {
        @unlink($hotPath);
    }
});

test('styles uses production URL when hot file does not exist', function () {
    $hotPath = AssetManager::packageHotPath();
    @unlink($hotPath);

    $manager = new AssetManager(app());
    $html = $manager->styles();

    expect($html)->toContain('vendor/tardis/assets/app.css');
    expect($html)->not->toContain('/resources/css/app.css');
});

class ThemePluginFixture implements ThemePlugin
{
    /** @var array<string, string> */
    public static array $variables = [];

    public function name(): string
    {
        return 'test-theme';
    }

    public function description(): string
    {
        return 'Test theme';
    }

    public function getTheme(): array
    {
        return static::$variables;
    }
}

function themePluginFixture(array $variables): ThemePluginFixture
{
    ThemePluginFixture::$variables = $variables;

    return new ThemePluginFixture;
}

test('a ThemePlugin contributes its CSS variables', function () {
    $pluginManager = app(PluginManager::class);
    $pluginManager->register('test-theme', themePluginFixture(['--color-primary' => '#ff0000', '--tardis-radius' => '0.5rem'])::class);
    $pluginManager->enableByDefault('test-theme');

    $html = app(AssetManager::class)->styles();

    expect($html)->toContain(':root{')
        ->toContain('--color-primary:#ff0000;')
        ->toContain('--tardis-radius:0.5rem;');
});

test('theme variables that are not safe custom properties are dropped', function () {
    $pluginManager = app(PluginManager::class);
    $pluginManager->register('test-theme', themePluginFixture([
        '--ok' => 'oklch(45% 0.2 260)',
        'color' => 'red',
        '--bad name' => 'red',
        '--evil' => 'red;} body{display:none',
        '--url' => 'url(javascript:alert(1))',
        '--close' => '</style><script>x</script>',
    ])::class);
    $pluginManager->enableByDefault('test-theme');

    $html = app(AssetManager::class)->styles();

    expect($html)->toContain('--ok:oklch(45% 0.2 260);')
        ->not->toContain('display:none')
        ->not->toContain('javascript:')
        ->not->toContain('<script>x')
        ->not->toContain('--bad name')
        ->not->toContain('--evil')
        ->not->toContain('--url')
        ->not->toContain('--close');
});

test('published CSS URL carries a content hash so redeploys bust the cache', function () {
    @unlink(AssetManager::packageHotPath());

    $cssPath = public_path('vendor/tardis/assets/app.css');
    $dir = dirname($cssPath);
    $createdDir = ! is_dir($dir);

    if ($createdDir) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($cssPath, '.tardis-test{color:red}');

    try {
        $manager = new AssetManager(app());
        $html = $manager->styles();

        expect($html)->toContain('vendor/tardis/assets/app.css?v=');
        expect($html)->toContain('?v='.substr(md5_file($cssPath), 0, 8));
    } finally {
        @unlink($cssPath);

        if ($createdDir) {
            @rmdir($dir);
        }
    }
});

test('CSS URL stays bare when the bundle was never published', function () {
    @unlink(AssetManager::packageHotPath());

    $cssPath = public_path('vendor/tardis/assets/app.css');
    $existed = is_file($cssPath);
    $original = $existed ? file_get_contents($cssPath) : null;

    if ($existed) {
        @unlink($cssPath);
    }

    try {
        $manager = new AssetManager(app());
        $html = $manager->styles();

        expect($html)->toContain('vendor/tardis/assets/app.css');
        expect($html)->not->toContain('?v=');
    } finally {
        if ($existed) {
            file_put_contents($cssPath, $original);
        }
    }
});

test('CSS content hash changes when the published bundle changes', function () {
    @unlink(AssetManager::packageHotPath());

    $cssPath = public_path('vendor/tardis/assets/app.css');
    $dir = dirname($cssPath);
    $createdDir = ! is_dir($dir);

    if ($createdDir) {
        mkdir($dir, 0755, true);
    }

    $existed = is_file($cssPath);
    $original = $existed ? file_get_contents($cssPath) : null;

    try {
        file_put_contents($cssPath, '.a{color:red}');
        $firstHtml = (new AssetManager(app()))->styles();

        file_put_contents($cssPath, '.b{color:blue}');
        $secondHtml = (new AssetManager(app()))->styles();

        preg_match('/app\.css\?v=([a-f0-9]+)/', $firstHtml, $first);
        preg_match('/app\.css\?v=([a-f0-9]+)/', $secondHtml, $second);

        expect($first[1] ?? null)->not->toBeNull();
        expect($second[1] ?? null)->not->toBeNull();
        expect($first[1])->not->toBe($second[1]);
    } finally {
        if ($existed) {
            file_put_contents($cssPath, $original);
        } else {
            @unlink($cssPath);
        }

        if ($createdDir) {
            @rmdir($dir);
        }
    }
});

test('dev mode CSS URL is never given a content hash', function () {
    $hotPath = AssetManager::packageHotPath();
    $hotDir = dirname($hotPath);

    if (! is_dir($hotDir)) {
        mkdir($hotDir, 0755, true);
    }

    file_put_contents($hotPath, 'http://localhost:5173');

    try {
        $html = (new AssetManager(app()))->styles();

        expect($html)->toContain('http://localhost:5173/resources/css/app.css');
        expect($html)->not->toContain('?v=');
    } finally {
        @unlink($hotPath);
    }
});

test('configured additional css and js are emitted after the package assets', function () {
    config()->set('tardis.assets.css', ['https://cdn.example.test/extra.css', '/vendor/host/extra.css']);
    config()->set('tardis.assets.js', ['/vendor/host/extra.js']);

    $manager = app(AssetManager::class);
    $styles = $manager->styles();
    $scripts = $manager->scripts();

    expect($styles)->toContain('<link rel="stylesheet" href="https://cdn.example.test/extra.css">')
        ->toContain('<link rel="stylesheet" href="/vendor/host/extra.css">')
        ->and(strpos($styles, 'extra.css'))->toBeGreaterThan(strpos($styles, 'app.css'))
        ->and($scripts)->toContain('<script src="/vendor/host/extra.js" defer></script>');
});

test('configured assets that are not http or root-relative urls are dropped', function () {
    config()->set('tardis.assets.css', ['javascript:alert(1)', 'data:text/css,body{}', '//evil.test/x.css', '"><script>x</script>']);
    config()->set('tardis.assets.js', ['javascript:alert(1)']);

    $manager = app(AssetManager::class);

    expect($manager->styles())->not->toContain('javascript:')->not->toContain('data:text')->not->toContain('evil.test')->not->toContain('<script>x')
        ->and($manager->scripts())->not->toContain('javascript:');
});
