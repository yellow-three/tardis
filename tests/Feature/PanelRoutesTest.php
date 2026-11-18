<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Auth\RoleAuthorizationPlugin;
use Tardis\Bread\ReservedSlugs;
use Tardis\Plugins\PluginManager;

class PanelRoutesTestUser extends Authenticatable
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

    config()->set('auth.providers.users.model', PanelRoutesTestUser::class);
    $this->artisan('migrate');
});

function panelUser(): PanelRoutesTestUser
{
    return PanelRoutesTestUser::create(['name' => 'Ada', 'email' => 'ada@example.test']);
}

test('a signed-in user without panel access can still sign out', function () {
    $manager = app(PluginManager::class);
    $manager->register('tardis-authorization', RoleAuthorizationPlugin::class);
    $manager->enableByDefault('tardis-authorization');

    $this->actingAs(panelUser())->get(route('tardis.dashboard'))->assertForbidden();

    $this->post(route('tardis.logout'))->assertRedirect(route('tardis.login'));

    $this->assertGuest();
});

test('signing out goes through the authentication plugin', function () {
    $this->actingAs(panelUser())->post(route('tardis.logout'))->assertRedirect(route('tardis.login'));

    $this->assertGuest();
});

test('the panel root leads to the dashboard', function () {
    $this->get('/admin')->assertRedirect(route('tardis.dashboard'));
});

test('a signed-in user is sent from the login page to the dashboard', function () {
    $this->actingAs(panelUser());

    Livewire::test('tardis::pages.login')->assertRedirect(route('tardis.dashboard'));
});

test('a media id that is not a number is a 404, not a server error', function () {
    $this->actingAs(panelUser())->get('/admin/media/abc/edit')->assertNotFound();
});

test('every first path segment of a fixed screen is reserved for BREAD slugs', function () {
    $segments = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'tardis.')
            && ! preg_match('/^tardis\.bread\.(index|add|edit\.item|read)(\.|$)/', (string) $route->getName()))
        ->map(fn ($route) => explode('/', trim(substr($route->uri(), strlen('admin')), '/'))[0] ?? '')
        ->filter(fn ($segment) => $segment !== '' && ! str_starts_with($segment, '{'))
        ->unique()
        ->values();

    expect($segments)->not->toBeEmpty();

    foreach ($segments as $segment) {
        expect(ReservedSlugs::has($segment))->toBeTrue("[{$segment}] is not reserved");
    }
});
