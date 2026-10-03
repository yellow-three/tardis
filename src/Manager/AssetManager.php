<?php

declare(strict_types=1);

namespace Tardis\Manager;

use Illuminate\Contracts\Foundation\Application;
use Tardis\Contracts\Plugins\Features\Provider\CSS;
use Tardis\Contracts\Plugins\Features\Provider\JS;
use Tardis\Contracts\Plugins\ThemePlugin;

class AssetManager
{
    protected bool $stylesRendered = false;

    protected bool $scriptsRendered = false;

    public function __construct(
        private Application $app
    ) {}

    private function isViteDevMode(): bool
    {
        return file_exists(self::packageHotPath());
    }

    private function viteDevUrl(): string
    {
        return rtrim((string) file_get_contents(self::packageHotPath()), '/');
    }

    /**
     * Path to the hot file created by Vite in the package's own resources/ directory.
     * Using resource_path() would resolve to the HOST app's resources/hot, which is wrong
     * because the Vite dev server runs from the package directory.
     */
    public static function packageHotPath(): string
    {
        return dirname(__DIR__, 2).'/resources/hot';
    }

    /**
     * Path to the themes manifest in the package's own public/ directory.
     * In dev mode the Vite plugin writes the manifest here AND serves it via
     * middleware, so PHP can always read it from disk without needing to make
     * an HTTP request to the Vite dev server (which may not be reachable from
     * within Docker / Lerd).
     */
    public static function packageManifestPath(): string
    {
        return dirname(__DIR__, 2).'/public/tardis-assets/themes-manifest.json';
    }

    /**
     * Themes a visitor may pick, as declared by the themes manifest.
     *
     * Resolved from disk in production, and — when the Vite dev server is
     * running — from the server first with the package copy as fallback, since
     * the dev server may be unreachable from inside Docker / Lerd.
     *
     * Returns an empty list when no manifest is readable so callers degrade to
     * the built-in theme-name defaults rather than failing.
     *
     * @return list<array<string, mixed>>
     */
    public static function availableThemes(): array
    {
        if (file_exists(self::packageHotPath())) {
            $devUrl = rtrim((string) file_get_contents(self::packageHotPath()), '/');
            $json = @file_get_contents($devUrl.'/tardis-assets/themes-manifest.json');

            if ($json === false) {
                $json = @file_get_contents(self::packageManifestPath());
            }
        } else {
            $json = @file_get_contents(public_path('tardis-assets/themes-manifest.json'));
        }

        if ($json === false) {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! is_array($decoded['themes'] ?? null)) {
            return [];
        }

        return array_values(array_filter($decoded['themes'], 'is_array'));
    }

    /**
     * Short content hash appended to the published CSS URL as `?v=` so that
     * republishing the bundle busts browser and CDN caches. Vite emits a fixed
     * filename (assets/[name][extname]), so the URL is otherwise identical
     * across deploys and stale CSS can survive indefinitely.
     *
     * Returns null when the bundle is missing or unreadable — e.g. the host app
     * never ran `vendor:publish --tag=tardis-assets`. Callers then emit the bare
     * URL, degrading to the previous behaviour rather than advertising a version
     * that does not exist.
     */
    protected function publishedCssVersion(): ?string
    {
        $path = public_path('vendor/tardis/assets/app.css');

        if (! is_file($path)) {
            return null;
        }

        $hash = md5_file($path);

        return $hash === false ? null : substr($hash, 0, 8);
    }

    public function styles(): string
    {
        if ($this->stylesRendered) {
            return '';
        }
        $this->stylesRendered = true;

        $html = '<!-- TARDIS Styles -->'.PHP_EOL;

        // 1. Main CSS asset
        if ($this->isViteDevMode()) {
            $cssUrl = $this->viteDevUrl().'/resources/css/app.css';
        } else {
            $cssUrl = asset('vendor/tardis/assets/app.css');

            // Cache-bust only when the bundle is actually published, so a host
            // without `vendor:publish --tag=tardis-assets` keeps a valid URL.
            $version = $this->publishedCssVersion();

            if ($version !== null) {
                $cssUrl .= '?v='.$version;
            }
        }
        $html .= '<link rel="stylesheet" href="'.$cssUrl.'">'.PHP_EOL;

        // 1b. Host-configured stylesheets
        foreach ($this->configuredAssets('css') as $url) {
            $html .= '<link rel="stylesheet" href="'.e($url).'">'.PHP_EOL;
        }

        // 2. Plugin CSS providers (CSS interface)
        foreach ($this->plugins()->enabledWith(CSS::class) as $plugin) {
            $html .= '<style>'.$plugin->provideCSS().'</style>'.PHP_EOL;
        }

        // 3. ThemePlugin styles
        foreach ($this->plugins()->enabledWith(ThemePlugin::class) as $theme) {
            $html .= '<style>'.$theme->getStyles().'</style>'.PHP_EOL;
        }

        return $html;
    }

    public function scripts(): string
    {
        if ($this->scriptsRendered) {
            return '';
        }
        $this->scriptsRendered = true;

        $html = '<!-- TARDIS Scripts -->'.PHP_EOL;

        // 0. Host-configured scripts
        foreach ($this->configuredAssets('js') as $url) {
            $html .= '<script src="'.e($url).'" defer></script>'.PHP_EOL;
        }

        // 1. Plugin JS providers
        foreach ($this->plugins()->enabledWith(JS::class) as $plugin) {
            $html .= '<script>'.$plugin->provideJS().'</script>'.PHP_EOL;
        }

        return $html;
    }

    /**
     * URLs from tardis.assets.{css,js}. Only http(s) and root-relative URLs are
     * emitted, so a malformed or hostile config value (javascript:, data:)
     * cannot become executable markup.
     *
     * @return list<string>
     */
    private function configuredAssets(string $type): array
    {
        return array_values(array_filter(
            array_map('strval', (array) config('tardis.assets.'.$type, [])),
            fn (string $url) => preg_match('#^(https?://|/(?!/))#i', $url) === 1
        ));
    }

    private function plugins(): PluginManager
    {
        return $this->app->make(PluginManager::class);
    }
}
