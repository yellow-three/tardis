<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data x-init="$store.theme.init()" data-theme="dark" :data-theme="$store.theme.applied">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? __($title) : __('tardis::shell.brand.admin') }} - {{ __('tardis::shell.brand.name') }}</title>

    <x-tardis::theme-boot />

    @tardisStyles
    @livewireStyles
</head>
<body class="min-h-screen bg-base-200">
    <a href="#main-content" class="tardis-skip-link btn btn-primary btn-sm">{{ __('tardis::shell.skip_to_content') }}</a>

    <div class="drawer lg:drawer-open">
        <input id="tardis-drawer" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex flex-col min-h-screen">
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

    @tardisScripts

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                mode: localStorage.getItem('tardis-theme-mode') || 'dark',
                light: localStorage.getItem('tardis-theme-light') || (window.__TARDIS_THEMES__?.find(t => t.colorScheme === 'light')?.name || 'winter'),
                dark: localStorage.getItem('tardis-theme-dark') || (window.__TARDIS_THEMES__?.find(t => t.colorScheme === 'dark')?.name || 'dark'),

                get applied() {
                    if (this.mode === 'system') {
                        return window.matchMedia('(prefers-color-scheme: dark)').matches ? this.dark : this.light
                    }

                    return this.mode === 'dark' ? this.dark : this.light
                },

                get availableThemes() {
                    return window.__TARDIS_THEMES__ || [];
                },

                get lightThemes() {
                    return this.availableThemes.filter(t => t.colorScheme === 'light');
                },

                get darkThemes() {
                    return this.availableThemes.filter(t => t.colorScheme === 'dark');
                },

                init() {
                    this.apply()

                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                        if (this.mode === 'system') this.apply()
                    })
                },

                apply() {
                    document.documentElement.setAttribute('data-theme', this.applied)
                    localStorage.setItem('tardis-theme-mode', this.mode)
                    localStorage.setItem('tardis-theme-light', this.light)
                    localStorage.setItem('tardis-theme-dark', this.dark)
                },

                toggle() {
                    this.mode = this.mode === 'dark' ? 'light' : 'dark'
                    this.apply()
                },
            })
        })
    </script>

    @livewireScripts
</body>
</html>
