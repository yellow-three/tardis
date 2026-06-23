<?php

namespace Tardis\Auth;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tardis\Core\Classes\Widget;
use Tardis\Core\Contracts\Plugins\AuthenticationPlugin;
use Tardis\Core\Contracts\Plugins\Features\Provider\Widgets;

class TardisAuthPlugin implements AuthenticationPlugin, Widgets
{
    public function name(): string
    {
        return 'tardis-auth';
    }

    public function description(): string
    {
        return 'Default Fortify-based authentication for TARDIS admin';
    }

    public function user(): ?Authenticatable
    {
        return Auth::user();
    }

    public function avatar(): ?string
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        return 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($user->email))).'?d=mp';
    }

    public function guard(): string
    {
        return 'web';
    }

    public function authenticate(Request $request): ?array
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            return null;
        }

        return [__('auth.failed')];
    }

    public function logout(): void
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    public function redirectTo(): string
    {
        return route('dashboard', absolute: false);
    }

    public function handle(Request $request, Closure $next): mixed
    {
        auth()->setDefaultDriver($this->guard());

        if ($this->user() !== null) {
            return $next($request);
        }

        return redirect()->guest(route('login'));
    }

    public function loginComponent(): ?string
    {
        return null;
    }

    public function loginView(): ?string
    {
        return 'pages::auth.login';
    }

    public function provideWidgets(): array
    {
        return [
            (new Widget('widgets.stats-users', 'Total Users'))
                ->width(3)
                ->order(10),
        ];
    }
}
