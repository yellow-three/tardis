<?php

declare(strict_types=1);

namespace Tardis\Listeners;

use Tardis\Bread\ActivityLogger;
use Tardis\Events\BreadRemoved;
use Tardis\Events\BreadSaved;

/**
 * Writes BREAD definition changes (builder, import, make:bread) to the
 * activity log, so a changed list or form can be traced to a person.
 */
class LogBreadDefinitionChange
{
    public function __construct(protected ActivityLogger $logger) {}

    public function handle(BreadSaved|BreadRemoved $event): void
    {
        if ($event instanceof BreadRemoved) {
            $this->logger->audit('tardis.bread_definition', 'removed', ['slug' => $event->slug]);

            return;
        }

        $this->logger->audit('tardis.bread_definition', $event->wasNew ? 'created' : 'updated', [
            'slug' => $event->definition->slug,
            'model' => $event->definition->model,
        ]);
    }
}
