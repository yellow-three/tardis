<?php

namespace Tardis\Contracts\Plugins;

interface GenericPlugin
{
    public function name(): string;

    public function description(): string;
}
