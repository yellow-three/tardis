<?php

namespace Tardis\Core\Contracts\Plugins;

interface ThemePlugin
{
    public function name(): string;

    public function assets(): array;

    public function apply(): void;
}
