{{--
    Hands the resolved theme to the page.

    The server already wrote data-theme on <html> from the user's saved choice (or
    the administrator's default), so the first paint is correct without any script.
    This component adds the data the core script needs (themes, choice, where to
    save it) as a JSON data block, which a Content-Security-Policy does not treat
    as a script. Only "system" mode adds a blocking one-liner that picks the light
    or dark theme from the operating system before paint, the one thing the server
    cannot know.

    $choice is the resolved preference when the layout has it already.
--}}
@props(['choice' => null])
@php
    $tardisChoice = $choice ?? app(\Tardis\Theme\ThemePreference::class)->resolve(auth()->id());
    $tardisBoot = [
        'version' => \Tardis\TardisManager::version(),
        'theme' => $tardisChoice + [
            'themes' => app(\Tardis\Theme\ThemeManager::class)->all()->map->toArray()->values()->all(),
            'saveUrl' => route('tardis.preferences.theme'),
            'csrf' => csrf_token(),
            'persist' => auth()->check(),
        ],
    ];
@endphp
<script type="application/json" id="tardis-boot">@json($tardisBoot)</script>
@if ($tardisChoice['mode'] === 'system')
    <script{!! app(\Tardis\Assets\AssetManager::class)->nonceAttribute() !!}>
        (function () {
            var t = JSON.parse(document.getElementById('tardis-boot').textContent).theme;
            document.documentElement.setAttribute('data-theme', window.matchMedia('(prefers-color-scheme: dark)').matches ? t.dark : t.light);
        })();
    </script>
@endif
