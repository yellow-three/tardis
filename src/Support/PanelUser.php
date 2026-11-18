<?php

declare(strict_types=1);

namespace Tardis\Support;

use Tardis\Plugins\PluginManager;

/**
 * Who is signed in to the panel, as the authentication plugin describes them:
 * its guard picks the user, its nameField() the label and avatar() the picture.
 * The shell reads the user through here instead of auth()->user() so a plugin
 * that signs people in with another guard is shown correctly too.
 */
final class PanelUser
{
    public static function current(): mixed
    {
        return app(PluginManager::class)->authenticationPlugin()?->user() ?? auth()->user();
    }

    public static function name(mixed $user = null): string
    {
        $user ??= self::current();

        if ($user === null) {
            return __('tardis::shell.admin_fallback');
        }

        $field = app(PluginManager::class)->authenticationPlugin()?->nameField() ?? 'name';
        $name = data_get($user, $field) ?: data_get($user, 'name') ?: data_get($user, 'email');

        return is_string($name) && $name !== '' ? $name : __('tardis::shell.admin_fallback');
    }

    public static function avatar(mixed $user = null): ?string
    {
        $user ??= self::current();

        if ($user === null) {
            return null;
        }

        $url = app(PluginManager::class)->authenticationPlugin()?->avatar($user);

        // Only http(s) and root-relative URLs become an <img src>.
        return is_string($url) && preg_match('#^(https?://|/(?!/))#i', $url) === 1 ? $url : null;
    }
}
