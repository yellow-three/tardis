<?php

declare(strict_types=1);

namespace Tardis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Contracts\Plugins\AuthenticationPlugin;
use Tardis\Manager\PluginManager;

class AdminMiddleware
{
    public function __construct(
        protected PluginManager $pluginManager,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $authPlugins = $this->pluginManager->enabledWith(
            AuthenticationPlugin::class
        );

        /** @var AuthenticationPlugin|null $auth */
        $auth = $authPlugins->first();

        // Being logged in is not enough: the panel is for administrators, so once
        // the request is authenticated it must also hold the access ability.
        $authorized = function (Request $request) use ($next): mixed {
            app(BreadAuthorization::class)->authorizeAbility(Abilities::ACCESS);

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
