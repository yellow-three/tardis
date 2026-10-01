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

/**
 * availableThemes() backs the blocking theme script, which cannot degrade to a
 * warning or an exception: it runs inside <head> before first paint, so any
 * failure there breaks every page. These tests pin the three resolution paths.
 */
test('availableThemes returns an empty list when no manifest is readable', function () {
    @unlink(AssetManager::packageHotPath());

    $prodPath = public_path('tardis-assets/themes-manifest.json');
    $existed = is_file($prodPath);
    $original = $existed ? file_get_contents($prodPath) : null;

    if ($existed) {
        @unlink($prodPath);
    }

    try {
        expect(AssetManager::availableThemes())->toBe([]);
    } finally {
        if ($existed) {
            file_put_contents($prodPath, $original);
        }
    }
});

test('availableThemes reads the published manifest', function () {
    @unlink(AssetManager::packageHotPath());

    $prodPath = public_path('tardis-assets/themes-manifest.json');
    $dir = dirname($prodPath);
    $createdDir = ! is_dir($dir);

    if ($createdDir) {
        mkdir($dir, 0755, true);
    }

    $existed = is_file($prodPath);
    $original = $existed ? file_get_contents($prodPath) : null;

    $themes = [
        ['name' => 'winter', 'colorScheme' => 'light'],
        ['name' => 'dark', 'colorScheme' => 'dark'],
    ];

    file_put_contents($prodPath, json_encode(['themes' => $themes]));

    try {
        expect(AssetManager::availableThemes())->toBe($themes);
    } finally {
        if ($existed) {
            file_put_contents($prodPath, $original);
        } else {
            @unlink($prodPath);
        }

        if ($createdDir) {
            @rmdir($dir);
        }
    }
});

test('availableThemes drops malformed theme entries instead of emitting them', function () {
    @unlink(AssetManager::packageHotPath());

    $prodPath = public_path('tardis-assets/themes-manifest.json');
    $dir = dirname($prodPath);
    $createdDir = ! is_dir($dir);

    if ($createdDir) {
        mkdir($dir, 0755, true);
    }

    $existed = is_file($prodPath);
    $original = $existed ? file_get_contents($prodPath) : null;

    // A hand-edited manifest can contain scalars or nulls where theme objects are
    // expected. `pick()` reads .colorScheme off each entry, so a stray scalar would
    // throw inside the blocking script and take the whole page's styling with it.
    file_put_contents($prodPath, json_encode([
        'themes' => ['not-an-object', null, ['name' => 'dark', 'colorScheme' => 'dark']],
    ]));

    try {
        expect(AssetManager::availableThemes())->toBe([['name' => 'dark', 'colorScheme' => 'dark']]);
    } finally {
        if ($existed) {
            file_put_contents($prodPath, $original);
        } else {
            @unlink($prodPath);
        }

        if ($createdDir) {
            @rmdir($dir);
        }
    }
});

test('availableThemes falls back to the package manifest when the dev server is unreachable', function () {
    $hotPath = AssetManager::packageHotPath();
    $hotDir = dirname($hotPath);

    if (! is_dir($hotDir)) {
        mkdir($hotDir, 0755, true);
    }

    // Loopback discard port: nothing listens there, so the dev-server read fails
    // immediately and the package copy must take over. The Vite server is often
    // unreachable from inside Docker / Lerd even when the hot file exists.
    file_put_contents($hotPath, 'http://127.0.0.1:9');

    $packageManifest = AssetManager::packageManifestPath();
    $packageExisted = is_file($packageManifest);
    $packageOriginal = $packageExisted ? file_get_contents($packageManifest) : null;

    $themes = [['name' => 'package-light', 'colorScheme' => 'light']];

    try {
        file_put_contents($packageManifest, json_encode(['themes' => $themes]));

        expect(AssetManager::availableThemes())->toBe($themes);
    } finally {
        if ($packageExisted) {
            file_put_contents($packageManifest, $packageOriginal);
        } else {
            @unlink($packageManifest);
        }

        @unlink($hotPath);
    }
});
