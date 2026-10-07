<?php

declare(strict_types=1);

namespace Tardis\Bread;

/**
 * URL segments the package owns. A BREAD with one of these slugs would be
 * shadowed by (or would shadow) a fixed admin screen, so definitions cannot use
 * them and no BREAD routes are generated for them.
 */
final class ReservedSlugs
{
    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            'dashboard', 'login', 'logout', 'forgot-password', 'reset-password',
            'plugins', 'media', 'activity-log', 'database', 'settings', 'search',
            'permissions', 'roles', 'users', 'ui-components', 'bread', '_assets',
        ];
    }

    public static function has(string $slug): bool
    {
        return in_array($slug, self::all(), true);
    }
}
