<?php

declare(strict_types=1);

namespace Tardis\Auth;

use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Manager\PluginManager;

/**
 * Gates every BREAD page on the enabled AuthorizationPlugin.
 *
 * Ability strings are built the same way Permission::forBread() creates them
 * ("browse posts", "add posts"), so a role granted a permission on the Roles
 * page is exactly what unlocks the matching page.
 *
 * When no AuthorizationPlugin is registered the guard fails open, mirroring
 * MenuItem::isVisible(): an install that never configures permissions keeps
 * the behaviour it has today instead of locking every administrator out.
 */
class BreadAuthorization
{
    public function __construct(protected ?PluginManager $plugins = null) {}

    /**
     * The permission slug a BREAD action is stored under.
     */
    public static function ability(string $action, string $slug): string
    {
        return trim($action).' '.trim($slug);
    }

    public function allows(string $action, string $slug, mixed $model = null): bool
    {
        $auth = $this->plugin();

        if (! $auth instanceof AuthorizationPlugin) {
            return true;
        }

        return $auth->can(static::ability($action, $slug), $model);
    }

    public function authorize(string $action, string $slug, mixed $model = null): void
    {
        if (! $this->allows($action, $slug, $model)) {
            abort(403, 'Unauthorized.');
        }
    }

    /**
     * Check a fixed ability (see Abilities) rather than a BREAD action.
     */
    public function allowsAbility(string $ability): bool
    {
        $auth = $this->plugin();

        if (! $auth instanceof AuthorizationPlugin) {
            return true;
        }

        return $auth->can($ability, null);
    }

    public function authorizeAbility(string $ability): void
    {
        if (! $this->allowsAbility($ability)) {
            abort(403, 'Unauthorized.');
        }
    }

    protected function plugin(): ?AuthorizationPlugin
    {
        $this->plugins ??= app(PluginManager::class);

        $auth = $this->plugins->enabledWith(AuthorizationPlugin::class)->first();

        return $auth instanceof AuthorizationPlugin ? $auth : null;
    }
}
