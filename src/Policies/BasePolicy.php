<?php

declare(strict_types=1);

namespace Tardis\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class BasePolicy
{
    use HandlesAuthorization;

    public function __call(string $method, array $parameters): bool
    {
        return true;
    }
}
