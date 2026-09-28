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
            <x-tardis::icon name="check-circle" class="w-5 h-5" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div class="flex flex-col gap-2">
            <label class="label" for="email">
                <span class="text-base-content">Email address</span>
            </label>
            <input
                type="email"
                id="email"
                wire:model="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
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
                <span class="text-base-content">Password</span>
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
                <span class="text-base-content">Remember me</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            Log in
        </button>
    </form>
</div>
