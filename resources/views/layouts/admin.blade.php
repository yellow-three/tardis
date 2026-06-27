<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data x-init="$store.theme.init()" :data-theme="$store.theme.applied">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ ($title ?? 'TARDIS Admin') }} - TARDIS</title>

    @vite(['packages/Tardis/Core/resources/css/app.css', 'packages/Tardis/Core/resources/js/app.js'])
    @livewireStyles

    @foreach(app(\Tardis\Manager\PluginManager::class)->enabledWith(\Tardis\Contracts\Plugins\Features\Provider\CSS::class) as $plugin)
        <style>{!! $plugin->provideCSS() !!}</style>
    @endforeach
</head>
<body class="min-h-screen bg-base-200">
    <div class="drawer lg:drawer-open">
        <input id="tardis-drawer" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex flex-col">
            <x-tardis::admin-header :title="$title ?? 'TARDIS Admin'" />

            <main class="flex-1 p-6">
                {{ $slot }}
            </main>
        </div>

        <x-tardis::admin-sidebar />
    </div>

    @foreach(app(\Tardis\Manager\PluginManager::class)->enabledWith(\Tardis\Contracts\Plugins\Features\Provider\JS::class) as $plugin)
        <script>{!! $plugin->provideJS() !!}</script>
    @endforeach

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                mode: localStorage.getItem('tardis-theme-mode') || 'dark',
                light: localStorage.getItem('tardis-theme-light') || 'winter',
                dark: localStorage.getItem('tardis-theme-dark') || 'dark',

                get applied() {
                    if (this.mode === 'system') {
                        return window.matchMedia('(prefers-color-scheme: dark)').matches ? this.dark : this.light
                    }

                    return this.mode === 'dark' ? this.dark : this.light
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
