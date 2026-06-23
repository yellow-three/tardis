<?php

namespace Tardis\Core\Contracts\Plugins;

interface GenericPlugin
{
    public function name(): string;

    public function description(): string;
}
