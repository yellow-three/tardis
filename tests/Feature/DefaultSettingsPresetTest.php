<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Settings\SettingsManager;
use Tardis\TardisServiceProvider;

beforeEach(function () {
    $this->settingsFile = storage_path('tardis/settings/settings.json');
    File::delete($this->settingsFile);
    app()->forgetInstance(SettingsManager::class);
});

afterEach(function () {
    File::delete($this->settingsFile);
    app()->forgetInstance(SettingsManager::class);
});

/**
 * The preset used to be written by the service provider, which checked for
 * the file on every web request and wrote it from a GET. It is now the
 * starting point the manager reads when there is no file yet; nothing is
 * written until an administrator saves.
 */
test('without a settings file the preset values are used', function () {
    expect(app(SettingsManager::class)->get('admin.title'))->toBe('TARDIS Admin');
});

test('reading the settings never writes the file', function () {
    app(SettingsManager::class)->all();

    expect(File::exists($this->settingsFile))->toBeFalse();
});

test('saving writes the file, starting from the preset', function () {
    app(SettingsManager::class)->set('admin.title', 'My Panel');

    expect(File::exists($this->settingsFile))->toBeTrue();

    app()->forgetInstance(SettingsManager::class);

    expect(app(SettingsManager::class)->get('admin.title'))->toBe('My Panel')
        ->and(app(SettingsManager::class)->get('appearance.mode'))->not->toBeNull();
});

test('an existing settings file is never mixed with the preset', function () {
    File::ensureDirectoryExists(dirname($this->settingsFile));
    File::put($this->settingsFile, '[]');

    expect(app(SettingsManager::class)->get('admin.title'))->toBeNull()
        ->and(File::get($this->settingsFile))->toBe('[]');
});

test('the service provider no longer touches the settings file at boot', function () {
    expect(method_exists(TardisServiceProvider::class, 'loadDefaultSettings'))->toBeFalse();
});
