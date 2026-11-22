<?php

use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\TwoFactor\TwoFactorAuthentication;
use Tardis\Bread\ActivityLogger;

new #[Title('tardis::auth.two_factor_title')] #[Layout('tardis::layouts.auth')] class extends Component
{
    public string $code = '';

    public ?string $error = null;

    public function mount(): void
    {
        if ($this->pending() === null) {
            $this->redirect(route('tardis.login'));
        }
    }

    /**
     * The sign-in waiting for its second step, as the login page left it.
     *
     * @return array{id: int|string, remember: bool, guard: string, redirect: string}|null
     */
    protected function pending(): ?array
    {
        $pending = session('tardis.two_factor');

        return is_array($pending) && isset($pending['id'], $pending['guard']) ? $pending : null;
    }

    public function verify(): void
    {
        $pending = $this->pending();

        if ($pending === null) {
            $this->redirect(route('tardis.login'));

            return;
        }

        $limiter = app(RateLimiter::class);
        $key = 'tardis-two-factor|'.$pending['id'].'|'.request()->ip();

        if ($limiter->tooManyAttempts($key, 5)) {
            $this->error = __('tardis::auth.too_many_attempts', ['seconds' => $limiter->availableIn($key)]);

            return;
        }

        $provider = auth()->guard($pending['guard'])->getProvider();
        $user = $provider->retrieveById($pending['id']);

        if (! $user instanceof Model || ! app(TwoFactorAuthentication::class)->verify($user, $this->code)) {
            $limiter->hit($key);
            $this->code = '';
            $this->error = __('tardis::auth.two_factor_wrong');
            app(ActivityLogger::class)->audit('tardis.auth', 'two_factor_failed', [], $pending['id']);

            return;
        }

        $limiter->clear($key);
        session()->forget('tardis.two_factor');
        auth()->guard($pending['guard'])->login($user, (bool) $pending['remember']);
        session()->regenerate();
        app(ActivityLogger::class)->audit('tardis.auth', 'signed_in', ['two_factor' => true]);

        $this->redirect($pending['redirect'] ?? route('tardis.dashboard'));
    }

    public function cancel(): void
    {
        session()->forget('tardis.two_factor');
        $this->redirect(route('tardis.login'));
    }
}; ?>

<div>
    <h2 class="mb-2 text-lg font-semibold">{{ __('tardis::auth.two_factor_title') }}</h2>
    <p class="mb-4 text-sm text-base-content/70">{{ __('tardis::auth.two_factor_prompt') }}</p>

    @if ($error)
        <div class="alert alert-error mb-4" role="alert"><span>{{ $error }}</span></div>
    @endif

    <form wire:submit="verify" class="space-y-4">
        <div class="flex flex-col gap-2">
            <label class="label" for="code">
                <span class="text-base-content">{{ __('tardis::auth.two_factor_code') }}</span>
            </label>
            <input type="text" id="code" wire:model="code" required autofocus autocomplete="one-time-code" inputmode="text" class="input w-full font-mono tracking-widest" />
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ __('tardis::auth.two_factor_verify') }}</button>
    </form>

    <button type="button" wire:click="cancel" class="btn btn-ghost btn-block mt-2">{{ __('tardis::auth.two_factor_cancel') }}</button>
</div>
