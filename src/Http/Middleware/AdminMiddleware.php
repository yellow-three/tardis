<?php

declare(strict_types=1);

namespace Tardis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        if ($auth) {
            return $auth->handleRequest($request, $next);
        }

        // Fallback: direct auth check if no AuthenticationPlugin registered
        if (! auth()->check()) {
            return redirect()->guest(route('tardis.login'));
        }

        return $next($request);
    }
}
