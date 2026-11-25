<?php

declare(strict_types=1);

namespace Tardis\Support;

/**
 * The one URL check shared by stored panel links and redirects.
 *
 * A leading "/\" is refused even though PHP sees one slash: browsers treat
 * both spellings as a protocol-relative URL and would send the visitor to the
 * host in the path. Backslashes, control characters and surrounding whitespace
 * are refused everywhere, including absolute URLs, because browsers normalise
 * them differently from PHP's parser.
 */
final class SafeUrl
{
    public static function isAllowed(string $url, bool $allowFragment = false): bool
    {
        if ($url === '' || $url !== trim($url) || str_contains($url, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }

        // Site menus allow "#section" links; notification and admin-menu links
        // never ask for them.
        if ($allowFragment && str_starts_with($url, '#')) {
            return true;
        }

        if ($url[0] === '/') {
            return ! isset($url[1]) || ($url[1] !== '/' && $url[1] !== '\\');
        }

        $parts = parse_url($url);

        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && $parts['host'] !== '';
    }
}
