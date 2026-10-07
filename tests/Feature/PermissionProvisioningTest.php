<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tardis\Auth\Abilities;
use Tardis\Database\Seeders\PermissionSeeder;
use Tardis\Models\Permission;

/**
 * Mirrors the package's own permission migration so the tests assert against
 * the schema a host actually gets, not a hand-waved approximation of it.
 */
function createNativePermissionSchema(): void
{
    Schema::create('tardis_permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('group')->nullable();
        $table->timestamps();
    });

    Schema::create('tardis_roles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->timestamps();
    });

    Schema::create('tardis_permission_role', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    Schema::create('tardis_role_user', function (Blueprint $table) {
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('user_id');
        $table->primary(['role_id', 'user_id']);
    });
}

beforeEach(function () {
    createNativePermissionSchema();
});

test('forBread stores its abilities in the tardis permission table', function () {
    Permission::forBread('posts');

    expect(DB::table('tardis_permissions')->count())->toBe(5)
        ->and(DB::table('tardis_permissions')->where('slug', 'browse posts')->exists())->toBeTrue();
});

test('forBread does not duplicate abilities when run twice', function () {
    Permission::forBread('posts');
    Permission::forBread('posts');

    expect(DB::table('tardis_permissions')->count())->toBe(5);
});

test('the seeder provisions the package permission table', function () {
    (new PermissionSeeder)->run();

    expect(DB::table('tardis_permissions')->where('slug', 'browse admin')->exists())->toBeTrue()
        ->and(DB::table('tardis_permissions')->where('slug', 'upload media')->exists())->toBeTrue();
});

test('the seeder grants the super admin role every permission it can reach', function () {
    (new PermissionSeeder)->run();

    $role = DB::table('tardis_roles')->where('slug', 'super-admin')->first();

    expect($role)->not->toBeNull();

    $granted = DB::table('tardis_permission_role')->where('role_id', $role->id)->count();
    $total = DB::table('tardis_permissions')->count();

    expect($granted)->toBe($total)
        ->and($total)->toBeGreaterThan(0);
});

test('the seeder provisions every configured super admin role', function () {
    config()->set('tardis.authorization.super_admin_roles', ['super-admin', 'root']);

    (new PermissionSeeder)->run();

    expect(DB::table('tardis_roles')->pluck('slug')->all())
        ->toEqualCanonicalizing(['super-admin', 'root']);

    $total = DB::table('tardis_permissions')->count();

    foreach (DB::table('tardis_roles')->pluck('id') as $roleId) {
        expect(DB::table('tardis_permission_role')->where('role_id', $roleId)->count())->toBe($total);
    }
});

test('the seeder is idempotent', function () {
    (new PermissionSeeder)->run();
    (new PermissionSeeder)->run();

    $expected = count(Abilities::admin()) + count(Abilities::media());

    expect(DB::table('tardis_permissions')->count())->toBe($expected)
        ->and(DB::table('tardis_roles')->count())->toBe(1)
        ->and(DB::table('tardis_permission_role')->count())->toBe($expected);
});

test('the seeder provisions the system abilities', function () {
    (new PermissionSeeder)->run();

    expect(DB::table('tardis_permissions')->where('slug', Abilities::SYSTEM)->exists())->toBeTrue()
        ->and(DB::table('tardis_permissions')->where('slug', Abilities::LOGS)->exists())->toBeTrue()
        ->and(DB::table('tardis_permissions')->where('slug', Abilities::COMMANDS)->exists())->toBeTrue();
});

test('the system abilities are part of the admin list the seeder walks', function () {
    // Keeping them out of admin() would create the permissions for nobody to
    // hold: the super-admin role is granted exactly what these lists return.
    expect(Abilities::admin())
        ->toContain(Abilities::SYSTEM)
        ->toContain(Abilities::LOGS)
        ->toContain(Abilities::COMMANDS);
});

test('the seeder exposes bread permissions through the native model', function () {
    (new PermissionSeeder)->syncForBread('posts');

    $slugs = DB::table('tardis_permissions')->pluck('slug')->all();

    expect($slugs)->toContain('browse posts')
        ->toContain('delete posts')
        ->toContain('add posts');
});
