<?php

namespace Tardis\Contracts\Plugins\Features\Provider;

use Tardis\Assets\Asset;

interface JS
{
    /**
     * Plain text is written inline; return an Asset (or a list of them) to have
     * Tardis serve files from a hashed, cacheable URL instead.
     *
     * @return string|Asset|array<int, Asset>
     */
    public function provideJS(): string|Asset|array;
}
