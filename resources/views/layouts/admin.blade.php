<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data x-init="$store.theme.init()" data-theme="dark" :data-theme="$store.theme.applied">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ ($title ?? 'TARDIS Admin') }} - TARDIS</title>

    @tardisStyles
    @livewireStyles
</head>
<body class="min-h-screen bg-base-200">
    <div class="drawer lg:drawer-open">
        <input id="tardis-drawer" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex flex-col min-h-screen">
            <x-tardis::admin-header :title="$title ?? 'TARDIS Admin'" />

            <main class="flex-1 p-4 lg:p-6">
                {{ $slot }}
            </main>

            <footer class="footer footer-center bg-base-100 text-base-content/60 p-4 text-sm border-t border-base-300">
                <aside>
                    <p>© {{ date('Y') }} TARDIS Admin</p>
                </aside>
            </footer>
        </div>

        <x-tardis::admin-sidebar />
    </div>

    @tardisScripts

    @php
        $hotPath = \Tardis\Manager\AssetManager::packageHotPath();
        $manifestThemes = [];

        if (file_exists($hotPath)) {
            // Dev mode — try Vite dev server, fallback to package disk
            $viteUrl = rtrim((string) file_get_contents($hotPath), '/');
            $manifestJson = @file_get_contents($viteUrl.'/tardis-assets/themes-manifest.json');
            if ($manifestJson === false) {
                $packageManifest = \Tardis\Manager\AssetManager::packageManifestPath();
                if (file_exists($packageManifest)) {
                    $manifestJson = file_get_contents($packageManifest);
                }
            }
            if ($manifestJson !== false) {
                $manifestData = json_decode($manifestJson, true);
                $manifestThemes = $manifestData['themes'] ?? [];
            }
        } else {
            // Production — read from disk
            $manifestPath = public_path('tardis-assets/themes-manifest.json');
            if (file_exists($manifestPath)) {
                $manifestData = json_decode(file_get_contents($manifestPath), true);
                $manifestThemes = $manifestData['themes'] ?? [];
            }
        }
    @endphp

    <script>
        window.__TARDIS_THEMES__ = @json($manifestThemes);
    </script>

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
