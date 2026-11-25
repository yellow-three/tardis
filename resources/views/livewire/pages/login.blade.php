<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;
use Tardis\Plugins\PluginManager;

new #[Title('tardis::auth.login')] #[Layout('tardis::layouts.auth')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public ?string $error = null;

    protected function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:1',
        ];
    }

    public function mount(): void
    {
        if (auth()->check()) {
            $this->redirect(route('tardis.dashboard'));
        }
    }

    public function login(): void
    {
        $this->validate();

        $limiter = app(RateLimiter::class);
        $key = Str::lower($this->email).'|'.request()->ip();

        if ($limiter->tooManyAttempts($key, 5)) {
            $this->error = __('tardis::auth.too_many_attempts', ['seconds' => $limiter->availableIn($key)]);

            return;
        }

        $plugin = app(PluginManager::class)->authenticationPlugin();

        $signedIn = $plugin !== null
            ? $plugin->attempt(['email' => $this->email, 'password' => $this->password], $this->remember)
            : auth()->attempt(['email' => $this->email, 'password' => $this->password], $this->remember);

        if ($signedIn) {
            $limiter->clear($key);
            $user = $plugin?->user() ?? auth()->user();

            // The password was right; with two-factor on, the session is not
            // the user's until the second step. They are signed out again and
            // the challenge page finishes the sign-in.
            if ($user instanceof \Illuminate\Database\Eloquent\Model && app(\Tardis\Auth\TwoFactor\TwoFactorAuthentication::class)->enabledFor($user)) {
                // The guard belongs to the authentication plugin, not to one
                // concrete plugin class: a custom plugin may authenticate a
                // different guard altogether.
                $guard = $plugin?->guard() ?? config('auth.defaults.guard');
                auth()->guard($guard)->logout();
                session()->put('tardis.two_factor', ['id' => $user->getKey(), 'remember' => $this->remember, 'guard' => $guard, 'redirect' => $plugin?->redirectTo() ?? route('tardis.dashboard')]);
                $this->redirect(route('tardis.two-factor'));

                return;
            }

            app(\Tardis\Bread\ActivityLogger::class)->audit('tardis.auth', 'signed_in');
            $this->redirect($plugin !== null ? $plugin->redirectTo() : route('tardis.dashboard'));

            return;
        }

        $limiter->hit($key);
        app(\Tardis\Bread\ActivityLogger::class)->audit('tardis.auth', 'sign_in_failed', ['email' => mb_substr($this->email, 0, 255)]);

        $this->error = __('auth.failed');
    }
}; ?>

<div>
    @if ($error)
        <div class="alert alert-error mb-4">
            <span>{{ $error }}</span>
        </div>
    @endif

    @if (session('status'))
        <div class="alert alert-success mb-4">
            <x-tardis::icon name="check-circle" class="w-5 h-5" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div class="flex flex-col gap-2">
            <label class="label" for="email">
                <span class="text-base-content">{{ __('tardis::auth.email_address') }}</span>
            </label>
            <input
                type="email"
                id="email"
                wire:model="email"
                required
                autofocus
                autocomplete="email"
                placeholder="{{ __('tardis::auth.email_example_com') }}"
                class="input w-full @error('email') input-error @enderror"
            />
            @error('email')
                <label class="label">
                    <span class="text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label class="label" for="password">
                <span class="text-base-content">{{ __('tardis::auth.password') }}</span>
            </label>
            <input
                type="password"
                id="password"
                wire:model="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="input w-full @error('password') input-error @enderror"
            />
            @error('password')
                <label class="label">
                    <span class="text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label class="label cursor-pointer justify-start gap-3">
                <input
                    type="checkbox"
                    wire:model="remember"
                    class="checkbox checkbox-primary checkbox-sm"
                />
                <span class="text-base-content">{{ __('tardis::auth.remember_me') }}</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            {{ __('tardis::auth.log_in') }}
        </button>
    </form>
</div>
