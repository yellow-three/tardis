<?php

namespace Tardis\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as Authenticatable;

class BasePolicy
{
    use HandlesAuthorization;

    protected string $slug;

    public function __construct()
    {
        $this->slug = class_basename($this);
    }

    public function browseAny(Authenticatable $user): bool
    {
        return $this->hasPermission($user, 'browse');
    }

    public function browse(Authenticatable $user, $model): bool
    {
        return $this->hasPermission($user, 'browse');
    }

    public function read(Authenticatable $user, $model): bool
    {
        return $this->hasPermission($user, 'read');
    }

    public function edit(Authenticatable $user, $model): bool
    {
        return $this->hasPermission($user, 'edit');
    }

    public function add(Authenticatable $user): bool
    {
        return $this->hasPermission($user, 'add');
    }

    public function delete(Authenticatable $user, $model): bool
    {
        return $this->hasPermission($user, 'delete');
    }

    protected function hasPermission(Authenticatable $user, string $action): bool
    {
        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo("{$action} {$this->slug}");
        }

        return true;
    }
}
