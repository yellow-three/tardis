<?php

declare(strict_types=1);

namespace Tardis\Manager;

use Illuminate\Contracts\Foundation\Application;
use Tardis\Assets\Asset;
use Tardis\Contracts\Plugins\Features\Provider\CSS;
use Tardis\Contracts\Plugins\Features\Provider\JS;
use Tardis\Contracts\Plugins\ThemePlugin;

class AssetManager
{
    protected bool $stylesRendered = false;

    protected bool $scriptsRendered = false;

    /** @var list<Asset> */
    protected array $added = [];

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
        return $this->publishedVersion('app.css');
    }

    protected function publishedVersion(string $file): ?string
    {
        $path = public_path('vendor/tardis/assets/'.$file);

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

        // 1a. Runtime themes (the built-in ones are in the stylesheet)
        $themeCss = app(ThemeManager::class)->css();

        if ($themeCss !== '') {
            $html .= '<style id="tardis-themes">'.$themeCss.'</style>'.PHP_EOL;
        }

        // 1b. Assets registered through Tardis::addCss()
        foreach ($this->added('css') as $asset) {
            $html .= $this->render($asset);
        }

        // 2. Plugin CSS providers (CSS interface)
        foreach ($this->plugins()->enabledWith(CSS::class) as $plugin) {
            $html .= '<style>'.$plugin->provideCSS().'</style>'.PHP_EOL;
        }

        // 3. ThemePlugin variables
        foreach ($this->plugins()->enabledWith(ThemePlugin::class) as $theme) {
            $rule = $this->themeRule($theme->getTheme());

            if ($rule !== '') {
                $html .= '<style>'.$rule.'</style>'.PHP_EOL;
            }
        }

        // 4. Host-configured stylesheets come last so they can override the rest
        foreach ($this->configuredAssets('css') as $url) {
            $html .= '<link rel="stylesheet" href="'.e($url).'">'.PHP_EOL;
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

        // 0. The core script. It must run before Livewire starts Alpine, so it is a
        // plain (non-deferred) script placed ahead of @livewireScripts; it also copes
        // with Alpine having started already.
        $html .= $this->coreScript();

        // 0a. Assets registered through Tardis::addJs()
        foreach ($this->added('js') as $asset) {
            $html .= $this->render($asset);
        }

        // 1. Plugin JS providers
        foreach ($this->plugins()->enabledWith(JS::class) as $plugin) {
            $html .= '<script>'.$plugin->provideJS().'</script>'.PHP_EOL;
        }

        // 2. Host-configured scripts
        foreach ($this->configuredAssets('js') as $url) {
            $html .= '<script src="'.e($url).'" defer></script>'.PHP_EOL;
        }

        return $html;
    }

    /**
     * Turn theme variables into a :root rule. Names must be custom properties
     * and values may only contain characters a colour, length or number needs,
     * so a value cannot close the rule or the <style> element.
     *
     * @param  array<string, mixed>  $variables
     */
    private function themeRule(array $variables): string
    {
        $declarations = '';

        foreach ($variables as $name => $value) {
            $value = trim((string) $value);

            if (preg_match('/^--[a-z0-9][a-z0-9_-]*$/i', (string) $name) !== 1
                || preg_match('/^[#a-z0-9%.,()\/\s_+-]+$/i', $value) !== 1
                || stripos($value, 'url(') !== false
                || stripos($value, 'javascript') !== false) {
                continue;
            }

            $declarations .= $name.':'.$value.';';
        }

        return $declarations === '' ? '' : ':root{'.$declarations.'}';
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

    private function coreScript(): string
    {
        if ($this->isViteDevMode()) {
            return '<script type="module" src="'.e($this->viteDevUrl().'/resources/js/app.js').'"></script>'.PHP_EOL;
        }

        $url = asset('vendor/tardis/assets/app.js');
        $version = $this->publishedVersion('app.js');

        return '<script src="'.e($url.($version !== null ? '?v='.$version : '')).'"></script>'.PHP_EOL;
    }

    /**
     * Register a stylesheet. A plain string is a URL.
     */
    public function addCss(Asset|string $asset): void
    {
        $this->added[] = is_string($asset) ? Asset::css($asset) : $asset;
    }

    /**
     * Register a script. A plain string is a URL.
     */
    public function addJs(Asset|string $asset): void
    {
        $this->added[] = is_string($asset) ? Asset::js($asset) : $asset;
    }

    /**
     * @return list<Asset>
     */
    private function added(string $type): array
    {
        return array_values(array_filter($this->added, fn (Asset $asset) => $asset->type === $type));
    }

    private function render(Asset $asset): string
    {
        if ($asset->inline !== null) {
            // An inline block must not be able to close its own element.
            $body = str_ireplace(['</style', '</script'], ['<\\/style', '<\\/script'], $asset->inline);

            return ($asset->type === 'css' ? '<style>'.$body.'</style>' : '<script>'.$body.'</script>').PHP_EOL;
        }

        if ($asset->url === null || ! $this->isSafeUrl($asset->url)) {
            return '';
        }

        if ($asset->type === 'css') {
            return '<link rel="stylesheet" href="'.e($asset->url).'"'.$this->integrityAttributes($asset).'>'.PHP_EOL;
        }

        return '<script src="'.e($asset->url).'"'.($asset->defer ? ' defer' : '').$this->integrityAttributes($asset).'></script>'.PHP_EOL;
    }

    private function isSafeUrl(string $url): bool
    {
        return preg_match('#^(https?://|/(?!/))#i', $url) === 1;
    }

    private function integrityAttributes(Asset $asset): string
    {
        if ($asset->integrity === null || preg_match('/^sha(256|384|512)-[A-Za-z0-9+\/=]+$/', $asset->integrity) !== 1) {
            return '';
        }

        return ' integrity="'.$asset->integrity.'" crossorigin="anonymous"';
    }

    private function plugins(): PluginManager
    {
        return $this->app->make(PluginManager::class);
    }
}
