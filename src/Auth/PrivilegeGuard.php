<?php

declare(strict_types=1);

namespace Tardis\Auth;

use Illuminate\Support\Facades\DB;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Plugins\PluginManager;
use Tardis\Support\ModelResolver;

/**
 * Keeps the Users and Roles screens from being a way up.
 *
 * "manage users" and "manage roles" let someone hand out access, and without a
 * limit that is the same as holding every ability: give yourself the
 * super-admin role, or add every permission to a role you hold. The rule here
 * is the usual one: nobody hands out more than they have, and only a super
 * administrator hands out the super-administrator role.
 *
 * It follows BreadAuthorization: with no authorization plugin, or a host that
 * answers permissions itself (Spatie's hasPermissionTo), the Tardis roles grant
 * nothing and there is nothing to guard.
 */
class PrivilegeGuard
{
    public function __construct(protected PluginManager $plugins) {}

    /**
     * @return list<int>
     */
    public function superAdminRoleIds(): array
    {
        $slugs = array_values(array_filter(array_map('strval', (array) config('tardis.authorization.super_admin_roles', []))));

        if ($slugs === []) {
            return [];
        }

        return array_map('intval', ModelResolver::role()::query()->whereIn('slug', $slugs)->pluck('id')->all());
    }

    public function isSuperAdmin(mixed $user): bool
    {
        if ($user === null || $user->getKey() === null) {
            return false;
        }

        $ids = $this->superAdminRoleIds();

        return $ids !== [] && DB::table('tardis_role_user')
            ->where('user_id', $user->getKey())
            ->whereIn('role_id', $ids)
            ->exists();
    }

    /**
     * May the user change a role's permissions from $before to $after? Only
     * the permissions being added are judged, so removing one, or editing a
     * role that already holds more than the user, stays possible.
     *
     * @param  array<int, int|string>  $before
     * @param  array<int, int|string>  $after
     */
    public function mayGrantPermissions(mixed $user, array $before, array $after): bool
    {
        if (! $this->enforced($user) || $this->isSuperAdmin($user)) {
            return true;
        }

        $added = array_diff($this->ids($after), $this->ids($before));

        return array_diff($added, $this->heldPermissionIds($user)) === [];
    }

    /**
     * Why the user may not change someone's roles from $before to $after:
     * "super_admin", "beyond_own", or null when they may.
     *
     * Only the roles that change are judged: a user who cannot hand out
     * super-admin can still edit the other roles of someone who holds it.
     *
     * @param  array<int, int|string>  $before
     * @param  array<int, int|string>  $after
     */
    public function roleChangeDenial(mixed $user, array $before, array $after): ?string
    {
        if (! $this->enforced($user) || $this->isSuperAdmin($user)) {
            return null;
        }

        $before = $this->ids($before);
        $after = $this->ids($after);
        $changed = array_merge(array_diff($before, $after), array_diff($after, $before));

        if (array_intersect($changed, $this->superAdminRoleIds()) !== []) {
            return 'super_admin';
        }

        $added = array_diff($after, $before);

        if ($added === []) {
            return null;
        }

        $granted = DB::table('tardis_permission_role')->whereIn('role_id', $added)->pluck('permission_id')->all();

        return array_diff($this->ids($granted), $this->heldPermissionIds($user)) === [] ? null : 'beyond_own';
    }

    /**
     * @return list<int>
     */
    protected function heldPermissionIds(mixed $user): array
    {
        return $this->ids(DB::table('tardis_role_user as ru')
            ->join('tardis_permission_role as pr', 'pr.role_id', '=', 'ru.role_id')
            ->where('ru.user_id', $user->getKey())
            ->pluck('pr.permission_id')
            ->all());
    }

    protected function enforced(mixed $user): bool
    {
        if ($this->plugins->enabledWith(AuthorizationPlugin::class)->isEmpty()) {
            return false;
        }

        return ! ($user !== null && method_exists($user, 'hasPermissionTo'));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    protected function ids(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
