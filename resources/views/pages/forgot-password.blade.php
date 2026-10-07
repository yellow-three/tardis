<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Password;

new #[Title('tardis::auth.forgot_password')] #[Layout('tardis::layouts.auth')] class extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(['email' => $this->email]);

        $this->status = $status === Password::RESET_LINK_SENT
            ? __('tardis::auth.reset_link_sent')
            : __($status);
    }

    public function render()
    {
        return view('tardis::pages.forgot-password');
    }
}; ?>

<div>
    <h2 class="card-title text-xl mb-4">{{ __('tardis::auth.forgot_password') }}</h2>

    @if ($status)
        <div class="alert alert-info mb-4">
            <span>{{ $status }}</span>
        </div>
    @endif

    <p class="text-base-content/60 mb-4">{{ __('tardis::auth.enter_your_email_address_and_we_112e') }}</p>

    <form wire:submit="sendResetLink" class="space-y-4">
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
                placeholder="{{ __('tardis::auth.email_example_com') }}"
                class="input w-full"
            />
            @error('email')
                <label class="label">
                    <span class="text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            {{ __('tardis::auth.send_reset_link') }}
        </button>
    </form>

    <div class="divider">{{ __('tardis::auth.or') }}</div>

    <p class="text-center text-sm">
        <a href="{{ route('tardis.login') }}" class="link link-primary">{{ __('tardis::auth.back_to_login') }}</a>
    </p>
</div>
