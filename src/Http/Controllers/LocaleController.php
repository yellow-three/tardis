<?php

declare(strict_types=1);

namespace Tardis\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Tardis\Support\Locales;
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

        return back();
    }
}
