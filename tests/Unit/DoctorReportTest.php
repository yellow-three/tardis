<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Bread\BreadManager;
use Tardis\Diagnostics\CheckResult;
use Tardis\Diagnostics\DoctorReport;

beforeEach(function () {
    File::ensureDirectoryExists(storage_path('framework'));

    $this->publicDir = sys_get_temp_dir().'/tardis-public-'.uniqid();
    File::ensureDirectoryExists($this->publicDir.'/vendor/tardis/assets');
    File::ensureDirectoryExists($this->publicDir.'/tardis-assets');
    app()->usePublicPath($this->publicDir);
});

afterEach(function () {
    File::deleteDirectory($this->publicDir);
});

function reportCheck(DoctorReport $report, string $name): CheckResult
{
    foreach ($report->checks as $check) {
        if ($check->name === $name) {
            return $check;
        }
    }

    throw new RuntimeException("No check named [{$name}].");
}

test('run returns ten checks', function () {
    expect(DoctorReport::run()->checks)->toHaveCount(10);
});

test('toArray exposes the ok flag and the check shape', function () {
    $array = DoctorReport::run()->toArray();

    expect($array)->toHaveKeys(['ok', 'checks'])
        ->and($array['checks'][0])->toHaveKeys(['name', 'status', 'message', 'hint']);
});

test('toJson produces valid json', function () {
    expect(json_decode(DoctorReport::run()->toJson(), true))->toBeArray();
});

test('the php version check passes on a supported runtime', function () {
    expect(reportCheck(DoctorReport::run(), 'PHP version')->isOk())->toBeTrue();
});

test('the storage check fails when a directory is missing', function () {
    File::deleteDirectory(storage_path('framework'));

    expect(reportCheck(DoctorReport::run(), 'Storage')->isFailure())->toBeTrue();
});

test('the tables check fails before migrating and passes after', function () {
    expect(reportCheck(DoctorReport::run(), 'Database tables')->isFailure())->toBeTrue();

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();

    expect(reportCheck(DoctorReport::run(), 'Database tables')->isOk())->toBeTrue();
});

test('the route cache check warns when a cached route file exists', function () {
    $path = app()->getCachedRoutesPath();
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<?php return [];');

    try {
        expect(reportCheck(DoctorReport::run(), 'Route cache')->isWarning())->toBeTrue();
    } finally {
        File::delete($path);
    }
});

test('the assets check fails when published assets are missing', function () {
    expect(reportCheck(DoctorReport::run(), 'Published assets')->isFailure())->toBeTrue();
});

test('the assets check warns when published assets differ from the build', function () {
    File::put($this->publicDir.'/vendor/tardis/assets/app.css', 'published');
    File::put($this->publicDir.'/vendor/tardis/assets/app.js', 'published');

    expect(reportCheck(DoctorReport::run(), 'Published assets')->isWarning())->toBeTrue();
});

test('the theme manifest check fails when the manifest is missing', function () {
    expect(reportCheck(DoctorReport::run(), 'Theme manifest')->isFailure())->toBeTrue();
});

test('the theme manifest check passes for valid json', function () {
    File::put($this->publicDir.'/tardis-assets/themes-manifest.json', (string) json_encode(['themes' => []]));

    expect(reportCheck(DoctorReport::run(), 'Theme manifest')->isOk())->toBeTrue();
});

test('the bread check fails for a definition whose model is missing', function () {
    app(BreadManager::class)->save([
        'slug' => 'ghosts',
        'model' => 'App\\Models\\Ghost',
        'name' => 'Ghost',
        'name_plural' => 'Ghosts',
    ]);

    expect(reportCheck(DoctorReport::run(), 'BREAD definitions')->isFailure())->toBeTrue();
});

test('hasFailures is true when any check fails', function () {
    expect(DoctorReport::run()->hasFailures())->toBeTrue();
});
