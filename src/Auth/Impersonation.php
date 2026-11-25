<?php

declare(strict_types=1);

namespace Tardis\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Tardis\Bread\ActivityLogger;
use Tardis\Plugins\PluginManager;
use Tardis\Support\PanelUser;

/**
 * Signing in as another user to see the panel as they do, and back.
 *
 * Needs "impersonate users", and follows the Users screen's rule: nobody takes
 * over an account that holds the super-administrator role or a permission
 * they do not hold themselves. Both ends are written to the activity log.
 */
final class Impersonation
{
    public const SESSION_KEY = 'tardis.impersonator';

    public function mayImpersonate(?Model $actor, ?Model $target): bool
    {
        return $actor !== null && $target !== null
            && ! $this->active()
            && (string) $actor->getKey() !== (string) $target->getKey()
            && app(BreadAuthorization::class)->allowsAbility(Abilities::IMPERSONATE)
            && app(PrivilegeGuard::class)->mayManageUser($actor, $target);
    }

    public function start(Model $target): void
    {
        $actor = PanelUser::current();

        abort_unless($actor instanceof Model && $target instanceof Authenticatable && $this->mayImpersonate($actor, $target), 403);

        $guard = $this->guard();
        app(ActivityLogger::class)->audit('tardis.auth', 'impersonation_started', ['as' => $target->getKey()], $target->getKey());

        session()->put(self::SESSION_KEY, ['id' => $actor->getKey(), 'guard' => $guard]);
        auth()->guard($guard)->login($target);
        session()->regenerate();
    }

    /** Back to the account that started it. False when nothing was started. */
    public function leave(): bool
    {
        $impersonator = $this->impersonator();

        if ($impersonator === null) {
            return false;
        }

        $guard = auth()->guard($impersonator['guard']);
        $original = $guard->getProvider()->retrieveById($impersonator['id']);
        $as = $guard->id();

        session()->forget(self::SESSION_KEY);

        if (! $original instanceof Model || ! $original instanceof Authenticatable) {
            $guard->logout();

            return true;
        }

        $guard->login($original);
        session()->regenerate();
        app(ActivityLogger::class)->audit('tardis.auth', 'impersonation_ended', ['as' => $as], $original->getKey());

        return true;
    }

    public function active(): bool
    {
        return $this->impersonator() !== null;
    }

    /** @return array{id: int|string, guard: string}|null */
    public function impersonator(): ?array
    {
        $value = session(self::SESSION_KEY);

        return is_array($value) && isset($value['id'], $value['guard']) ? $value : null;
    }

    private function guard(): string
    {
        $plugin = app(PluginManager::class)->authenticationPlugin();

        return $plugin instanceof SessionAuthenticationPlugin ? $plugin->guard() : (string) config('auth.defaults.guard');
    }
}
