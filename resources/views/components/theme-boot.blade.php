{{--
    Applies the visitor's stored theme before the first paint.

    Alpine boots only after the stylesheet loads, so without this the static
    data-theme="dark" on <html> paints first and light-theme users see a dark
    flash (FOUC) on every full page load. This resolves from localStorage plus
    the themes manifest and writes data-theme synchronously, while the parser is
    still inside <head>.

    Shared by the admin and auth layouts deliberately. The head script and the
    Alpine store must resolve the SAME theme, so the resolution logic has to have
    exactly one home — duplicating it per layout is how the two drift apart, and a
    drifting store overwrites this on boot and brings the flash back.

    The localStorage key names must stay byte-identical to the Alpine store in the
    admin layout. That pairing is pinned by ThemeFoucGuardTest.
--}}
<script>
    window.__TARDIS_THEMES__ = @json(\Tardis\Manager\AssetManager::availableThemes());
</script>
<script>
    (function () {
        var themes = window.__TARDIS_THEMES__ || [];
        var pick = function (scheme) {
            var match = themes.find(function (t) { return t.colorScheme === scheme; });
            return match ? match.name : null;
        };

        var mode  = localStorage.getItem('tardis-theme-mode')  || 'dark';
        var light = localStorage.getItem('tardis-theme-light') || pick('light') || 'winter';
        var dark  = localStorage.getItem('tardis-theme-dark')  || pick('dark')  || 'dark';

        var applied;
        if (mode === 'system') {
            applied = window.matchMedia('(prefers-color-scheme: dark)').matches ? dark : light;
        } else {
            applied = mode === 'dark' ? dark : light;
        }

        document.documentElement.setAttribute('data-theme', applied);
    })();
</script>