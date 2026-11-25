<!DOCTYPE html>
@php($tardisTheme = app(\Tardis\Theme\ThemePreference::class)->resolve(auth()->id()))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \Tardis\Support\Locales::direction() }}" data-theme="{{ $tardisTheme['mode'] === 'light' ? $tardisTheme['light'] : $tardisTheme['dark'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? __($title) : __('tardis::shell.brand.admin') }} - {{ __('tardis::shell.brand.name') }}</title>

    <x-tardis::head :theme="$tardisTheme" />
</head>
<body class="min-h-screen bg-base-200">
    <a href="#main-content" class="tardis-skip-link btn btn-primary btn-sm">{{ __('tardis::shell.skip_to_content') }}</a>

    <div class="drawer lg:drawer-open">
        <input id="tardis-drawer" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex flex-col min-h-screen">
            @if (session(\Tardis\Auth\Impersonation::SESSION_KEY))
                <form method="POST" action="{{ route('tardis.impersonation.leave') }}" class="flex flex-wrap items-center justify-center gap-3 bg-warning px-4 py-2 text-sm text-warning-content" role="status">
                    @csrf
                    <span>{{ __('tardis::shell.impersonating', ['name' => \Tardis\Support\PanelUser::name()]) }}</span>
                    <button type="submit" class="btn btn-xs">{{ __('tardis::shell.impersonation_leave') }}</button>
                </form>
            @endif
            <x-tardis::admin-header :title="isset($title) ? __($title) : __('tardis::shell.brand.admin')" />

            <main id="main-content" tabindex="-1" class="flex-1 p-4 lg:p-6">
                {{ $slot }}
            </main>

            <footer class="flex items-center justify-center border-t border-base-300 bg-base-100 px-4 py-3 text-xs text-base-content/50">
                <p>{{ __('tardis::shell.footer', ['year' => date('Y')]) }}</p>
            </footer>
        </div>

        <x-tardis::admin-sidebar />
    </div>

    <x-tardis::command-palette />
    <x-tardis::toasts />
    <x-tardis::loading-bar />

    <x-tardis::scripts />
</body>
</html>
