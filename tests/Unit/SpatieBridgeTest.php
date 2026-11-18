<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tardis\Auth\RoleAuthorizationPlugin;

class SpatieBridgePermissionMissing extends RuntimeException {}

/**
 * Stands in for a host user with Spatie's HasRoles: hasPermissionTo() throws
 * for a permission that was never created, as Spatie's PermissionDoesNotExist does.
 */
class SpatieBridgeUser extends Authenticatable
{
    /** @var list<string> */
    public array $permissions = [];

    /** @var list<string> */
    public array $roles = [];

    public function getKey(): int
    {
        return 1;
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if (! in_array($permission, ['browse posts', 'edit posts'], true)) {
            throw new SpatieBridgePermissionMissing("There is no permission named `{$permission}`.");
        }

        return in_array($permission, $this->permissions, true);
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        return array_intersect((array) $roles, $this->roles) !== [];
    }
}

function spatieBridgeAs(SpatieBridgeUser $user): RoleAuthorizationPlugin
{
    auth()->setUser($user);

    return new RoleAuthorizationPlugin;
}

test('a permission the host never created is a denial, not a server error', function () {
    $plugin = spatieBridgeAs(new SpatieBridgeUser);

    expect($plugin->can('view logs'))->toBeFalse();
});

test('the host decides permissions it knows', function () {
    $user = new SpatieBridgeUser;
    $user->permissions = ['browse posts'];
    $plugin = spatieBridgeAs($user);

    expect($plugin->can('browse posts'))->toBeTrue()
        ->and($plugin->can('edit posts'))->toBeFalse();
});

test('the configured super administrator roles bypass checks on the host path too', function () {
    config()->set('tardis.authorization.super_admin_roles', ['super-admin']);
    $user = new SpatieBridgeUser;
    $user->roles = ['super-admin'];
    $plugin = spatieBridgeAs($user);

    expect($plugin->can('view logs'))->toBeTrue()
        ->and($plugin->can('edit posts'))->toBeTrue();
});
