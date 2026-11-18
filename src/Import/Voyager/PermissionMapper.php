<?php

declare(strict_types=1);

namespace Tardis\Import\Voyager;

use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;

/**
 * A Voyager permission key (browse_posts, edit_settings, ...) as the Tardis
 * ability that grants the same thing, or null when there is none.
 */
final class PermissionMapper
{
    private const ACTIONS = ['browse', 'read', 'edit', 'add', 'delete'];

    /** Voyager tables that are built-in Tardis screens, not BREADs. */
    private const SCREENS = [
        'bread' => Abilities::BREAD,
        'database' => Abilities::DATABASE,
        'menus' => Abilities::MENUS,
        'settings' => Abilities::SETTINGS,
        'roles' => Abilities::ROLES,
        'users' => Abilities::USERS,
        'compass' => Abilities::SYSTEM,
    ];

    /**
     * @param  array<string, string>  $slugs  Voyager table name => imported BREAD slug
     */
    public function __construct(private readonly array $slugs = []) {}

    public function map(string $key): ?string
    {
        if ($key === 'browse_admin') {
            return Abilities::ACCESS;
        }

        if (preg_match('/^('.implode('|', self::ACTIONS).')_(.+)$/', $key, $match) !== 1) {
            return null;
        }

        [, $action, $table] = $match;

        if ($table === 'media') {
            return match ($action) {
                'browse', 'read' => Abilities::MEDIA_BROWSE,
                'add' => Abilities::MEDIA_UPLOAD,
                'edit' => Abilities::MEDIA_RENAME,
                'delete' => Abilities::MEDIA_DELETE,
            };
        }

        if (isset(self::SCREENS[$table])) {
            return self::SCREENS[$table];
        }

        return isset($this->slugs[$table]) ? BreadAuthorization::ability($action, $this->slugs[$table]) : null;
    }
}
