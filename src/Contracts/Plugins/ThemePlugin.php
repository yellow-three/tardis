<?php

declare(strict_types=1);

namespace Tardis\Contracts\Plugins;

interface ThemePlugin
{
    public function name(): string;

    public function description(): string;

    /**
     * Themes this plugin adds to the panel, as data: each entry has the shape
     * of a themes.json entry (name, scheme "light"|"dark", label, colors and
     * an optional shape). They appear in the theme picker next to the
     * built-in and custom ones.
     *
     * Tardis validates every value and writes the CSS itself, so a theme
     * cannot inject arbitrary CSS; an invalid entry, or one that reuses a
     * built-in theme's name, is skipped. To change something other than a
     * theme's palette, provide a stylesheet through Provider\CSS instead.
     *
     * @return list<array<string, mixed>>
     */
    public function themes(): array;
}
