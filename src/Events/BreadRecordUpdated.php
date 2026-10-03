<?php

namespace Tardis\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A record was updated through a BREAD page.
 */
class BreadRecordUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $old  the changed attributes before the update
     * @param  array<string, mixed>  $new  the changed attributes after the update
     */
    public function __construct(
        public string $slug,
        public mixed $model,
        public array $old = [],
        public array $new = [],
    ) {}
}
