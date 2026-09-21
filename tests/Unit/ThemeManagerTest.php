<?php

declare(strict_types=1);

use Tardis\Manager\ThemeManager;

function manifestPath(): string
{
    return dirname(__DIR__, 2).'/public/tardis-assets/themes-manifest.json';
}

test('loadManifest loads themes from manifest file', function () {
    $manager = new ThemeManager;
    $manager->loadManifest(manifestPath());

    $themes = $manager->themes();
    expect($themes)->toHaveCount(2);
    expect($themes)->toHaveKey('tardis-light');
    expect($themes)->toHaveKey('tardis-dark');
});

test('loaded themes have correct metadata', function () {
    $manager = new ThemeManager;
    $manager->loadManifest(manifestPath());

    $light = $manager->resolve('tardis-light');
    expect($light->type)->toBe('light');
    expect($light->previewColors)->toHaveCount(4);
    expect($light->isCustom)->toBeFalse();

    $dark = $manager->resolve('tardis-dark');
    expect($dark->type)->toBe('dark');
});

test('default theme is set from manifest', function () {
    $manager = new ThemeManager;
    $manager->loadManifest(manifestPath());

    $default = $manager->default();
    expect($default)->not->toBeNull();
    expect($default->code)->toBe('tardis-light');
});

test('loadManifest throws exception for missing file', function () {
    $manager = new ThemeManager;
    $manager->loadManifest('/nonexistent/path.json');
})->throws(InvalidArgumentException::class, 'Theme manifest not found');

test('loadManifest throws exception for invalid JSON', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'theme_test');
    file_put_contents($tempFile, '{ invalid json }');

    try {
        $manager = new ThemeManager;
        $manager->loadManifest($tempFile);
    } finally {
        @unlink($tempFile);
    }
})->throws(RuntimeException::class, 'Invalid theme manifest');

test('loadManifest throws exception for invalid manifest structure', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'theme_test');
    file_put_contents($tempFile, json_encode(['other' => 'data']));

    try {
        $manager = new ThemeManager;
        $manager->loadManifest($tempFile);
    } finally {
        @unlink($tempFile);
    }
})->throws(RuntimeException::class, 'Invalid theme manifest');

test('loadManifest handles empty themes array', function () {
    $manager = new ThemeManager;

    // Create a temporary manifest with empty themes
    $tempFile = tempnam(sys_get_temp_dir(), 'theme_test');
    file_put_contents($tempFile, json_encode(['themes' => []]));

    try {
        $manager->loadManifest($tempFile);
        expect($manager->themes())->toHaveCount(0);
    } finally {
        @unlink($tempFile);
    }
});

test('getThemesWithMetadata returns same data as themes', function () {
    $manager = new ThemeManager;
    $manager->loadManifest(manifestPath());

    expect($manager->getThemesWithMetadata())->toBe($manager->themes());
});

test('register adds a theme manually', function () {
    $manager = new ThemeManager;
    $theme = $manager->register('custom', 'Custom Theme', 'A custom theme', 'light', ['#fff']);

    expect($theme->code)->toBe('custom');
    expect($manager->themes())->toHaveKey('custom');
});

test('resolve returns null for missing theme', function () {
    $manager = new ThemeManager;
    expect($manager->resolve('nonexistent'))->toBeNull();
});

test('default returns null when no themes registered', function () {
    $manager = new ThemeManager;
    expect($manager->default())->toBeNull();
});

test('active returns fallback theme when no themes registered', function () {
    $manager = new ThemeManager;
    $active = $manager->active();

    expect($active)->not->toBeNull();
    expect($active->code)->toBe('tardis-light');
});

test('loadManifestFromUrl throws exception for unreachable URL', function () {
    $manager = new ThemeManager;
    $manager->loadManifestFromUrl('http://localhost:19999/manifest.json');
})->throws(RuntimeException::class, 'Failed to fetch theme manifest');

test('loadManifestFromUrl throws exception for invalid JSON response', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'theme_url_test');
    file_put_contents($tempFile, 'not json');

    try {
        $manager = new ThemeManager;
        $manager->loadManifestFromUrl('file://'.$tempFile);
    } finally {
        @unlink($tempFile);
    }
})->throws(RuntimeException::class, 'Invalid theme manifest structure');
