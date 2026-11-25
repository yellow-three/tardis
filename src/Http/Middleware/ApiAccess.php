<?php

declare(strict_types=1);

namespace Tardis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;

/**
 * The API's door: a signed-in user holding "access admin", the same as the
 * panel. Answers in JSON (401/403) instead of redirecting to the login page.
 */
class ApiAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth()->guest()) {
            return response()->json(['message' => __('tardis::api.unauthenticated')], 401);
        }

        if (! app(BreadAuthorization::class)->allowsAbility(Abilities::ACCESS)) {
            return response()->json(['message' => __('tardis::api.forbidden')], 403);
        }

        return $next($request);
    }
}
