<?php

namespace Tardis\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    protected array $adminPermissions = [
        'browse admin',
        'access admin',
        'manage users',
        'manage settings',
        'manage plugins',
        'manage menus',
    ];

    protected array $mediaPermissions = [
        'browse media',
        'read media',
        'upload media',
        'edit media',
        'delete media',
        'rename media',
        'move media',
    ];

    public function run(): void
    {
        $this->createAdminPermissions();
        $this->createMediaPermissions();
        $this->ensureSuperAdminRole();
    }

    public function syncForBread(string $slug): void
    {
        $actions = ['browse', 'read', 'edit', 'add', 'delete'];

        foreach ($actions as $action) {
            Permission::findOrCreate(
                "{$action} {$slug}",
                config('permission.defaults.guard', 'web')
            );
        }
    }

    protected function createAdminPermissions(): void
    {
        foreach ($this->adminPermissions as $permission) {
            Permission::findOrCreate(
                $permission,
                config('permission.defaults.guard', 'web')
            );
        }
    }

    protected function createMediaPermissions(): void
    {
        foreach ($this->mediaPermissions as $permission) {
            Permission::findOrCreate(
                $permission,
                config('permission.defaults.guard', 'web')
            );
        }
    }

    protected function ensureSuperAdminRole(): void
    {
        $roleName = config('tardis-permissions.super_admin_role', 'super-admin');

        $role = Role::findOrCreate($roleName, config('permission.defaults.guard', 'web'));

        if ($role->permissions()->count() === 0) {
            $role->givePermissionTo(Permission::all());
        }
    }
}
