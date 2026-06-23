<?php

namespace Tardis\Permissions;

use Illuminate\Support\Facades\Gate;
use Tardis\Classes\MenuItem;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Contracts\Plugins\Features\Provider\MenuItems;

class PermissionsPlugin implements AuthorizationPlugin, MenuItems
{
    public function name(): string
    {
        return 'tardis-permissions';
    }

    public function description(): string
    {
        return 'Role-based access control with Spatie permissions - manage roles, permissions, and user assignments';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        if ($user = auth()->user()) {
            return $user->can($ability, $model);
        }

        return false;
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        Gate::authorize($ability, $model);
    }

    public function provideMenuItems(): array
    {
        return [
            (new MenuItem('Permissions', 'heroicon-o-lock-closed'))
                ->route('tardis.permissions')
                ->group('System')
                ->order(35)
                ->permission('browse admin'),
        ];
    }
}
