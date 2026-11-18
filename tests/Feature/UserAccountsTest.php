<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Auth\Abilities;
use Tardis\Auth\RoleAuthorizationPlugin;
use Tardis\Database\Seeders\PermissionSeeder;
use Tardis\Models\Permission;
use Tardis\Models\Role;
use Tardis\Plugins\PluginManager;

class AccountsTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
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

    config()->set('auth.providers.users.model', AccountsTestUser::class);
    $this->artisan('migrate');
    $this->seed(PermissionSeeder::class);

    $manager = app(PluginManager::class);
    $manager->register(RoleAuthorizationPlugin::KEY, RoleAuthorizationPlugin::class);
    $manager->enableByDefault(RoleAuthorizationPlugin::KEY);

    $this->managerRole = Role::create(['name' => 'Managers', 'slug' => 'managers']);
    $this->managerRole->permissions()->sync(Permission::whereIn('slug', [Abilities::ACCESS, Abilities::USERS])->pluck('id'));
    $this->manager = AccountsTestUser::create(['name' => 'Mia', 'email' => 'mia@example.test', 'password' => 'mia-pass-0001']);
    DB::table('tardis_role_user')->insert(['role_id' => $this->managerRole->id, 'user_id' => $this->manager->id]);

    $this->superRole = Role::where('slug', 'super-admin')->firstOrFail();
    $this->super = AccountsTestUser::create(['name' => 'Sam', 'email' => 'sam@example.test', 'password' => 'sam-pass-0002']);
    DB::table('tardis_role_user')->insert(['role_id' => $this->superRole->id, 'user_id' => $this->super->id]);
});

function accountRoles(int $userId): array
{
    return DB::table('tardis_role_user')->where('user_id', $userId)->pluck('role_id')->map(fn ($id) => (int) $id)->all();
}

test('a user manager creates an account with a hashed password and roles', function () {
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')
        ->call('openCreate')
        ->set('userForm.name', 'Neo')
        ->set('userForm.email', 'neo@example.test')
        ->set('userForm.password', 'long-enough-1')
        ->set('userForm.password_confirmation', 'long-enough-1')
        ->set('createRoleIds', [$this->managerRole->id])
        ->call('saveUser')
        ->assertHasNoErrors()
        ->assertSet('showUserModal', false);

    $neo = AccountsTestUser::where('email', 'neo@example.test')->firstOrFail();

    expect(Hash::check('long-enough-1', $neo->password))->toBeTrue()
        ->and(accountRoles($neo->id))->toBe([$this->managerRole->id]);
});

test('creating validates the email, the password and the roles', function () {
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')
        ->call('openCreate')
        ->set('userForm.name', 'Dup')
        ->set('userForm.email', 'mia@example.test')
        ->set('userForm.password', 'short')
        ->set('userForm.password_confirmation', 'other')
        ->call('saveUser')
        ->assertHasErrors(['userForm.email', 'userForm.password']);

    Livewire::test('tardis::pages.users')
        ->call('openCreate')
        ->set('userForm.name', 'Up')
        ->set('userForm.email', 'up@example.test')
        ->set('userForm.password', 'long-enough-1')
        ->set('userForm.password_confirmation', 'long-enough-1')
        ->set('createRoleIds', [$this->superRole->id])
        ->call('saveUser')
        ->assertSet('error', __('tardis::users.cannot_change_super_admin'));

    expect(AccountsTestUser::where('email', 'up@example.test')->exists())->toBeFalse();
});

test('editing keeps the password unless a new one is given', function () {
    $other = AccountsTestUser::create(['name' => 'Otto', 'email' => 'otto@example.test', 'password' => 'original-pass']);
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')
        ->call('openDetails', $other->id)
        ->assertSet('userForm.email', 'otto@example.test')
        ->set('userForm.name', 'Otto Renamed')
        ->call('saveUser')
        ->assertHasNoErrors();

    expect($other->fresh()->name)->toBe('Otto Renamed')
        ->and(Hash::check('original-pass', $other->fresh()->password))->toBeTrue();
});

test('a user manager cannot take over a super administrator', function () {
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')->call('openDetails', $this->super->id)->assertForbidden();
    Livewire::test('tardis::pages.users')->call('deleteUser', $this->super->id)->assertForbidden();

    expect(AccountsTestUser::whereKey($this->super->id)->exists())->toBeTrue();
});

test('a user manager cannot manage someone who holds more than they do', function () {
    $ops = Role::create(['name' => 'Ops', 'slug' => 'ops']);
    $ops->permissions()->sync(Permission::where('slug', Abilities::COMMANDS)->pluck('id'));
    $opsUser = AccountsTestUser::create(['name' => 'Ola', 'email' => 'ola@example.test']);
    DB::table('tardis_role_user')->insert(['role_id' => $ops->id, 'user_id' => $opsUser->id]);

    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.users')->call('openDetails', $opsUser->id)->assertForbidden();
});

test('deleting removes the account and its roles, but not yourself or the last super administrator', function () {
    $other = AccountsTestUser::create(['name' => 'Otto', 'email' => 'otto@example.test']);
    DB::table('tardis_role_user')->insert(['role_id' => $this->managerRole->id, 'user_id' => $other->id]);

    $this->actingAs($this->super);

    Livewire::test('tardis::pages.users')
        ->call('deleteUser', $other->id)
        ->call('deleteUser', $this->super->id)
        ->assertSet('error', __('tardis::users.cannot_delete_self'));

    expect(AccountsTestUser::whereKey($other->id)->exists())->toBeFalse()
        ->and(accountRoles($other->id))->toBe([])
        ->and(AccountsTestUser::whereKey($this->super->id)->exists())->toBeTrue();
});

test('the users page works with other column names and without a name column', function () {
    Schema::table('users', fn ($table) => $table->dropColumn('name'));
    config()->set('tardis.users.columns.email', 'email');
    $this->actingAs($this->super);

    Livewire::test('tardis::pages.users')
        ->set('search', 'mia')
        ->assertSee('mia@example.test')
        ->assertDontSee('sam@example.test')
        ->call('openCreate')
        ->assertDontSee('wire:model="userForm.name"', false);
});

test('the profile page changes the name freely and the password with the current one', function () {
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.profile')
        ->assertSet('userForm.email', 'mia@example.test')
        ->set('userForm.name', 'Mia Wallace')
        ->call('save')
        ->assertHasNoErrors()
        ->set('userForm.password', 'brand-new-pass')
        ->set('userForm.password_confirmation', 'brand-new-pass')
        ->set('userForm.current_password', 'wrong')
        ->call('save')
        ->assertHasErrors('userForm.current_password')
        ->set('userForm.current_password', 'mia-pass-0001')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $this->manager->fresh();

    expect($fresh->name)->toBe('Mia Wallace')
        ->and(Hash::check('brand-new-pass', $fresh->password))->toBeTrue();
});

test('changing the email on the profile needs the current password', function () {
    $this->actingAs($this->manager);

    Livewire::test('tardis::pages.profile')
        ->set('userForm.email', 'mia.new@example.test')
        ->call('save')
        ->assertHasErrors('userForm.current_password');

    expect($this->manager->fresh()->email)->toBe('mia@example.test');
});
