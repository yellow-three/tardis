<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Login')] #[Layout('tardis::layouts.auth')] class extends Component
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

    public function login(): void
    {
        $this->validate();

        if (auth()->attempt([
            'email' => $this->email,
            'password' => $this->password,
        ], $this->remember)) {
            session()->regenerate();
            $this->redirect(route('tardis.dashboard'));
        }

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
            <x-heroicon-o-check-circle class="w-5 h-5" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
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
                autocomplete="email"
                placeholder="email@example.com"
                class="input input-bordered w-full @error('email') input-error @enderror"
            />
            @error('email')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <div class="form-control">
            <label class="label" for="password">
                <span class="label-text">Password</span>
            </label>
            <input
                type="password"
                id="password"
                wire:model="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="input input-bordered w-full @error('password') input-error @enderror"
            />
            @error('password')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <div class="form-control">
            <label class="label cursor-pointer justify-start gap-3">
                <input
                    type="checkbox"
                    wire:model="remember"
                    class="checkbox checkbox-primary checkbox-sm"
                />
                <span class="label-text">Remember me</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            Log in
        </button>
    </form>
</div>
