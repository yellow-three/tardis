<?php

namespace Tardis\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BreadUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $slug,
        public mixed $model,
        public array $old,
        public array $new,
    ) {}
}
