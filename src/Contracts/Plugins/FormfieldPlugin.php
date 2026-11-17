<?php

declare(strict_types=1);

namespace Tardis\Contracts\Plugins;

interface FormfieldPlugin
{
    public function name(): string;

    public function component(): string;

    public function render(): string;
}
