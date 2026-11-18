<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Auth\Abilities;
use Tardis\Auth\RoleAuthorizationPlugin;
use Tardis\Database\Seeders\PermissionSeeder;
use Tardis\Models\Permission;
use Tardis\Models\Role;
use Tardis\Plugins\PluginManager;

class EscalationTestUser extends Authenticatable
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

    config()->set('auth.providers.users.model', EscalationTestUser::class);
    $this->artisan('migrate');
    $this->seed(PermissionSeeder::class);

    $manager = app(PluginManager::class);
    $manager->register('tardis-authorization', RoleAuthorizationPlugin::class);
    $manager->enableByDefault('tardis-authorization');

    // A manager may run the Users and Roles screens, and nothing else.
    $this->managerRole = Role::create(['name' => 'Managers', 'slug' => 'managers']);
    $this->managerRole->permissions()->sync(Permission::whereIn('slug', [Abilities::ACCESS, Abilities::USERS, Abilities::ROLES])->pluck('id'));

    $this->manager = EscalationTestUser::create(['name' => 'Mia', 'email' => 'mia@example.test']);
    DB::table('tardis_role_user')->insert(['role_id' => $this->managerRole->id, 'user_id' => $this->manager->id]);

    $this->superRole = Role::where('slug', 'super-admin')->firstOrFail();
    $this->super = EscalationTestUser::create(['name' => 'Sam', 'email' => 'sam@example.test']);
    DB::table('tardis_role_user')->insert(['role_id' => $this->superRole->id, 'user_id' => $this->super->id]);
});

function escalationRolesOf(EscalationTestUser $user): array
{
    return DB::table('tardis_role_user')->where('user_id', $user->id)->orderBy('role_id')->pluck('role_id')->map(fn ($id) => (int) $id)->all();
}

test('a user manager cannot make themselves a super administrator', function () {
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->manager->id)
        ->set('editRoleIds', [$this->managerRole->id, $this->superRole->id])
        ->call('saveRoles')
        ->assertSet('error', __('tardis::users.cannot_change_super_admin'));

    expect(escalationRolesOf($this->manager))->toBe([$this->managerRole->id]);
});

test('a user manager cannot take the super administrator role away either', function () {
    $other = EscalationTestUser::create(['name' => 'Otto', 'email' => 'otto@example.test']);
    DB::table('tardis_role_user')->insert(['role_id' => $this->superRole->id, 'user_id' => $other->id]);

    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $other->id)
        ->set('editRoleIds', [])
        ->call('saveRoles')
        ->assertSet('error', __('tardis::users.cannot_change_super_admin'));

    expect(escalationRolesOf($other))->toBe([$this->superRole->id]);
});

test('a user manager cannot assign a role that holds more than they do', function () {
    $ops = Role::create(['name' => 'Ops', 'slug' => 'ops']);
    $ops->permissions()->sync(Permission::where('slug', Abilities::COMMANDS)->pluck('id'));

    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->manager->id)
        ->set('editRoleIds', [$this->managerRole->id, $ops->id])
        ->call('saveRoles')
        ->assertSet('error', __('tardis::users.cannot_assign_beyond_own'));

    expect(escalationRolesOf($this->manager))->toBe([$this->managerRole->id]);
});

test('a user manager can still assign roles within what they hold', function () {
    $helpers = Role::create(['name' => 'Helpers', 'slug' => 'helpers']);
    $helpers->permissions()->sync(Permission::where('slug', Abilities::ACCESS)->pluck('id'));
    $other = EscalationTestUser::create(['name' => 'Otto', 'email' => 'otto@example.test']);

    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $other->id)
        ->set('editRoleIds', [$helpers->id])
        ->call('saveRoles')
        ->assertSet('error', null);

    expect(escalationRolesOf($other))->toBe([$helpers->id]);
});

test('a role manager cannot add permissions they do not hold to their own role', function () {
    $commands = Permission::where('slug', Abilities::COMMANDS)->value('id');
    $before = $this->managerRole->permissions()->pluck('tardis_permissions.id')->sort()->values()->all();

    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.roles')
        ->call('editRole', $this->managerRole->id)
        ->set('editRolePermissions', [...$before, $commands])
        ->call('saveRolePermissions')
        ->assertSet('error', __('tardis::roles.cannot_grant_beyond_own'));

    expect($this->managerRole->permissions()->pluck('tardis_permissions.id')->sort()->values()->all())->toBe($before);
});

test('the super administrator role cannot be deleted', function () {
    $this->actingAs($this->super);

    Livewire::test('tardis::pages.roles')
        ->call('confirmDelete', $this->superRole->id)
        ->call('deleteRole')
        ->assertSet('error', __('tardis::roles.cannot_delete_super_admin'));

    expect(Role::whereKey($this->superRole->id)->exists())->toBeTrue()
        ->and(escalationRolesOf($this->super))->toBe([$this->superRole->id]);
});

test('a super administrator can grant the super administrator role', function () {
    $this->actingAs($this->super);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->manager->id)
        ->set('editRoleIds', [$this->managerRole->id, $this->superRole->id])
        ->call('saveRoles')
        ->assertSet('error', null);

    expect(escalationRolesOf($this->manager))->toContain($this->superRole->id);
});
