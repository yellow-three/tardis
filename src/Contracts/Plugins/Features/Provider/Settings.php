<?php

declare(strict_types=1);

namespace Tardis\Contracts\Plugins\Features\Provider;

interface Settings
{
    /** @return array<string, mixed> */
    public function provideSettings(): array;
}
