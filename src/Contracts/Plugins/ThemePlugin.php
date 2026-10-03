<?php

namespace Tardis\Contracts\Plugins;

interface ThemePlugin
{
    public function name(): string;

    public function description(): string;

    /**
     * CSS custom properties the theme sets (name => value), e.g.
     * ['--color-primary' => 'oklch(45% 0.2 260)'].
     *
     * Only data is accepted: Tardis validates the names and values and writes
     * the rule itself, so a theme cannot inject arbitrary CSS.
     *
     * @return array<string, string>
     */
    public function getTheme(): array;
}
