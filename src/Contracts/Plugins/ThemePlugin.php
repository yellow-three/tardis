<?php

namespace Tardis\Contracts\Plugins;

interface ThemePlugin
{
    public function name(): string;

    public function description(): string;

    /** @return array<string, string> CSS variable overrides */
    public function getTheme(): array;

    public function getStyles(): string;
}
