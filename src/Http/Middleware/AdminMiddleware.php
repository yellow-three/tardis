<?php

declare(strict_types=1);

namespace Tardis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Manager\PluginManager;

class AdminMiddleware
{
    public function __construct(
        protected PluginManager $pluginManager,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $auth = $this->pluginManager->authenticationPlugin();

        // Being logged in is not enough: the panel is for administrators, so once
        // the request is authenticated it must also hold the access ability.
        $authorized = function (Request $request) use ($next): mixed {
            app(BreadAuthorization::class)->authorizeAbility(Abilities::ACCESS);

            // Announce the page so plugins can hook the request lifecycle.
            Event::dispatch('tardis.page', [$request]);

            return $next($request);
        };

        if ($auth) {
            return $auth->handleRequest($request, $authorized);
        }

        // Fallback: direct auth check if no AuthenticationPlugin registered
        if (! auth()->check()) {
            return redirect()->guest(route('tardis.login'));
        }

        return $authorized($request);
    }
}
