<?php

declare(strict_types=1);

namespace Tardis\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tardis\Bread\ActivityLogger;
use Tardis\Plugins\PluginManager;

/**
 * Signs the user out through the authentication plugin.
 *
 * It sits outside the `tardis.admin` group on purpose: someone who is signed in
 * but may not open the panel (no "access admin") gets a 403 on every page and
 * must still be able to leave, or switch to another account.
 */
class LogoutController
{
    public function __invoke(Request $request, PluginManager $plugins): RedirectResponse
    {
        // Written before the session ends, so the entry still names the user.
        app(ActivityLogger::class)->audit('tardis.auth', 'signed_out');

        $plugin = $plugins->authenticationPlugin();

        if ($plugin !== null) {
            $plugin->logout($request);
        } else {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('tardis.login');
    }
}
