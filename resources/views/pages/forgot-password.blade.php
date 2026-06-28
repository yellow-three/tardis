<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Password;

new #[Title('Forgot Password')] #[Layout('tardis::layouts.auth')] class extends Component
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
            ? 'We have emailed your password reset link.'
            : __($status);
    }

    public function render()
    {
        return view('tardis::pages.forgot-password');
    }
}; ?>

<div>
    <h2 class="card-title text-xl mb-4">Forgot Password</h2>

    @if ($status)
        <div class="alert alert-info mb-4">
            <span>{{ $status }}</span>
        </div>
    @endif

    <p class="text-base-content/60 mb-4">Enter your email address and we'll send you a link to reset your password.</p>

    <form wire:submit="sendResetLink" class="space-y-4">
        <div class="form-control">
            <label class="label" for="email">
                <span class="label-text">Email address</span>
            </label>
            <input
                type="email"
                id="email"
                wire:model="email"
                required
                autofocus
                placeholder="email@example.com"
                class="input input-bordered w-full"
            />
            @error('email')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            Send Reset Link
        </button>
    </form>

    <div class="divider">OR</div>

    <p class="text-center text-sm">
        <a href="{{ route('tardis.login') }}" class="link link-primary">Back to Login</a>
    </p>
</div>
