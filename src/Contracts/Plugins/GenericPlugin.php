<?php

declare(strict_types=1);

namespace Tardis\Contracts\Plugins;

interface GenericPlugin
{
    public function name(): string;

    public function description(): string;
}
