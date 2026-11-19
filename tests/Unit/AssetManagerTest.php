<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tardis\Assets\Asset;
use Tardis\Assets\AssetManager;
use Tardis\Contracts\Plugins\Features\Provider\CSS;
use Tardis\Contracts\Plugins\Features\Provider\JS;
use Tardis\Plugins\PluginManager;
use Tardis\Settings\SettingsManager;

test('styles returns HTML with link tag', function () {
    $hotPath = AssetManager::packageHotPath();
    @unlink($hotPath);

    $manager = app(AssetManager::class);
    $html = $manager->styles();
    expect($html)->toContain('<link rel="stylesheet"');
    expect($html)->toContain('/_assets/'.Asset::file(AssetManager::compiledPath('app.css'))->hash().'.css');
});

test('scripts returns HTML header', function () {
    $manager = app(AssetManager::class);
    $html = $manager->scripts();
    expect($html)->toContain('<!-- Tardis Scripts -->');
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

test('development mode loads the Vite client and the sources, the script as a classic script', function () {
    $hotPath = AssetManager::packageHotPath();
    $previous = is_file($hotPath) ? file_get_contents($hotPath) : null;
    file_put_contents($hotPath, 'https://tardis.test:5173');

    try {
        $manager = new AssetManager(app());
        $styles = $manager->styles();
        $scripts = $manager->scripts();

        expect($styles)->toContain('<script type="module" src="https://tardis.test:5173/@vite/client"></script>')
            ->toContain('<link rel="stylesheet" href="https://tardis.test:5173/resources/css/app.css">')
            ->not->toContain('/_assets/')
            ->and($scripts)->toContain('<script src="https://tardis.test:5173/resources/js/app.js"></script>')
            ->not->toContain('type="module"');
    } finally {
        $previous === null ? @unlink($hotPath) : file_put_contents($hotPath, $previous);
    }
});

test('the core assets are served from the package, with nothing published', function () {
    @unlink(AssetManager::packageHotPath());

    $manager = new AssetManager(app());
    $styles = $manager->styles();
    $scripts = $manager->scripts();
    [$css, $js] = $manager->coreAssets();

    expect($styles)->toContain('/_assets/'.$css->hash().'.css')->not->toContain('vendor/tardis')
        ->and($scripts)->toContain('<script src="'.route('tardis.assets', ['hash' => $js->hash(), 'extension' => 'js']).'"></script>')
        ->and($manager->findByHash((string) $css->hash(), 'css'))->not->toBeNull()
        ->and($manager->findByHash((string) $js->hash(), 'js'))->not->toBeNull();
});

test('the core script is not deferred: it has to run before Livewire starts Alpine', function () {
    @unlink(AssetManager::packageHotPath());

    [, $js] = (new AssetManager(app()))->coreAssets();

    expect($js->defer)->toBeFalse()->and($js->scope)->toBe('both');
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

test('custom CSS from the appearance settings is injected and cannot close its style tag', function () {
    $settings = app(SettingsManager::class);
    $settings->loadPreset(__DIR__.'/../../resources/presets/settings.json');
    $settings->set('appearance.custom_css', 'body{color:red}</style><script>alert(1)</script>', false);

    $html = app(AssetManager::class)->styles();

    expect($html)->toContain('id="tardis-custom-css"')->toContain('body{color:red}')->not->toContain('</style><script>');
});
