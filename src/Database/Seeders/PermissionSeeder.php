<?php

declare(strict_types=1);

namespace Tardis\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Tardis\Models\Permission;
use Tardis\Models\Role;

/**
 * Provisions the permissions and roles that TARDIS' own authorization plugin
 * reads.
 *
 * The plugin resolves abilities against the `tardis_*` tables, so seeding
 * anything else leaves a host with rows its own guard never consults. The
 * super admin role is created natively for the same reason: without it every
 * user matches no role and the bypass configured in
 * `tardis.authorization.super_admin_roles` can never apply.
 */
class PermissionSeeder extends Seeder
{
    /** @var array<int, string> */
    protected array $adminPermissions = [
        'browse admin',
        'access admin',
        'manage users',
        'manage settings',
        'manage plugins',
        'manage menus',
    ];

    /** @var array<int, string> */
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
        $this->createPermissions($this->adminPermissions, 'admin');
        $this->createPermissions($this->mediaPermissions, 'media');
        $this->ensureSuperAdminRoles();
    }

    /**
     * Expose the BREAD abilities of a page through the same helper the
     * authorization plugin uses, so a seeded page and a visited page never
     * disagree on the slug an ability is stored under.
     */
    public function syncForBread(string $slug): void
    {
        Permission::forBread($slug);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    protected function createPermissions(array $abilities, string $group): void
    {
        foreach ($abilities as $ability) {
            // The slug is the ability string itself, because that is the exact
            // value BreadAuthorization::ability() builds and the plugin
            // compares the permission slug against.
            Permission::firstOrCreate(
                ['slug' => $ability],
                ['name' => $ability, 'group' => $group],
            );
        }
    }

    /**
     * Create every role listed in the super admin configuration and give it
     * all permissions, so a freshly seeded install is actually administrable.
     */
    protected function ensureSuperAdminRoles(): void
    {
        $slugs = array_values(array_filter(array_map(
            'strval',
            (array) config('tardis.authorization.super_admin_roles', ['super-admin']),
        )));

        if ($slugs === []) {
            return;
        }

        $roles = Role::whereIn('slug', $slugs)->get()->keyBy('slug');

        foreach ($slugs as $slug) {
            $role = $roles->get($slug) ?? Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => Str::headline($slug)],
            );

            $role->permissions()->sync(Permission::query()->pluck('id'));
        }
    }
}
