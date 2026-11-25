<?php

declare(strict_types=1);

namespace Tardis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * API answers must stay JSON even when the client sends no Accept header:
 * middleware that runs before ApiAccess (auth, throttle) otherwise treats a
 * guest as a browser and redirects to a host's login route, which may not
 * exist. Run this first in the API route group.
 */
class ApiJson
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
