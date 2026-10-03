<?php

declare(strict_types=1);

namespace Tardis\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Models\Permission;

class TardisAuthorizationPlugin implements AuthorizationPlugin
{
    /**
     * Memoised schema probe. Instance-scoped on purpose: a static cache would
     * outlive the connection in tests, where every case rebuilds its own
     * :memory: database and would read a previous case's table list.
     */
    protected ?bool $tablesExist = null;

    public function name(): string
    {
        return 'tardis-authorization';
    }

    public function description(): string
    {
        return 'Role-based access control using TARDIS custom Permission/Role models';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // A host application running Spatie's HasRoles trait answers for itself;
        // mixing its tables with TARDIS' own would only produce half-truths.
        if (method_exists($user, 'hasPermissionTo')) {
            return (bool) $user->hasPermissionTo($ability);
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->holdsTardisPermission($user, $ability);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        if (! $this->can($ability, $model)) {
            abort(403, __('tardis::shell.unauthorized'));
        }
    }

    public static function forBread(string $slug): void
    {
        Permission::forBread($slug);
    }

    /**
     * Does the user hold the ability through any of their TARDIS roles?
     *
     * The pivot tables are queried directly rather than through a relation on
     * the host application's User model, which TARDIS cannot assume exists.
     */
    protected function holdsTardisPermission(mixed $user, string $ability): bool
    {
        if (! $this->permissionTablesExist() || $user->getKey() === null) {
            return false;
        }

        return DB::table('tardis_role_user as ru')
            ->join('tardis_permission_role as pr', 'pr.role_id', '=', 'ru.role_id')
            ->join('tardis_permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('ru.user_id', $user->getKey())
            ->where('p.slug', $ability)
            ->exists();
    }

    /**
     * Roles configured to bypass every ability check.
     */
    protected function isSuperAdmin(mixed $user): bool
    {
        $roles = array_values(array_filter(
            array_map('strval', (array) config('tardis.authorization.super_admin_roles', [])),
        ));

        if ($roles === [] || ! $this->permissionTablesExist() || $user->getKey() === null) {
            return false;
        }

        return DB::table('tardis_role_user as ru')
            ->join('tardis_roles as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', $user->getKey())
            ->whereIn('r.slug', $roles)
            ->exists();
    }

    /**
     * Guard against an install that never ran the package migrations: without
     * the tables there is nothing to grant from, and failing closed would lock
     * out every request instead of simply having no roles to check.
     */
    protected function permissionTablesExist(): bool
    {
        return $this->tablesExist ??= Schema::hasTable('tardis_permissions')
            && Schema::hasTable('tardis_roles')
            && Schema::hasTable('tardis_role_user')
            && Schema::hasTable('tardis_permission_role');
    }
}
