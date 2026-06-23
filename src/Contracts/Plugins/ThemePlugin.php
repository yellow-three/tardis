<?php

namespace Tardis\Contracts\Plugins;

interface ThemePlugin
{
    public function name(): string;

    public function assets(): array;

    public function apply(): void;
}
