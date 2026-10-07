<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Manager\SettingsManager;
use Tardis\TardisServiceProvider;

beforeEach(function () {
    $this->settingsFile = storage_path('tardis/settings/settings.json');
    $this->hadFile = File::exists($this->settingsFile);
    $this->backup = $this->hadFile ? File::get($this->settingsFile) : null;
    File::delete($this->settingsFile);
    app()->forgetInstance(SettingsManager::class);
});

afterEach(function () {
    $this->hadFile ? File::put($this->settingsFile, $this->backup) : File::delete($this->settingsFile);
    app()->forgetInstance(SettingsManager::class);
});

function runProviderDefaults(bool $console): void
{
    $app = app();
    $flag = new ReflectionProperty($app, 'isRunningInConsole');
    $original = $flag->getValue($app);
    $flag->setValue($app, $console);

    try {
        $provider = new TardisServiceProvider($app);
        (new ReflectionMethod($provider, 'loadDefaultSettings'))->invoke($provider);
    } finally {
        $flag->setValue($app, $original);
    }
}

test('the preset is seeded on first web boot when no settings file exists', function () {
    runProviderDefaults(false);

    expect(File::exists($this->settingsFile))->toBeTrue()
        ->and(app(SettingsManager::class)->get('admin.title'))->toBe('TARDIS Admin');
});

test('the preset is not written from the console', function () {
    runProviderDefaults(true);

    expect(File::exists($this->settingsFile))->toBeFalse();
});

test('an existing settings file is never overwritten by the preset', function () {
    File::ensureDirectoryExists(dirname($this->settingsFile));
    File::put($this->settingsFile, '[]');

    runProviderDefaults(false);

    expect(File::get($this->settingsFile))->toBe('[]');
});
