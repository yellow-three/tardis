<?php

declare(strict_types=1);

namespace Tardis\Http;

use Closure;
use Illuminate\Support\Facades\Route;
use Tardis\Http\Middleware\SecurityHeaders;

/**
 * The one place that knows how a panel route group looks: the admin prefix,
 * the `tardis.` name prefix and the middleware. routes/admin.php, the BREAD
 * routes and plugin routes all open their groups through here, so a change
 * (another middleware, a guard, a domain) is made once.
 */
final class PanelRoutes
{
    /** Middleware of every panel page; `tardis.admin` checks sign-in and "access admin". */
    public const MIDDLEWARE = ['web', 'tardis.locale', 'tardis.admin', SecurityHeaders::class];

    /** Middleware of the pages a guest needs: sign-in, password reset, preferences. */
    public const GUEST_MIDDLEWARE = ['web', 'tardis.locale', SecurityHeaders::class];

    public static function prefix(): string
    {
        return (string) config('tardis.admin.prefix', 'admin');
    }

    /**
     * Routes that require the panel's sign-in and "access admin".
     */
    public static function panel(Closure $routes): void
    {
        self::group(self::MIDDLEWARE, $routes);
    }

    /**
     * Routes reachable without "access admin" (a guest, or a signed-in user
     * who may only sign out).
     */
    public static function guest(Closure $routes): void
    {
        self::group(self::GUEST_MIDDLEWARE, $routes);
    }

    /**
     * Routes with no middleware at all, such as the hashed asset files.
     */
    public static function bare(Closure $routes): void
    {
        self::group([], $routes);
    }

    /**
     * Run once every provider has booted: plugins register in their own
     * providers, which may boot after this package.
     */
    public static function whenBooted(Closure $callback): void
    {
        $app = app();

        if ($app->isBooted()) {
            $callback();

            return;
        }

        $app->booted(fn () => $callback());
    }

    /**
     * @param  list<string>  $middleware
     */
    private static function group(array $middleware, Closure $routes): void
    {
        Route::middleware($middleware)
            ->prefix(self::prefix())
            ->name('tardis.')
            ->group($routes);
    }
}
