<?php

declare(strict_types=1);

namespace Tardis\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tardis\Contracts\Plugins\AuthenticationPlugin as AuthenticationPluginContract;

class SessionAuthenticationPlugin implements AuthenticationPluginContract
{
    public function user(): mixed
    {
        return Auth::guard($this->guard())->user();
    }

    public function attempt(array $credentials, bool $remember = false): bool
    {
        $signedIn = Auth::guard($this->guard())->attempt($credentials, $remember);

        if ($signedIn && app()->bound('session.store')) {
            session()->regenerate();
        }

        return $signedIn;
    }

    public function logout(Request $request): Request
    {
        Auth::guard($this->guard())->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request;
    }

    public function redirectTo(): string
    {
        return route('tardis.dashboard');
    }

    public function handleRequest(Request $request, \Closure $next): mixed
    {
        if (! Auth::guard($this->guard())->check()) {
            return redirect()->guest(route('tardis.login'));
        }

        return $next($request);
    }

    public const KEY = 'tardis-authentication';

    public function name(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('tardis::plugins.labels.authentication');
    }

    public function description(): string
    {
        return __('tardis::plugins.descriptions.authentication');
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
