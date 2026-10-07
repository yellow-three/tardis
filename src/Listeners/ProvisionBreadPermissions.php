<?php

declare(strict_types=1);

namespace Tardis\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tardis\Events\BreadSaved;
use Tardis\Support\ModelResolver;

/**
 * Create the browse/read/edit/add/delete abilities for a BREAD the moment its
 * definition is saved, so they appear on the Roles page (Voyager's
 * generate_permissions). Skipped on an install that has not run the package
 * migrations: saving a definition must not depend on them.
 */
class ProvisionBreadPermissions
{
    public function handle(BreadSaved $event): void
    {
        try {
            if (Schema::hasTable('tardis_permissions')) {
                ModelResolver::permission()::forBread($event->definition->permissionKey());
            }
        } catch (\Throwable $e) {
            Log::warning('Could not provision BREAD permissions.', [
                'slug' => $event->definition->slug,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
