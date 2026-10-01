<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tardis\Policies\BasePolicy;

/**
 * Stands in for a host policy. The slug is derived from the class name, so the
 * class name is part of the contract: a host seeds its Spatie permissions as
 * "browse PostPolicy" and this class has to ask for exactly that string.
 */
class PostPolicy extends BasePolicy {}

/**
 * A host user carrying Spatie's HasRoles trait. TARDIS has no dependency on
 * spatie/laravel-permission — BasePolicy probes for the method at runtime — so
 * this stands in for whatever the host installed, without the package present.
 */
class SpatieUser extends Authenticatable
{
    protected $table = 'users';

    /** @var array<int, string> */
    public array $askedAbilities = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->exists = true;
    }

    public function hasPermissionTo($ability, $guardName = null): bool
    {
        $this->askedAbilities[] = $ability;

        return str_starts_with($ability, 'browse');
    }
}

test('a policy asks the host for an ability named after the policy class', function () {
    $user = new SpatieUser;

    expect((new PostPolicy)->browse($user, null))->toBeTrue()
        ->and($user->askedAbilities)->toBe(['browse PostPolicy']);
});

test('every policy action reaches the host under its own ability string', function () {
    $policy = new PostPolicy;

    $policy->browseAny($user = new SpatieUser);
    $policy->browse($user, null);
    $policy->read($user, null);
    $policy->edit($user, null);
    $policy->add($user);
    $policy->delete($user, null);

    expect($user->askedAbilities)->toBe([
        'browse PostPolicy',
        'browse PostPolicy',
        'read PostPolicy',
        'edit PostPolicy',
        'add PostPolicy',
        'delete PostPolicy',
    ]);
});

test('a host denial is passed through instead of being overridden', function () {
    // The interop branch is the host's answer, so it has to be returned as-is.
    // Anything that treated "no ability string of ours was recognised" as
    // permission would turn every Spatie policy into a blanket allow.
    $user = new SpatieUser;

    expect((new PostPolicy)->delete($user, null))->toBeFalse()
        ->and((new PostPolicy)->read($user, null))->toBeFalse();
});
