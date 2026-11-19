<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tardis\Diagnostics\DoctorReport;

beforeEach(function () {
    File::ensureDirectoryExists(storage_path('framework'));

    // A leftover published copy would make the assets check warn.
    File::deleteDirectory(public_path('vendor/tardis'));
});

test('doctor fails when the install is unhealthy', function () {
    $this->artisan('tardis:doctor')->assertFailed();
});

test('doctor succeeds on a healthy install', function () {
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();

    // The report goes into the failure message: on a new Laravel or Livewire
    // release this test is the first to say which check broke.
    $exitCode = Artisan::call('tardis:doctor');

    expect($exitCode)->toBe(0, Artisan::output());
});

test('doctor outputs json with the --json option', function () {
    $this->artisan('tardis:doctor', ['--json' => true])
        ->expectsOutputToContain('"checks"')
        ->assertFailed();
});

test('a development build of Laravel satisfies the version requirement', function () {
    $satisfies = new ReflectionMethod(DoctorReport::class, 'satisfies');

    expect($satisfies->invoke(null, '13.x-dev', '^13.0'))->toBeTrue()
        ->and($satisfies->invoke(null, '13.2.x-dev', '^13.0'))->toBeTrue()
        ->and($satisfies->invoke(null, '13.4.1', '^13.0'))->toBeTrue()
        ->and($satisfies->invoke(null, '14.x-dev', '^13.0'))->toBeFalse()
        ->and($satisfies->invoke(null, '12.40.0', '^13.0'))->toBeFalse()
        // A requirement pinned to a branch, as CI does for unreleased builds.
        ->and($satisfies->invoke(null, '13.43.0', '13.x-dev as 13.99'))->toBeTrue()
        ->and($satisfies->invoke(null, '14.x-dev', 'dev-master as 13.99'))->toBeTrue();
});
