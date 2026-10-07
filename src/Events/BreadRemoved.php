<?php

namespace Tardis\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A BREAD definition was deleted. Its permissions and activity history are
 * left in place on purpose; listeners decide what else to clean up.
 */
class BreadRemoved
{
    use Dispatchable;

    public function __construct(
        public string $slug,
    ) {}
}
