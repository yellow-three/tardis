<?php

namespace Tardis\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A record was created through a BREAD page.
 */
class BreadRecordCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data  the attributes that were written
     */
    public function __construct(
        public string $slug,
        public mixed $model,
        public array $data = [],
    ) {}
}
