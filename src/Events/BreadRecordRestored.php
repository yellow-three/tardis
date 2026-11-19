<?php

declare(strict_types=1);

namespace Tardis\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A soft-deleted record was restored through a BREAD page.
 */
class BreadRecordRestored
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $slug,
        public mixed $model,
    ) {}
}
