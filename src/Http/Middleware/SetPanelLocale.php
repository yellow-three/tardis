<?php

declare(strict_types=1);

namespace Tardis\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Tardis\Support\Locales;
use Tardis\Support\UserPreferences;

/**
 * Picks the panel language: the signed-in user's stored choice, then the
 * session's, then the one this browser last used (a cookie, so the login and
 * two-factor pages speak it after sign-out too), then, for a visitor who is
 * not signed in, the browser's Accept-Language, then whatever the
 * application is already using.
 */
class SetPanelLocale
{
    public const COOKIE = 'tardis_locale';

    public function __construct(protected UserPreferences $preferences) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $saved = $this->preferences->get($request->user()?->getAuthIdentifier(), 'locale');
        $remembered = $request->cookie(self::COOKIE);

        $candidates = [
            $saved,
            $request->hasSession() ? $request->session()->get('tardis.locale') : null,
            $remembered,
            // Only before sign-in: a signed-in user without a choice gets the
            // application's language, as before.
            $request->user() === null ? $this->browserLocale($request) : null,
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && Locales::isAvailable($locale)) {
                app()->setLocale($locale);

                break;
            }
        }

        // The signed-in user's language is remembered for the sign-in pages.
        if (is_string($saved) && Locales::isAvailable($saved) && $saved !== $remembered) {
            Cookie::queue(self::cookie($saved));
        }

        return $next($request);
    }

    public static function cookie(string $locale): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(self::COOKIE, $locale, 60 * 24 * 365, httpOnly: true, sameSite: 'lax');
    }

    /** The best match for Accept-Language, only when the browser sent one. */
    protected function browserLocale(Request $request): ?string
    {
        if (! config('tardis.admin.browser_locale', true) || ! $request->headers->has('Accept-Language')) {
            return null;
        }

        $available = Locales::available();

        // In the browser's order; "pt-BR" matches a "pt" translation.
        foreach ($request->getLanguages() as $language) {
            $language = str_replace('-', '_', $language);
            $base = strtolower(strtok($language, '_') ?: '');

            foreach ([$language, $base] as $candidate) {
                if (in_array($candidate, $available, true)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
