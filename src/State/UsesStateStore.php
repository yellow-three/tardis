<?php

declare(strict_types=1);

namespace Tardis\State;

/**
 * For a store that can be pointed at a file of its own: given a path, it
 * reads and writes that file; at its default location it follows
 * tardis.state.driver.
 */
trait UsesStateStore
{
    protected bool $ownFile = false;

    protected function state(): StateStore
    {
        return $this->ownFile ? new FileStateStore : State::store();
    }
}
