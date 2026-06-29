<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Tardis\Contracts\Plugins\Features\Provider\CSS;
use Tardis\Contracts\Plugins\Features\Provider\JS;
use Tardis\Contracts\Plugins\ThemePlugin;

test('styles returns HTML with link tag', function () {
    $manager = app(\Tardis\Manager\AssetManager::class);
    $html = $manager->styles();
    expect($html)->toContain('<link rel="stylesheet"');
    expect($html)->toContain('vendor/tardis/assets/app.css');
});

test('scripts returns HTML header', function () {
    $manager = app(\Tardis\Manager\AssetManager::class);
    $html = $manager->scripts();
    expect($html)->toContain('<!-- TARDIS Scripts -->');
});

test('styles duplicate guard returns empty on second call', function () {
    $manager = app(\Tardis\Manager\AssetManager::class);
    $firstCall = $manager->styles();
    $secondCall = $manager->styles();
    expect($firstCall)->not->toBeEmpty();
    expect($secondCall)->toBeEmpty();
});

test('scripts duplicate guard returns empty on second call', function () {
    $manager = app(\Tardis\Manager\AssetManager::class);
    $firstCall = $manager->scripts();
    $secondCall = $manager->scripts();
    expect($firstCall)->not->toBeEmpty();
    expect($secondCall)->toBeEmpty();
});

test('plugin CSS is included when plugin implements CSS interface', function () {
    $pluginManager = app(\Tardis\Manager\PluginManager::class);
    app()->instance(\Tardis\Manager\PluginManager::class, $pluginManager);
    
    $cssPlugin = new class implements CSS {
        public function provideCSS(): string {
            return '.test-css{color:red}';
        }
    };
    
    $pluginManager->register('test-css', $cssPlugin::class);
    $pluginManager->enable('test-css');
    
    $assetManager = app(\Tardis\Manager\AssetManager::class);
    $html = $assetManager->styles();
    expect($html)->toContain('.test-css{color:red}');
});

test('plugin JS is included when plugin implements JS interface', function () {
    $pluginManager = app(\Tardis\Manager\PluginManager::class);
    app()->instance(\Tardis\Manager\PluginManager::class, $pluginManager);
    
    $jsPlugin = new class implements JS {
        public function provideJS(): string {
            return 'console.log("test-js");';
        }
    };
    
    $pluginManager->register('test-js', $jsPlugin::class);
    $pluginManager->enable('test-js');
    
    $assetManager = app(\Tardis\Manager\AssetManager::class);
    $html = $assetManager->scripts();
    expect($html)->toContain('console.log("test-js");');
});

test('ThemePlugin styles are included', function () {
    $pluginManager = app(\Tardis\Manager\PluginManager::class);
    app()->instance(\Tardis\Manager\PluginManager::class, $pluginManager);
    
    $themePlugin = new class implements ThemePlugin {
        public function name(): string {
            return 'test-theme';
        }
        
        public function description(): string {
            return 'Test theme';
        }
        
        public function getTheme(): array {
            return ['--primary' => '#ff0000'];
        }
        
        public function getStyles(): string {
            return '.theme-style{background:blue;}';
        }
    };
    
    $pluginManager->register('test-theme', $themePlugin::class);
    $pluginManager->enable('test-theme');
    
    $assetManager = app(\Tardis\Manager\AssetManager::class);
    $html = $assetManager->styles();
    expect($html)->toContain('.theme-style{background:blue;}');
});
