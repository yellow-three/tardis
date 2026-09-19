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
        }
        $html .= '<link rel="stylesheet" href="'.$cssUrl.'">'.PHP_EOL;

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

        // 1. Plugin JS providers
        foreach ($this->plugins()->enabledWith(JS::class) as $plugin) {
            $html .= '<script>'.$plugin->provideJS().'</script>'.PHP_EOL;
        }

        return $html;
    }

    private function plugins(): PluginManager
    {
        return $this->app->make(PluginManager::class);
    }
}
