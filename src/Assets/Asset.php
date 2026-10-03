<?php

declare(strict_types=1);

namespace Tardis\Assets;

/**
 * A stylesheet or script the admin pages load: a URL (http(s) or root-relative)
 * or an inline block. Built with the named constructors; the asset manager
 * validates URLs and integrity values when it writes them.
 *
 * File-backed, hash-addressed and scoped assets for plugins arrive in a later
 * phase; this is the value object they will extend.
 */
final class Asset
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $url,
        public readonly ?string $inline,
        public readonly ?string $integrity = null,
        public readonly bool $defer = true,
    ) {}

    public static function css(string $url, ?string $integrity = null): self
    {
        return new self('css', $url, null, $integrity);
    }

    public static function js(string $url, ?string $integrity = null, bool $defer = true): self
    {
        return new self('js', $url, null, $integrity, $defer);
    }

    public static function inlineCss(string $css): self
    {
        return new self('css', null, $css);
    }

    public static function inlineJs(string $js): self
    {
        return new self('js', null, $js);
    }
}
