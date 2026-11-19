<?php

declare(strict_types=1);

namespace Tardis\Assets;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Vite;
use Tardis\Contracts\Plugins\Features\Provider\CSS;
use Tardis\Contracts\Plugins\Features\Provider\JS;
use Tardis\Plugins\PluginManager;
use Tardis\Settings\SettingsManager;
use Tardis\Theme\ThemeManager;

class AssetManager
{
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

    /** A built core file inside the package: resources/compiled/assets/{file}. */
    public static function compiledPath(string $file = ''): string
    {
        return dirname(__DIR__, 2).'/resources/compiled/assets'.($file === '' ? '' : '/'.$file);
    }

    /**
     * The core stylesheet and script as file assets: served from the package
     * through the content-hash route, so there is nothing to publish and an
     * upgraded package is picked up on the next page. The script is not
     * deferred, it has to run before Livewire starts Alpine.
     *
     * @return list<Asset>
     */
    public function coreAssets(): array
    {
        return [
            Asset::file(self::compiledPath('app.css'))->scope('both'),
            Asset::file(self::compiledPath('app.js'))->scope('both')->defer(false),
        ];
    }

    public function styles(string $scope = 'admin'): string
    {
        if (! $this->firstTime('styles')) {
            return '';
        }

        $html = '<!-- Tardis Styles -->'.PHP_EOL;

        // 1. Main CSS asset. Under `npm run dev` the Vite client comes along,
        // so stylesheet changes are applied without a reload.
        if ($this->isViteDevMode()) {
            $html .= '<script type="module" src="'.e($this->viteDevUrl().'/@vite/client').'"></script>'.PHP_EOL;
            $html .= '<link rel="stylesheet" href="'.e($this->viteDevUrl().'/resources/css/app.css').'">'.PHP_EOL;
        } else {
            $html .= $this->render($this->coreAssets()[0]);
        }

        // 1a. Every theme (built-in, plugin, custom); the stylesheet has none
        $themeCss = app(ThemeManager::class)->css();

        if ($themeCss !== '') {
            $html .= '<style'.$this->nonceAttribute().' id="tardis-themes">'.$themeCss.'</style>'.PHP_EOL;
        }

        // 1a. Custom CSS from Settings → appearance. Only the closing tag could
        // break out of the <style> element, so that is all that is removed.
        $custom = app(SettingsManager::class)->get('appearance.custom_css');

        if (is_string($custom) && trim($custom) !== '') {
            $html .= '<style'.$this->nonceAttribute().' id="tardis-custom-css">'.preg_replace('#</style#i', '', $custom).'</style>'.PHP_EOL;
        }

        // 1b. Assets registered through Tardis::addCss()
        foreach ($this->wanted($this->added('css'), $scope) as $asset) {
            $html .= $this->render($asset);
        }

        // 2. Plugin CSS providers (CSS interface)
        foreach ($this->pluginAssets('css') as $item) {
            $html .= $item instanceof Asset
                ? ($this->wanted([$item], $scope) === [] ? '' : $this->render($item))
                : '<style'.$this->nonceAttribute().'>'.$item.'</style>'.PHP_EOL;
        }

        // 4. Host-configured stylesheets come last so they can override the rest
        foreach ($this->configuredAssets('css') as $url) {
            $html .= '<link rel="stylesheet" href="'.e($url).'">'.PHP_EOL;
        }

        return $html;
    }

