<?php

declare(strict_types=1);

$manifestPath = dirname(__DIR__, 2) . '/public/tardis-assets/themes-manifest.json';

test('theme manifest file exists', function () use ($manifestPath) {
    expect(file_exists($manifestPath))->toBeTrue();
});

test('theme manifest is valid JSON', function () use ($manifestPath) {
    $content = file_get_contents($manifestPath);
    $data = json_decode($content, true);

    expect($data)->toBeArray();
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
});

test('theme manifest contains themes array', function () use ($manifestPath) {
    $data = json_decode(file_get_contents($manifestPath), true);

    expect($data)->toHaveKey('themes');
    expect($data['themes'])->toBeArray();
});

test('theme manifest contains tardis-light theme', function () use ($manifestPath) {
    $data = json_decode(file_get_contents($manifestPath), true);
    $themeNames = array_column($data['themes'], 'name');

    expect($themeNames)->toContain('tardis-light');
});

test('theme manifest contains tardis-dark theme', function () use ($manifestPath) {
    $data = json_decode(file_get_contents($manifestPath), true);
    $themeNames = array_column($data['themes'], 'name');

    expect($themeNames)->toContain('tardis-dark');
});

test('each theme has required fields', function () use ($manifestPath) {
    $data = json_decode(file_get_contents($manifestPath), true);

    foreach ($data['themes'] as $theme) {
        expect($theme)->toHaveKeys(['name', 'colorScheme', 'previewColors']);
        expect($theme['name'])->toBeString();
        expect($theme['colorScheme'])->toBeIn(['light', 'dark']);
        expect($theme['previewColors'])->toBeArray();
    }
});

test('tardis-light theme has light color scheme', function () use ($manifestPath) {
    $data = json_decode(file_get_contents($manifestPath), true);
    $light = collect($data['themes'])->firstWhere('name', 'tardis-light');

    expect($light['colorScheme'])->toBe('light');
});

test('tardis-dark theme has dark color scheme', function () use ($manifestPath) {
    $data = json_decode(file_get_contents($manifestPath), true);
    $dark = collect($data['themes'])->firstWhere('name', 'tardis-dark');

    expect($dark['colorScheme'])->toBe('dark');
});
