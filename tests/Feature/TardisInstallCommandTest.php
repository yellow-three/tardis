<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tardis\Models\Permission;
use Tardis\Models\Role;

class InstallTestUser extends User
{
    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    // The publish destination is resolved when the provider boots, so the test
    // must use the same public path the provider saw rather than swapping it.
    // Start from a clean slate so a stale publish cannot mask a failed one.
    File::deleteDirectory(public_path('vendor/tardis'));

    config()->set('auth.providers.users.model', InstallTestUser::class);

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('users');
    File::deleteDirectory(public_path('vendor/tardis'));
});

test('install migrates and seeds permissions, with nothing to publish', function () {
    $this->artisan('tardis:install', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('tardis_permissions'))->toBeTrue()
        ->and(Permission::query()->count())->toBeGreaterThan(0)
        ->and(Role::where('slug', 'super-admin')->exists())->toBeTrue()
        ->and(File::exists(public_path('vendor/tardis/assets/app.css')))->toBeFalse();
});

test('install creates an administrator when an email is given', function () {
    $this->artisan('tardis:install', ['--force' => true, '--email' => 'admin@example.com'])->assertSuccessful();

    expect(InstallTestUser::where('email', 'admin@example.com')->exists())->toBeTrue();
});

test('install skips administrator creation without an email', function () {
    $this->artisan('tardis:install', ['--force' => true])
        ->expectsOutputToContain('skipping administrator creation')
        ->assertSuccessful();
});

test('install is idempotent', function () {
    $this->artisan('tardis:install', ['--force' => true])->assertSuccessful();
    $this->artisan('tardis:install', ['--force' => true])->assertSuccessful();

    expect(Permission::query()->count())->toBeGreaterThan(0);
});

test('install runs without interaction', function () {
    $this->artisan('tardis:install', ['--no-interaction' => true])->assertSuccessful();
});

/**
 * Livewire compiles the package's SFC/MFC components into the view cache and
 * renews them by file time. Composer gives a new release the archive's file
 * times, which can be older than the cached copy, so after an update the old
 * component could keep being served. Install clears the compiled views.
 */
test('install clears compiled views so updated components are recompiled', function () {
    $stale = rtrim((string) config('view.compiled'), '/').'/livewire/classes/stale-component.php';
    File::ensureDirectoryExists(dirname($stale));
    File::put($stale, '<?php // compiled by an older release');

    $this->artisan('tardis:install', ['--force' => true])->assertSuccessful();

    expect(is_file($stale))->toBeFalse();
});

test('the asset directives reference a stable container key, not a class name', function () {
    $compiled = Blade::compileString("@tardisStyles\n@tardisScripts('auth')");

    expect($compiled)->toContain("app('tardis.assets')->styles('admin')")
        ->toContain("app('tardis.assets')->scripts('auth')")
        ->not->toContain('Manager\\\\AssetManager');
});
