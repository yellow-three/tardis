<?php

namespace Tardis\Core\Contracts\Plugins\Features\Provider;

interface Settings
{
    /** @return array<string, mixed> */
    public function provideSettings(): array;
}
