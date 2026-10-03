{{--
    Hands the resolved theme to the page.

    The server already wrote data-theme on <html> from the user's saved choice (or
    the administrator's default), so the first paint is correct without any script.
    This component adds the data the core script needs (themes, choice, where to
    save it) and, only for "system" mode, a blocking one-liner that picks the light
    or dark theme from the operating system before paint, because that is the one
    thing the server cannot know.
--}}
@php
    $tardisChoice = app(\Tardis\Theme\ThemePreference::class)->resolve(auth()->id());
    $tardisBoot = [
        'version' => \Tardis\Tardis::version(),
        'theme' => $tardisChoice + [
            'themes' => app(\Tardis\Manager\ThemeManager::class)->all()->map->toArray()->values()->all(),
            'saveUrl' => route('tardis.preferences.theme'),
            'csrf' => csrf_token(),
            'persist' => auth()->check(),
        ],
    ];
@endphp
<script>
    window.__TARDIS__ = @json($tardisBoot);
</script>
@if ($tardisChoice['mode'] === 'system')
    <script>
        (function () {
            var t = window.__TARDIS__.theme;
            document.documentElement.setAttribute('data-theme', window.matchMedia('(prefers-color-scheme: dark)').matches ? t.dark : t.light);
        })();
    </script>
@endif
