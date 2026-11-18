<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Contracts\Plugins\GenericPlugin;
use Tardis\Models\ActivityLog;
use Tardis\Models\Permission;
use Tardis\Models\Role;
use Tardis\Plugins\PluginManager;

class AuditTestUser extends Authenticatable
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
    config()->set('auth.providers.users.model', AuditTestUser::class);
    $this->artisan('migrate');

    $this->admin = AuditTestUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => Hash::make('secret-pass')]);
});

function auditEntry(string $type, string $action): ?ActivityLog
{
    return ActivityLog::query()->where('model_type', $type)->where('action', $action)->latest('id')->first();
}

test('signing in, a failed attempt and signing out are logged with the address', function () {
    Livewire::test('tardis::pages.login')->set('email', 'ada@example.test')->set('password', 'wrong')->call('login');

    expect(auditEntry('tardis.auth', 'sign_in_failed'))
        ->not->toBeNull()
        ->new_values->toBe(['email' => 'ada@example.test'])
        ->ip_address->not->toBeNull();

    Livewire::test('tardis::pages.login')->set('email', 'ada@example.test')->set('password', 'secret-pass')->call('login');

    expect(auditEntry('tardis.auth', 'signed_in'))->user_id->toBe($this->admin->id);

    $this->actingAs($this->admin)->post(route('tardis.logout'));

    expect(auditEntry('tardis.auth', 'signed_out'))->user_id->toBe($this->admin->id);
});

test('role and user-role changes are logged', function () {
    $this->actingAs($this->admin);
    $role = Role::create(['name' => 'Editors', 'slug' => 'editors']);
    $permission = Permission::create(['name' => 'Browse posts', 'slug' => 'browse posts']);

    Livewire::test('tardis::pages.roles')
        ->call('editRole', $role->id)
        ->set('editRolePermissions', [$permission->id])
        ->call('saveRolePermissions');

    expect(auditEntry('tardis.role', 'permissions_changed'))
        ->new_values->toMatchArray(['role' => 'editors', 'added' => ['browse posts'], 'removed' => []]);

    Livewire::test('tardis::pages.users')
        ->call('openEdit', $this->admin->id)
        ->set('editRoleIds', [$role->id])
        ->call('saveRoles');

    expect(auditEntry('tardis.user_roles', 'roles_changed'))
        ->new_values->toMatchArray(['user_id' => $this->admin->id, 'added' => ['editors'], 'removed' => []]);
});

test('switching a plugin is logged', function () {
    app(PluginManager::class)->register('reports', AuditTestReportsPlugin::class);
    $this->actingAs($this->admin);

    Livewire::test('tardis::pages.plugins')->call('disable', 'reports');

    expect(auditEntry('tardis.plugin', 'disabled'))->new_values->toBe(['plugin' => 'reports']);
});

class AuditTestReportsPlugin implements GenericPlugin
{
    public function name(): string
    {
        return 'reports';
    }

    public function description(): string
    {
        return 'Reports';
    }
}
