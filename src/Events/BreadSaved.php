<?php

namespace Tardis\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Tardis\Bread\BreadDefinition;

/**
 * A BREAD definition was written (created or updated).
 */
class BreadSaved
{
    use Dispatchable;

    public function __construct(
        public BreadDefinition $definition,
        public bool $wasNew,
    ) {}
}
