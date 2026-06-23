<?php

namespace Tardis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! auth()->check()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
