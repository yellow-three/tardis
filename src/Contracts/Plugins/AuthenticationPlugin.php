<?php

namespace Tardis\Contracts\Plugins;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

interface AuthenticationPlugin
{
    public function name(): string;

    public function description(): string;

    public function user(): ?Authenticatable;

    public function avatar(): ?string;

    public function guard(): string;

    public function authenticate(Request $request): ?array;

    public function logout(): void;

    public function redirectTo(): string;

    public function handle(Request $request, Closure $next): mixed;

    public function loginComponent(): ?string;

    public function loginView(): ?string;
}
