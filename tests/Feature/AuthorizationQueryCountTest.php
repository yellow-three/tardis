<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tardis\Auth\RoleAuthorizationPlugin;
use Tardis\Database\Seeders\PermissionSeeder;
use Tardis\Models\Permission;
use Tardis\Models\Role;
use Tardis\Plugins\PluginManager;

class QueryCountTestUser extends Authenticatable
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

    config()->set('auth.providers.users.model', QueryCountTestUser::class);
    $this->artisan('migrate');
    $this->seed(PermissionSeeder::class);

    $manager = app(PluginManager::class);
    $manager->register('tardis-authorization', RoleAuthorizationPlugin::class);
    $manager->enableByDefault('tardis-authorization');
});

/**
 * The sidebar asks about every menu item and each screen asks again; one
 * question used to be one or two queries, 66 of them on a dashboard request.
 */
test('a page request reads the user permissions once', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $role->permissions()->sync(Permission::pluck('id'));
    $user = QueryCountTestUser::create(['name' => 'Ed']);
    DB::table('tardis_role_user')->insert(['role_id' => $role->id, 'user_id' => $user->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)->get(route('tardis.dashboard'))->assertOk();

    $permissionQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'tardis_role_user'));

    expect($permissionQueries->count())->toBeLessThanOrEqual(2);
});

test('a role change is seen on the next request', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $role->permissions()->sync(Permission::where('slug', 'access admin')->pluck('id'));
    $user = QueryCountTestUser::create(['name' => 'Ed']);

    $this->actingAs($user)->get(route('tardis.dashboard'))->assertForbidden();

    DB::table('tardis_role_user')->insert(['role_id' => $role->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('tardis.search'))->assertOk();
});
