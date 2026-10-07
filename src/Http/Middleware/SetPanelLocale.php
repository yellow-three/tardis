<?php

declare(strict_types=1);

namespace Tardis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tardis\Support\Locales;
use Tardis\Support\UserPreferences;

/**
 * Picks the panel language: the signed-in user's stored choice, then the
 * session's, then whatever the application is already using.
 */
class SetPanelLocale
{
    public function __construct(protected UserPreferences $preferences) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $candidates = [
            $this->preferences->get($request->user()?->getAuthIdentifier(), 'locale'),
            $request->hasSession() ? $request->session()->get('tardis.locale') : null,
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && Locales::isAvailable($locale)) {
                app()->setLocale($locale);

                break;
            }
        }

        return $next($request);
    }
}
