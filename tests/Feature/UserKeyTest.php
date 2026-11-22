<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tardis\Bread\ActivityLogger;
use Tardis\Diagnostics\DoctorReport;
use Tardis\Models\ActivityLog;
use Tardis\Models\Permission;
use Tardis\Models\Role;
use Tardis\Support\UserKey;

class UuidKeyUser extends Authenticatable
{
    use HasUuids;

    protected $table = 'users';

    protected $guarded = [];
}

class UlidKeyUser extends Authenticatable
{
    use HasUlids;

    protected $table = 'users';
}

class IntKeyUser extends Authenticatable
{
    protected $table = 'users';
}

function uuidUsersTable(): void
{
    Schema::dropIfExists('users');
    Schema::create('users', function ($table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
}

test('auto reads the key type off the user model, config overrides it', function () {
    config()->set('auth.providers.users.model', UuidKeyUser::class);
    expect(UserKey::type())->toBe('uuid');

    config()->set('auth.providers.users.model', UlidKeyUser::class);
    expect(UserKey::type())->toBe('ulid');

    config()->set('auth.providers.users.model', IntKeyUser::class);
    expect(UserKey::type())->toBe('int');

    config()->set('tardis.users.key_type', 'string');
    expect(UserKey::type())->toBe('string');

    config()->set('tardis.users.key_type', 'nonsense');
    expect(UserKey::type())->toBe('int');
});

test('an integer-keyed app keeps bigint columns', function () {
    config()->set('auth.providers.users.model', IntKeyUser::class);
    $this->artisan('migrate');

    foreach (UserKey::COLUMNS as $table => [$column]) {
        expect(UserKey::matches($table, $column, 'int'))->toBeTrue("{$table}.{$column}");
    }
});

test('a uuid user table gets uuid columns, holds roles and is logged', function () {
    uuidUsersTable();
    config()->set('auth.providers.users.model', UuidKeyUser::class);
    $this->artisan('migrate');

    foreach (UserKey::COLUMNS as $table => [$column]) {
        expect(UserKey::matches($table, $column, 'uuid'))->toBeTrue("{$table}.{$column}");
    }

    $user = UuidKeyUser::create(['name' => 'Ada']);
    $role = Role::create(['name' => 'Editors', 'slug' => 'editors']);
    $role->users()->attach($user->getKey());

    $this->actingAs($user);
    app(ActivityLogger::class)->audit('tardis.test', 'done');

    expect(DB::table('tardis_role_user')->value('user_id'))->toBe($user->getKey())
        ->and(ActivityLog::query()->latest('id')->value('user_id'))->toBe($user->getKey())
        ->and(collect(DoctorReport::run()->checks)->firstWhere('name', 'User key columns')->isOk())->toBeTrue();
});

test('the command leaves rows of the old type alone unless forced', function () {
    config()->set('auth.providers.users.model', IntKeyUser::class);
    $this->artisan('migrate');
    DB::table('tardis_activity_logs')->insert(['user_id' => 7, 'model_type' => 'x', 'model_id' => 1, 'action' => 'y']);

    uuidUsersTable();
    config()->set('auth.providers.users.model', UuidKeyUser::class);

    expect(collect(DoctorReport::run()->checks)->firstWhere('name', 'User key columns')->isWarning())->toBeTrue();

    expect(Artisan::call('tardis:user-keys'))->toBe(1)
        ->and(UserKey::matches('tardis_activity_logs', 'user_id', 'uuid'))->toBeFalse()
        ->and(UserKey::matches('tardis_role_user', 'user_id', 'uuid'))->toBeTrue();

    expect(Artisan::call('tardis:user-keys', ['--force' => true]))->toBe(0)
        ->and(UserKey::matches('tardis_activity_logs', 'user_id', 'uuid'))->toBeTrue()
        ->and(DB::table('tardis_activity_logs')->count())->toBe(0);
});

class TeamRole extends Role
{
    protected $table = 'tardis_roles';
}

class TeamPermission extends Permission
{
    protected $table = 'tardis_permissions';
}

test('custom role and permission models use the pivot columns by name', function () {
    config()->set('tardis.models.role', TeamRole::class);
    config()->set('tardis.models.permission', TeamPermission::class);
    $this->artisan('migrate');

    $role = TeamRole::create(['name' => 'Team', 'slug' => 'team']);
    $permission = TeamPermission::create(['name' => 'Browse posts', 'slug' => 'browse posts']);
    $role->permissions()->attach($permission->getKey());

    expect($role->permissions()->pluck('slug')->all())->toBe(['browse posts'])
        ->and($permission->roles()->pluck('slug')->all())->toBe(['team']);
});
