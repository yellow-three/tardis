<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::ensureDirectoryExists(storage_path('framework'));

    // The publish destination is resolved when the provider boots, so the test
    // must use the same public path the provider saw rather than swapping it.
    // Start from a clean slate so a stale publish cannot mask a failed one.
    File::deleteDirectory(public_path('vendor/tardis'));
    File::deleteDirectory(public_path('tardis-assets'));
});

afterEach(function () {
    File::deleteDirectory(public_path('vendor/tardis'));
    File::deleteDirectory(public_path('tardis-assets'));
});

test('doctor fails when the install is unhealthy', function () {
    $this->artisan('tardis:doctor')->assertFailed();
});

test('doctor succeeds on a healthy install', function () {
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    $this->artisan('vendor:publish', ['--tag' => 'tardis-assets', '--force' => true])->assertSuccessful();

    $this->artisan('tardis:doctor')->assertSuccessful();
});

test('doctor outputs json with the --json option', function () {
    $this->artisan('tardis:doctor', ['--json' => true])
        ->expectsOutputToContain('"checks"')
        ->assertFailed();
});
