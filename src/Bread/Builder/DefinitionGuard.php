<?php

declare(strict_types=1);

namespace Tardis\Bread\Builder;

use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Auth\PrivilegeGuard;
use Tardis\Bread\BreadDefinition;
use Tardis\Support\ModelResolver;
use Tardis\Support\PanelUser;

/**
 * Authorization and definition sanity checks for the BREAD builder.
 *
 * Kept out of the Livewire component so the same rules apply to any future
 * entry point that writes definitions, and the single-file component stays
 * small.
 */
final class DefinitionGuard
{
    public function authorizeScreen(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::BREAD);
    }

    /**
     * A definition appointing another resource's policy grants the abilities of
     * that policy, so creating it must require managing that policy first. A few
     * models are also powerful enough to need their own fixed ability.
     */
    public function authorize(string $model, string $slug, ?string $policy): void
    {
        $auth = app(BreadAuthorization::class);

        if ($policy !== null && $policy !== '' && $policy !== $slug) {
            $user = PanelUser::current();
            $superAdmin = is_object($user) && method_exists($user, 'getKey')
                && app(PrivilegeGuard::class)->isSuperAdmin($user);

            if (! $superAdmin && ! $auth->allows('manage', $policy)) {
                abort(403, __('tardis::shell.unauthorized'));
            }
        }

        $protectedModels = [
            [(string) config('auth.providers.users.model'), Abilities::USERS],
            [ModelResolver::role(), Abilities::ROLES],
            [ModelResolver::permission(), Abilities::ROLES],
            [ModelResolver::activityLog(), Abilities::ACTIVITY],
        ];

        foreach ($protectedModels as [$class, $ability]) {
            if ($class !== '' && is_a($model, $class, true)) {
                $auth->authorizeAbility($ability);
            }
        }
    }

    /** The translation key for an unusable model, or null when it is valid. */
    public static function modelError(string $model): ?string
    {
        if (BreadDefinition::isModelClass($model)) {
            return null;
        }

        return class_exists($model) ? 'tardis::builder.model_not_eloquent' : 'tardis::builder.model_not_found';
    }
}
