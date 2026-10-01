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

test('ThemePlugin styles are included', function () {
    $pluginManager = app(PluginManager::class);
    app()->instance(PluginManager::class, $pluginManager);

    $themePlugin = new class implements ThemePlugin
    {
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
            return ['--primary' => '#ff0000'];
        }

        public function getStyles(): string
        {
            return '.theme-style{background:blue;}';
        }
    };

    $pluginManager->register('test-theme', $themePlugin::class);
    $pluginManager->enable('test-theme');

    $assetManager = app(AssetManager::class);
    $html = $assetManager->styles();
    expect($html)->toContain('.theme-style{background:blue;}');
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
