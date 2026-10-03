<?php

declare(strict_types=1);

namespace Tardis\Contracts\Plugins;

use Illuminate\Http\Request;

interface AuthenticationPlugin
{
    /**
     * Get the authenticated user.
     */
    public function user(): mixed;

    /**
     * Try to sign a user in with the submitted credentials.
     *
     * The login form calls this instead of touching the guard itself, so a
     * plugin can use another guard, a different credential check or SSO.
     *
     * @param  array<string, mixed>  $credentials
     * @return bool whether the user is now signed in
     */
    public function attempt(array $credentials, bool $remember = false): bool;

    /**
     * Logout the current user.
     */
    public function logout(Request $request): Request;

    /**
     * Redirect URL after successful login.
     */
    public function redirectTo(): string;

    /**
     * Login page Livewire component name (e.g. 'tardis::pages.login').
     */
    public function loginComponent(): string;

    /**
     * Handle authentication in admin middleware.
     * Return the handled request or abort.
     */
    public function handleRequest(Request $request, \Closure $next): mixed;

    /**
     * Handle forgot password request.
     */
    public function forgotPassword(Request $request): mixed;

    /**
     * Forgot password view component name.
     */
    public function forgotPasswordView(): ?string;

    /**
     * Plugin name identifier.
     */
    public function name(): string;

    /**
     * The field used to display the user's display name (e.g. 'name', 'email').
     */
    public function nameField(): string;

    /**
     * Get the avatar URL for the given user.
     */
    public function avatar(mixed $user): ?string;

    /**
     * The auth guard to use.
     */
    public function guard(): string;
}
