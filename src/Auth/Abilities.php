<?php

declare(strict_types=1);

namespace Tardis\Auth;

/**
 * Abilities that gate the non-BREAD admin screens.
 *
 * BREAD pages use "{action} {slug}" abilities built by BreadAuthorization;
 * these are the fixed ones. PermissionSeeder creates every one of them, so a
 * name added here has to be added to its lists as well.
 */
final class Abilities
{
    /** Open the admin panel at all (checked by the tardis.admin middleware). */
    public const ACCESS = 'access admin';

    public const SETTINGS = 'manage settings';

    public const PLUGINS = 'manage plugins';

    public const USERS = 'manage users';

    public const ROLES = 'manage roles';

    public const DATABASE = 'manage database';

    public const BREAD = 'manage bread';

    public const ACTIVITY = 'view activity';

    public const MENUS = 'manage menus';

    public const DASHBOARD = 'manage dashboard';

    public const MEDIA_BROWSE = 'browse media';

    public const MEDIA_UPLOAD = 'upload media';

    public const MEDIA_RENAME = 'rename media';

    public const MEDIA_MOVE = 'move media';

    public const MEDIA_DELETE = 'delete media';

    /**
     * @return array<int, string>
     */
    public static function admin(): array
    {
        return [
            self::ACCESS,
            'browse admin',
            self::SETTINGS,
            self::PLUGINS,
            self::USERS,
            self::ROLES,
            self::DATABASE,
            self::BREAD,
            self::ACTIVITY,
            self::MENUS,
            self::DASHBOARD,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function media(): array
    {
        return [
            self::MEDIA_BROWSE,
            'read media',
            self::MEDIA_UPLOAD,
            'edit media',
            self::MEDIA_DELETE,
            self::MEDIA_RENAME,
            self::MEDIA_MOVE,
        ];
    }
}
