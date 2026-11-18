<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

new #[Title('tardis::auth.reset_password')] #[Layout('tardis::layouts.auth')] class extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public ?string $status = null;

    public function mount(?string $token = null): void
    {
        $this->token = $token ?? '';
        // The mailed link carries the address as a query parameter.
        $email = request()->query('email');
        $this->email = is_string($email) ? $email : '';
    }

    public function resetPassword(): void
    {
        $this->status = null;

        $this->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'passwordConfirmation' => 'required|same:password',
        ], attributes: [
            'passwordConfirmation' => __('tardis::auth.confirm_password'),
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password, 'token' => $this->token],
            function ($user, $password) {
                // Hashed here, not left to a model cast: a host model without
                // the `hashed` cast would otherwise store the plain password.
                // A model with the cast sees an existing hash and keeps it.
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // One message for a bad token and an unknown address alike.
            $this->addError('email', __('tardis::auth.reset_link_invalid'));
            $this->reset('password', 'passwordConfirmation');

            return;
        }

        $this->status = __('tardis::auth.password_reset_done');
        $this->reset('password', 'passwordConfirmation');
    }
}; ?>

<div>
    <h2 class="card-title text-xl mb-4">{{ __('tardis::auth.reset_password') }}</h2>

    @if ($status)
        <div class="alert alert-success mb-4">
            <span>{{ $status }}</span>
        </div>
        <a href="{{ route('tardis.login') }}" class="btn btn-primary btn-block">{{ __('tardis::auth.login') }}</a>
    @else
        <form wire:submit="resetPassword" class="space-y-4">
            <input type="hidden" wire:model="token" />

            <div class="flex flex-col gap-2">
                <label class="label" for="email">
                    <span class="text-base-content">{{ __('tardis::auth.email_address') }}</span>
                </label>
                <input
                    type="email"
                    id="email"
                    wire:model="email"
                    required
                    placeholder="{{ __('tardis::auth.email_example_com') }}"
                    class="input w-full"
                />
                @error('email')
                    <label class="label">
                        <span class="text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="label" for="password">
                    <span class="text-base-content">{{ __('tardis::auth.new_password') }}</span>
                </label>
                <input
                    type="password"
                    id="password"
                    wire:model="password"
                    required
                    placeholder="••••••••"
                    class="input w-full"
                />
                @error('password')
                    <label class="label">
                        <span class="text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="label" for="password_confirmation">
                    <span class="text-base-content">{{ __('tardis::auth.confirm_password') }}</span>
                </label>
                <input
                    type="password"
                    id="password_confirmation"
                    wire:model="passwordConfirmation"
                    required
                    placeholder="••••••••"
                    class="input w-full"
                />
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                {{ __('tardis::auth.reset_password') }}
            </button>
        </form>
    @endif
</div>
