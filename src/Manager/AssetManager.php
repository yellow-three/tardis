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

    public function styles(): string
    {
        if ($this->stylesRendered) {
            return '';
        }
        $this->stylesRendered = true;

        $html = '<!-- TARDIS Styles -->'.PHP_EOL;

        // 1. Main CSS asset
        $html .= '<link rel="stylesheet" href="'.asset('vendor/tardis/assets/app.css').'">'.PHP_EOL;

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
