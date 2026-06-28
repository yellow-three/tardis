<?php

namespace Tardis\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BreadDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $slug,
        public mixed $model,
    ) {}
}
