<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Auth\TardisAuthorizationPlugin;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Database\Seeders\PermissionSeeder;
use Tardis\Manager\PluginManager;
use Tardis\Models\Permission;
use Tardis\Models\Role;
use Tardis\TardisServiceProvider;

class AccessTestUser extends Authenticatable
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

    config()->set('auth.providers.users.model', AccessTestUser::class);
    $this->artisan('migrate');
});

function enableTardisAuthorization(): void
{
    $manager = app(PluginManager::class);
    $manager->register('tardis-authorization', TardisAuthorizationPlugin::class);
    $manager->enableByDefault('tardis-authorization');
}

function accessUser(string $email = 'ada@example.test'): AccessTestUser
{
    return AccessTestUser::create(['name' => 'Ada', 'email' => $email, 'password' => 'x']);
}

test('the authorization plugin is registered and enabled by default', function () {
    config()->set('tardis.authorization.enabled', true);
    app()->forgetInstance(PluginManager::class);
    app()->singleton(PluginManager::class);

    $provider = new TardisServiceProvider(app());
    (new ReflectionMethod($provider, 'registerDefaultAuthorization'))->invoke($provider);

    expect(app(PluginManager::class)->isEnabled('tardis-authorization'))->toBeTrue()
        ->and(app(PluginManager::class)->get('tardis-authorization'))->toBeInstanceOf(AuthorizationPlugin::class);
});

test('authorization can be switched off explicitly in the config', function () {
    config()->set('tardis.authorization.enabled', false);
    app()->forgetInstance(PluginManager::class);
    app()->singleton(PluginManager::class);

    $provider = new TardisServiceProvider(app());
    (new ReflectionMethod($provider, 'registerDefaultAuthorization'))->invoke($provider);

    expect(app(PluginManager::class)->isEnabled('tardis-authorization'))->toBeFalse();
});

test('a logged in user without any role cannot open the panel', function () {
    enableTardisAuthorization();

    $this->actingAs(accessUser())->get('/admin/dashboard')->assertForbidden();
});

test('a user holding access admin through a role can open the panel', function () {
    enableTardisAuthorization();
    $this->seed(PermissionSeeder::class);

    $user = accessUser();
    $role = Role::create(['name' => 'Staff', 'slug' => 'staff']);
    $role->permissions()->attach(Permission::where('slug', Abilities::ACCESS)->value('id'));
    DB::table('tardis_role_user')->insert(['role_id' => $role->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get('/admin/dashboard')->assertOk();
});

test('a super admin can open the panel', function () {
    enableTardisAuthorization();
    $this->seed(PermissionSeeder::class);

    $user = accessUser();
    DB::table('tardis_role_user')->insert([
        'role_id' => Role::where('slug', 'super-admin')->value('id'),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)->get('/admin/dashboard')->assertOk();
});

test('a guest is still sent to the login page rather than refused', function () {
    enableTardisAuthorization();

    $this->get('/admin/dashboard')->assertRedirect(route('tardis.login'));
});

test('generic abilities follow the authorization plugin and fail open without one', function () {
    $guard = new BreadAuthorization(new PluginManager);

    expect($guard->allowsAbility(Abilities::SETTINGS))->toBeTrue();

    $this->actingAs(accessUser());
    enableTardisAuthorization();

    $guard = new BreadAuthorization(app(PluginManager::class));

    expect($guard->allowsAbility(Abilities::SETTINGS))->toBeFalse()
        ->and(fn () => $guard->authorizeAbility(Abilities::SETTINGS))->toThrow(HttpException::class);
});

test('tardis:admin grants an existing user the super admin role', function () {
    $user = accessUser('boss@example.test');

    $this->artisan('tardis:admin', ['email' => 'boss@example.test'])->assertSuccessful();

    expect(DB::table('tardis_role_user')
        ->where('user_id', $user->id)
        ->where('role_id', Role::where('slug', 'super-admin')->value('id'))
        ->exists())->toBeTrue()
        ->and(Permission::where('slug', Abilities::ACCESS)->exists())->toBeTrue();
});

test('tardis:admin refuses an unknown user unless asked to create one', function () {
    $this->artisan('tardis:admin', ['email' => 'nobody@example.test'])->assertFailed();

    expect(AccessTestUser::where('email', 'nobody@example.test')->exists())->toBeFalse();

    $this->artisan('tardis:admin', ['email' => 'new@example.test', '--create' => true, '--password' => 'secret-pass-1'])
        ->assertSuccessful();

    $created = AccessTestUser::where('email', 'new@example.test')->first();

    expect($created)->not->toBeNull()
        ->and(Hash::check('secret-pass-1', $created->password))->toBeTrue()
        ->and(DB::table('tardis_role_user')->where('user_id', $created->id)->exists())->toBeTrue();
});

test('tardis:admin is idempotent', function () {
    accessUser('boss@example.test');

    $this->artisan('tardis:admin', ['email' => 'boss@example.test'])->assertSuccessful();
    $this->artisan('tardis:admin', ['email' => 'boss@example.test'])->assertSuccessful();

    expect(DB::table('tardis_role_user')->count())->toBe(1);
});
