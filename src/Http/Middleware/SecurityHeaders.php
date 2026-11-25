<?php

declare(strict_types=1);

namespace Tardis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Adds the panel's framing, MIME-sniffing and referrer headers. A header the
 * host already sent is left alone: a host may have a stricter policy, and
 * overwriting it here would silently weaken that policy. HSTS is not set —
 * whether a site is TLS-only belongs to the host, not to the panel package.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        /** @var array<string, string|null> $headers */
        $headers = (array) config('tardis.security.headers', []);

        foreach ($headers as $header => $value) {
            if ($value === null || $response->headers->has($header)) {
                continue;
            }

            $response->headers->set($header, $value);
        }

        return $response;
    }
}
