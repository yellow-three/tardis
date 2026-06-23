<x-tardis.auth-layout title="Log in">
    @if (session('status'))
        <div class="alert alert-success mb-4">
            <x-heroicon-o-check-circle class="w-5 h-5" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <div class="form-control">
            <label class="label" for="email">
                <span class="label-text">Email address</span>
            </label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
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
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="label-text-alt link link-hover">
                        Forgot password?
                    </a>
                @endif
            </label>
            <input
                type="password"
                id="password"
                name="password"
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
                    name="remember"
                    class="checkbox checkbox-primary checkbox-sm"
                    {{ old('remember') ? 'checked' : '' }}
                />
                <span class="label-text">Remember me</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            Log in
        </button>
    </form>

    @if (Route::has('register'))
        <div class="divider">OR</div>
        <p class="text-center text-sm">
            Don't have an account?
            <a href="{{ route('register') }}" class="link link-primary font-semibold">Sign up</a>
        </p>
    @endif
</x-tardis.auth-layout>
