<?php

declare(strict_types=1);

namespace Tardis\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A record was deleted through a BREAD page. $permanently is true for a force
 * delete of a soft-deletable record, which cannot be restored.
 */
class BreadRecordDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $slug,
        public mixed $model,
        public bool $permanently = false,
    ) {}
}
