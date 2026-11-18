<?php

declare(strict_types=1);

use Tardis\Assets\AssetManager;
use Tardis\Contracts\Plugins\ThemePlugin;
use Tardis\Plugins\PluginManager;
use Tardis\Theme\BuiltinThemes;
use Tardis\Theme\Theme;
use Tardis\Theme\ThemeManager;

class ThemeSourcePlugin implements ThemePlugin
{
    /** @var list<array<string, mixed>> */
    public static array $themes = [];

    public function name(): string
    {
        return 'ocean-themes';
    }

    public function description(): string
    {
        return 'Ocean themes';
    }

    public function themes(): array
    {
        return static::$themes;
    }
}

function oceanTheme(array $overrides = []): array
{
    return $overrides + [
        'name' => 'ocean',
        'scheme' => 'dark',
        'label' => 'Ocean',
        'colors' => ['primary' => '#0ea5e9', 'base-100' => '#0b1220', 'base-content' => '#e2e8f0'],
    ];
}

function registerOcean(array $themes): void
{
    ThemeSourcePlugin::$themes = $themes;
    $manager = app(PluginManager::class);
    $manager->register('ocean-themes', ThemeSourcePlugin::class);
    $manager->enableByDefault('ocean-themes');
    app()->forgetInstance(ThemeManager::class);
}

test('the built-in themes live in one place, not in the stylesheet as well', function () {
    $stylesheet = file_get_contents(__DIR__.'/../../resources/css/app.css');

    expect($stylesheet)->not->toContain('daisyui/theme');

    $css = app(ThemeManager::class)->css();

    foreach (BuiltinThemes::all() as $definition) {
        expect($css)->toContain('[data-theme="'.$definition['name'].'"]');
    }
});

test('the default light theme also paints a page without data-theme', function () {
    expect(app(ThemeManager::class)->css())->toContain(':where(:root),[data-theme="tardis-light"]{');
});

/**
 * DaisyUI 5 components size and round themselves from these variables. The
 * themes never set them, so radius and border widths came out invalid.
 */
test('every theme sets the DaisyUI shape variables, with DaisyUI defaults', function () {
    $css = Theme::fromArray(oceanTheme())->css();

    expect($css)->toContain('--radius-selector:0.5rem;')
        ->toContain('--radius-field:0.25rem;')
        ->toContain('--radius-box:0.5rem;')
        ->toContain('--size-selector:0.25rem;')
        ->toContain('--size-field:0.25rem;')
        ->toContain('--border:1px;')
        ->toContain('--depth:1;')
        ->toContain('--noise:0;');
});

test('a theme can change its shape, and unsafe shape values are refused', function () {
    $theme = Theme::fromArray(oceanTheme(['shape' => ['radius-box' => '1rem', 'depth' => '0', 'unknown' => '9px']]));

    expect($theme->css())->toContain('--radius-box:1rem;')->toContain('--depth:0;')->not->toContain('unknown')
        ->and(Theme::fromArray(oceanTheme(['shape' => ['radius-box' => '1rem;}body{display:none']])))->toBeNull();
});

test('a theme plugin adds selectable themes', function () {
    registerOcean([oceanTheme()]);

    $themes = app(ThemeManager::class);

    expect($themes->names('dark'))->toContain('ocean')
        ->and($themes->css())->toContain('[data-theme="ocean"]{');
});

test('a theme plugin cannot replace a built-in theme or inject CSS', function () {
    registerOcean([
        oceanTheme(['name' => 'tardis-dark']),
        oceanTheme(['name' => 'evil', 'colors' => ['primary' => 'red;}body{display:none', 'base-100' => '#000', 'base-content' => '#fff']]),
    ]);

    $css = app(ThemeManager::class)->css();

    expect($css)->not->toContain('#0ea5e9')
        ->not->toContain('display:none');
});

test('a theme plugin no longer writes a global :root override', function () {
    registerOcean([oceanTheme()]);

    expect(app(AssetManager::class)->styles())->not->toMatch('/<style[^>]*>:root\{/');
});

test('a theme keeps its own status colours and gets DaisyUI defaults for the rest', function () {
    $css = Theme::fromArray(oceanTheme(['colors' => ['primary' => '#0ea5e9', 'base-100' => '#0b1220', 'base-content' => '#e2e8f0', 'error' => '#ff0000']]))->css();

    expect($css)->toContain('--color-error:#ff0000;')
        ->not->toContain('--color-error:oklch(71%')
        ->toContain('--color-success:oklch(76% 0.177 163.223);');
});
