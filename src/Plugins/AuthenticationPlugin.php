<?php

declare(strict_types=1);

namespace Tardis\Plugins;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tardis\Contracts\Plugins\AuthenticationPlugin as AuthenticationPluginContract;

class AuthenticationPlugin implements AuthenticationPluginContract
{
    public function user(): mixed
    {
        return Auth::user();
    }

    public function authenticate(Request $request): Request
    {
        $credentials = $request->only(['email', 'password']);
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
        }

        return $request;
    }

    public function logout(Request $request): Request
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request;
    }

    public function redirectTo(): string
    {
        return route('tardis.dashboard');
    }

    public function loginComponent(): string
    {
        return 'tardis::pages.login';
    }

    public function handleRequest(Request $request, \Closure $next): mixed
    {
        if (! Auth::check()) {
            return redirect()->guest(route('tardis.login'));
        }

        return $next($request);
    }

    public function forgotPassword(Request $request): mixed
    {
        return redirect()->route('tardis.login');
    }

    public function forgotPasswordView(): ?string
    {
        return null;
    }

    public function name(): string
    {
        return 'TARDIS Default Auth';
    }

    public function nameField(): string
    {
        return 'name';
    }

    public function avatar(mixed $user): ?string
    {
        return null;
    }

    public function guard(): string
    {
        return 'web';
    }
}