    public function scripts(string $scope = 'admin'): string
    {
        if (! $this->firstTime('scripts')) {
            return '';
        }

        $html = '<!-- Tardis Scripts -->'.PHP_EOL;

        // 0. The core script. It must run before Livewire starts Alpine, so it is a
        // plain (non-deferred) script placed ahead of @livewireScripts; it also copes
        // with Alpine having started already.
        $html .= $this->coreScript();

        // 0a. Assets registered through Tardis::addJs()
        foreach ($this->wanted($this->added('js'), $scope) as $asset) {
            $html .= $this->render($asset);
        }

        // 1. Plugin JS providers
        foreach ($this->pluginAssets('js') as $item) {
            $html .= $item instanceof Asset
                ? ($this->wanted([$item], $scope) === [] ? '' : $this->render($item))
                : '<script'.$this->nonceAttribute().'>'.$item.'</script>'.PHP_EOL;
        }

        // 2. Host-configured scripts
        foreach ($this->configuredAssets('js') as $url) {
            $html .= '<script src="'.e($url).'" defer></script>'.PHP_EOL;
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

    /**
     * A classic script in development too: the source has no imports, so Vite
     * serves it as it is, and it runs at the same point as the build does
     * (ahead of Livewire) instead of deferred like a module.
     */
    private function coreScript(): string
    {
        if ($this->isViteDevMode()) {
            return '<script src="'.e($this->viteDevUrl().'/resources/js/app.js').'"></script>'.PHP_EOL;
        }

        return $this->render($this->coreAssets()[1]);
    }

    /**
     * True the first time a block is asked for during the current request.
     *
     * The manager is a singleton because plugins register assets on it once,
     * at boot; whether a page already got its styles is a fact about one
     * request, so it is kept on the request, not here. A process that serves
     * another request (Octane, a worker rendering mail, a test) starts over.
     */
    private function firstTime(string $block): bool
    {
        $request = $this->app->bound('request') ? $this->app['request'] : null;

        if ($request === null) {
            return true;
        }

        $key = 'tardis.assets.rendered.'.$block;

        if ($request->attributes->get($key) === true) {
            return false;
        }

        $request->attributes->set($key, true);

        return true;
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
     * Everything the enabled plugins provide for one type: plain strings (inline
     * text) and Asset objects, in plugin order.
     *
     * @return list<Asset|string>
     */
    private function pluginAssets(string $type): array
    {
        $contract = $type === 'css' ? CSS::class : JS::class;
        $items = [];

        foreach ($this->plugins()->enabledWith($contract) as $plugin) {
            $provided = $type === 'css' ? $plugin->provideCSS() : $plugin->provideJS();

            foreach (is_array($provided) ? $provided : [$provided] as $item) {
                if ($item instanceof Asset || (is_string($item) && $item !== '')) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Every file-backed asset anything has registered, whatever the page: the
     * table the hashed asset route resolves against.
     *
     * @return list<Asset>
     */
    public function fileAssets(): array
    {
        $all = [...$this->coreAssets(), ...$this->added, ...array_filter($this->pluginAssets('css'), fn ($i) => $i instanceof Asset), ...array_filter($this->pluginAssets('js'), fn ($i) => $i instanceof Asset)];

        return array_values(array_filter($all, fn (Asset $asset) => $asset->file !== null));
    }

    /** The registered file asset behind a hash and extension, or null. */
    public function findByHash(string $hash, string $extension): ?Asset
    {
        foreach ($this->fileAssets() as $asset) {
            if ($asset->type === $extension && $asset->hash() === $hash) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * @param  list<Asset>  $assets
     * @return list<Asset>
     */
    private function wanted(array $assets, string $scope): array
    {
        $route = request()->route()?->getName();
        $can = fn (string $ability) => (bool) auth()->user()?->can($ability);

        return array_values(array_filter($assets, fn (Asset $asset) => $asset->wantedOn($scope, $route, $can)));
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

            return ($asset->type === 'css' ? '<style'.$this->nonceAttribute().'>'.$body.'</style>' : '<script'.$this->nonceAttribute().'>'.$body.'</script>').PHP_EOL;
        }

        if ($asset->file !== null) {
            $hash = $asset->hash();

            if ($hash === null) {
                return '';
            }

            $url = route('tardis.assets', ['hash' => $hash, 'extension' => $asset->type]);

            return $asset->type === 'css'
                ? '<link rel="stylesheet" href="'.e($url).'">'.PHP_EOL
                : '<script src="'.e($url).'"'.($asset->defer ? ' defer' : '').'></script>'.PHP_EOL;
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

    /**
     * The nonce attribute for inline blocks, so a strict Content-Security-Policy
     * can allow them: Laravel's Vite nonce when the host set one, else
     * `tardis.csp.nonce` (a string, or a closure returning one).
     */
    public function nonce(): ?string
    {
        $nonce = config('tardis.csp.nonce');

        if ($nonce instanceof \Closure) {
            $nonce = $nonce();
        }

        $nonce ??= Vite::cspNonce();

        return is_string($nonce) && preg_match('/^[A-Za-z0-9+\/=_-]+$/', $nonce) === 1 ? $nonce : null;
    }

    public function nonceAttribute(): string
    {
        $nonce = $this->nonce();

        return $nonce === null ? '' : ' nonce="'.$nonce.'"';
    }

    /**
     * Ask for an asset that only some pages need (a formfield's script). It is
     * written with the page's other assets, once, however many fields want it.
     */
    public function require(Asset $asset): void
    {
        foreach ($this->added as $existing) {
            if ($existing->type === $asset->type && $existing->file === $asset->file && $existing->url === $asset->url && $existing->inline === $asset->inline) {
                return;
            }
        }

        $this->added[] = $asset;
    }

    private function plugins(): PluginManager
    {
        return $this->app->make(PluginManager::class);
    }
}
