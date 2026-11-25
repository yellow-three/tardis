<?php

declare(strict_types=1);

namespace Tardis\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Tardis\Http\Middleware\SetPanelLocale;
use Tardis\Support\Locales;
use Tardis\Support\SafeUrl;
use Tardis\Support\UserPreferences;

/**
 * Switches the panel language: remembered for the signed-in user, and in the
 * session for everyone (the login screen has no user yet).
 */
class LocaleController
{
    public function __invoke(Request $request, UserPreferences $preferences): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locales::available())],
        ]);

        $request->session()->put('tardis.locale', $validated['locale']);
        $preferences->set($request->user()?->getAuthIdentifier(), 'locale', $validated['locale']);

        return redirect()->to($this->previousUrl($request))
            ->withCookie(SetPanelLocale::cookie($validated['locale']));
    }

    /**
     * back() follows a client-controlled Referer (or the previous URL stored in
     * the session), which turns this guest POST into an open redirect. Keep the
     * old page only when it really is a same-host http(s) URL; otherwise send
     * the visitor to the panel page they can actually see.
     */
    private function previousUrl(Request $request): string
    {
        $previous = url()->previous();
        $parts = parse_url($previous);

        if (is_array($parts)
            && SafeUrl::isAllowed($previous)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && strcasecmp((string) ($parts['host'] ?? ''), $request->getHost()) === 0
            && (! isset($parts['port']) || $parts['port'] === $request->getPort())) {
            return $previous;
        }

        return route($request->user() !== null ? 'tardis.dashboard' : 'tardis.login');
    }
}
