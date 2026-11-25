<?php

declare(strict_types=1);

namespace Tardis\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Tardis\Auth\Impersonation;

/**
 * Back to one's own account. Outside the panel group, like logout: the user
 * being impersonated may not be allowed to open the panel at all.
 */
class LeaveImpersonationController
{
    public function __invoke(Impersonation $impersonation): RedirectResponse
    {
        return $impersonation->leave()
            ? redirect()->route('tardis.users.index')
            : redirect()->route('tardis.dashboard');
    }
}
