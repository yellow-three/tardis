<?php

namespace Tardis\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Models\Permission;
use Tardis\Models\Role;

class TardisAuthorizationPlugin implements AuthorizationPlugin
{
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

        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($ability);
        }

        return false;
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        if (! $this->can($ability, $model)) {
            abort(403, 'Unauthorized.');
        }
    }

    public static function forBread(string $slug): void
    {
        Permission::forBread($slug);
    }
}
