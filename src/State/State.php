<?php

declare(strict_types=1);

namespace Tardis\State;

/**
 * Shorthand for the configured state store. Resolved on every call, not
 * kept: the store is scoped, so a long-running worker gets a fresh one per
 * request or job.
 */
final class State
{
    public static function store(): StateStore
    {
        return app(StateStore::class);
    }
}
