<?php

declare(strict_types=1);

namespace Tardis\Bread;

/**
 * URL segments the package owns. A BREAD with one of these slugs would be
 * shadowed by (or would shadow) a fixed admin screen, so definitions cannot use
 * them and no BREAD routes are generated for them.
 *
 * The list is the fixed one below plus the first segment of every `tardis.*`
 * route registered so far, so a new screen is reserved without anyone having
 * to remember to add it here.
 */
final class ReservedSlugs
{
    /** Segments that are not, or not always, a registered route. */
    private const FIXED = [
        'dashboard', 'login', 'logout', 'forgot-password', 'reset-password',
        'plugins', 'media', 'activity-log', 'database', 'settings', 'search',
        'permissions', 'roles', 'users', 'ui-components', 'bread', '_assets',
        'themes', 'menus', 'system', 'preferences', 'livewire',
    ];

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_values(array_unique([...self::FIXED, ...self::fromRoutes()]));
    }

    public static function has(string $slug): bool
    {
        return in_array($slug, self::all(), true);
    }

    /**
     * First path segments of the panel's own routes, leaving out the BREAD
     * routes themselves (their first segment is the slug being checked).
     *
     * @return list<string>
     */
    private static function fromRoutes(): array
    {
        if (! app()->bound('router')) {
            return [];
        }

        $prefix = trim((string) config('tardis.admin.prefix', 'admin'), '/');
        $segments = [];

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'tardis.') || preg_match('/^tardis\.bread\.(index|create|edit|show|order)(\.|$)/', $name) === 1) {
                continue;
            }

            $path = trim($route->uri(), '/');

            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                $path = trim(substr($path, strlen($prefix)), '/');
            }

            $segment = explode('/', $path)[0];

            if ($segment !== '' && ! str_starts_with($segment, '{')) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }
}
