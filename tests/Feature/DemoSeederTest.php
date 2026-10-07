<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Tardis\Database\Seeders\DemoSeeder;
use Tardis\Models\Role;

class DemoSeederTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::dropIfExists('users');
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    $this->artisan('migrate');
});

test('demo seeder is no-op when not enabled', function () {
    config(['tardis.system.demo.enabled' => false]);
    config(['app.env' => 'local']);

    $this->seed(DemoSeeder::class);

    expect(DemoSeederTestUser::count())->toBe(0);
    expect(Role::where('slug', 'demo-admin')->exists())->toBeFalse();
});

test('demo seeder is no-op in non-local environment even if enabled', function () {
    config(['tardis.system.demo.enabled' => true]);
    $this->app['env'] = 'production';
    $this->app->detectEnvironment(fn () => 'production');

    // Called directly rather than via $this->seed(), which accepts no
    // parameters and so cannot pass --force. db:seed prompts for
    // confirmation in production (SeedCommand::confirmToProceed), which would
    // hit the mocked console output; --force bypasses it so the guard under
    // test is DemoSeeder's environment check rather than the command's.
    $this->artisan('db:seed', [
        '--class' => DemoSeeder::class,
        '--force' => true,
    ]);

    expect(DemoSeederTestUser::count())->toBe(0);
    expect(Role::where('slug', 'demo-admin')->exists())->toBeFalse();
});

test('demo seeder seeds demo data when enabled in local environment', function () {
    config(['tardis.system.demo.enabled' => true]);
    config(['app.env' => 'local']);
    config(['auth.providers.users.model' => DemoSeederTestUser::class]);

    $this->seed(DemoSeeder::class);

    expect(DemoSeederTestUser::where('email', 'demo-admin@example.test')->exists())->toBeTrue();
    expect(DemoSeederTestUser::where('email', 'demo-editor@example.test')->exists())->toBeTrue();
    expect(Role::where('slug', 'demo-admin')->exists())->toBeTrue();
    expect(Role::where('slug', 'demo-editor')->exists())->toBeTrue();
});

test('demo seeder is idempotent and safe to run twice', function () {
    config(['tardis.system.demo.enabled' => true]);
    config(['app.env' => 'local']);
    config(['auth.providers.users.model' => DemoSeederTestUser::class]);

    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(DemoSeederTestUser::where('email', 'demo-admin@example.test')->count())->toBe(1);
    expect(DemoSeederTestUser::where('email', 'demo-editor@example.test')->count())->toBe(1);
    expect(Role::where('slug', 'demo-admin')->count())->toBe(1);
    expect(Role::where('slug', 'demo-editor')->count())->toBe(1);
});
