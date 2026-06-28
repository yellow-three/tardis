<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

new #[Title('Reset Password')] #[Layout('tardis::layouts.auth')] class extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public ?string $status = null;

    public function mount(?string $token = null, ?string $email = null): void
    {
        $this->token = $token ?? '';
        $this->email = $email ?? '';
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password, 'password_confirmation' => $this->passwordConfirmation, 'token' => $this->token],
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        $this->status = $status === Password::PASSWORD_RESET
            ? 'Your password has been reset successfully.'
            : __($status);
    }

    public function render()
    {
        return view('tardis::pages.reset-password');
    }
}; ?>

<div>
    <h2 class="card-title text-xl mb-4">Reset Password</h2>

    @if ($status)
        <div class="alert alert-success mb-4">
            <span>{{ $status }}</span>
        </div>
        <a href="{{ route('tardis.login') }}" class="btn btn-primary btn-block">Login</a>
    @else
        <form wire:submit="resetPassword" class="space-y-4">
            <input type="hidden" wire:model="token" />

            <div class="form-control">
                <label class="label" for="email">
                    <span class="label-text">Email address</span>
                </label>
                <input
                    type="email"
                    id="email"
                    wire:model="email"
                    required
                    placeholder="email@example.com"
                    class="input input-bordered w-full"
                />
                @error('email')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <div class="form-control">
                <label class="label" for="password">
                    <span class="label-text">New Password</span>
                </label>
                <input
                    type="password"
                    id="password"
                    wire:model="password"
                    required
                    placeholder="••••••••"
                    class="input input-bordered w-full"
                />
                @error('password')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <div class="form-control">
                <label class="label" for="password_confirmation">
                    <span class="label-text">Confirm Password</span>
                </label>
                <input
                    type="password"
                    id="password_confirmation"
                    wire:model="passwordConfirmation"
                    required
                    placeholder="••••••••"
                    class="input input-bordered w-full"
                />
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Reset Password
            </button>
        </form>
    @endif
</div>
