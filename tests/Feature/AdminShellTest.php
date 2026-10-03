<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tardis\Manager\MenuManager;

class ShellTestUser extends Authenticatable
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

    $this->actingAs(ShellTestUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'x']));
});

test('every fixed admin page renders a full document without any host application routes', function (string $url) {
    // A freshly installed host has no `profile.edit` and no `logout` route; the
    // shell must not depend on either.
    $response = $this->get($url);

    expect($response->exception?->getMessage())->toBeNull();
    $response->assertOk()->assertSee('<html', false);
})->with([
    '/admin/dashboard',
    '/admin/settings',
    '/admin/plugins',
    '/admin/media',
    '/admin/bread',
    '/admin/database',
    '/admin/roles',
    '/admin/permissions',
    '/admin/activity-log',
    '/admin/search',
    '/admin/ui-components',
]);

test('the user menu offers logout through the package route', function () {
    $this->get('/admin/dashboard')
        ->assertSee('action="'.route('tardis.logout').'"', false)
        ->assertDontSee('Route [');
});

test('the profile link only appears when the host application has a profile route', function () {
    $this->get('/admin/dashboard')->assertDontSee('/profile', false);

    Route::get('/profile', fn () => 'profile')->name('profile.edit');
    app('router')->getRoutes()->refreshNameLookups();

    // MenuManager is a singleton and collects once per request; start clean.
    app()->forgetInstance(MenuManager::class);

    $this->get('/admin/dashboard')->assertSee('href="'.route('profile.edit').'"', false);
});
