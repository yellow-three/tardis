<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Database\Seeders\PermissionSeeder;
use Tardis\Models\Role;

class UsersPageTestUser extends Authenticatable
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

    config()->set('auth.providers.users.model', UsersPageTestUser::class);

    $this->artisan('migrate');
    $this->seed(PermissionSeeder::class);

    $this->ada = UsersPageTestUser::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'password' => 'x']);
    $this->bob = UsersPageTestUser::create(['name' => 'Bob Builder', 'email' => 'bob@example.test', 'password' => 'x']);

    $this->superRole = Role::where('slug', 'super-admin')->firstOrFail();
    $this->staff = Role::create(['name' => 'Staff', 'slug' => 'staff']);

    $this->actingAs($this->ada);
});

function giveRole(UsersPageTestUser $user, Role $role): void
{
    DB::table('tardis_role_user')->insert(['role_id' => $role->id, 'user_id' => $user->id]);
}

function rolesOf(UsersPageTestUser $user): array
{
    return DB::table('tardis_role_user')->where('user_id', $user->id)->orderBy('role_id')->pluck('role_id')->all();
}

test('the users page lists users with their roles and can search them', function () {
    giveRole($this->bob, $this->staff);

    Livewire::test('tardis::pages.users')
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Bob Builder')
        ->assertSee('Staff')
        ->set('search', 'bob@')
        ->assertSee('Bob Builder')
        ->assertDontSee('Ada Lovelace');
});

test('roles can be assigned to a user', function () {
    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->bob->id)
        ->set('editRoleIds', [$this->staff->id])
        ->call('saveRoles')
        ->assertHasNoErrors()
        ->assertSet('error', null);

    expect(rolesOf($this->bob))->toBe([$this->staff->id]);
});

test('editing opens with the roles the user already holds', function () {
    giveRole($this->bob, $this->staff);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->bob->id)
        ->assertSet('editRoleIds', [$this->staff->id]);
});

test('role ids that do not exist are ignored', function () {
    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->bob->id)
        ->set('editRoleIds', [$this->staff->id, 9999])
        ->call('saveRoles');

    expect(rolesOf($this->bob))->toBe([$this->staff->id]);
});

test('the last super admin cannot lose the role', function () {
    giveRole($this->ada, $this->superRole);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->ada->id)
        ->set('editRoleIds', [$this->staff->id])
        ->call('saveRoles')
        ->assertSet('error', 'At least one super administrator must remain.');

    expect(rolesOf($this->ada))->toBe([$this->superRole->id]);
});

test('a super admin can be demoted when another one remains', function () {
    giveRole($this->ada, $this->superRole);
    giveRole($this->bob, $this->superRole);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->bob->id)
        ->set('editRoleIds', [])
        ->call('saveRoles')
        ->assertSet('error', null);

    expect(rolesOf($this->bob))->toBe([]);
});

test('removing every role from a plain user works', function () {
    giveRole($this->bob, $this->staff);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->bob->id)
        ->set('editRoleIds', [])
        ->call('saveRoles');

    expect(rolesOf($this->bob))->toBe([]);
});
