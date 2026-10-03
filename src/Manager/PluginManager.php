<?php

namespace Tardis\Manager;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tardis\Contracts\Plugins\AuthenticationPlugin;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Contracts\Plugins\FormfieldPlugin;
use Tardis\Contracts\Plugins\GenericPlugin;
use Tardis\Contracts\Plugins\ThemePlugin;

class PluginManager
{
    protected Collection $plugins;

    protected array $enabled = [];

    protected array $disabled = [];

    protected string $statePath;

    /**
     * The on/off switches live in a file, not the cache: a `cache:clear` during
     * a deploy would otherwise silently re-enable every plugin an administrator
     * had switched off.
     */
    public function __construct(?string $statePath = null)
    {
        $this->plugins = collect();
        $this->statePath = $statePath ?? storage_path('tardis/plugins.json');
        $this->disabled = $this->loadDisabled();
    }

    protected function loadDisabled(): array
    {
        if (! is_file($this->statePath)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->statePath), true);

        return is_array($data) && is_array($data['disabled'] ?? null)
            ? array_values(array_filter($data['disabled'], 'is_string'))
            : [];
    }

    protected function persistDisabled(): void
    {
        try {
            $dir = dirname($this->statePath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $tmp = tempnam($dir, '.plugins-');
            file_put_contents($tmp, json_encode(['disabled' => $this->disabled], JSON_PRETTY_PRINT)."\n");
            rename($tmp, $this->statePath);
        } catch (\Throwable $e) {
            Log::warning('Could not persist the plugin state.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Authentication and authorization plugins guard the panel itself, so the
     * UI must not be able to switch them off (it would remove every check).
     */
    public function isLocked(string $name): bool
    {
        $instance = $this->plugins->get($name)['instance'] ?? null;

        return $instance instanceof AuthenticationPlugin || $instance instanceof AuthorizationPlugin;
    }

    public function register(string $name, string $pluginClass): void
    {
        if (! class_exists($pluginClass)) {
            return;
        }

        $plugin = app($pluginClass);

        $this->plugins->put($name, [
            'class' => $pluginClass,
            'instance' => $plugin,
            'type' => $this->resolveType($plugin),
        ]);
    }

    public function enable(string $name): void
    {
        if (! in_array($name, $this->enabled, true)) {
            $this->enabled[] = $name;
        }

        $this->disabled = array_values(array_diff($this->disabled, [$name]));
        $this->persistDisabled();
    }

    /**
     * Turn a plugin on at boot unless an administrator switched it off.
     *
     * enable() is an explicit user action: it clears the stored disable and
     * writes the cache. Calling it from a service provider would therefore undo
     * every "disable" made on the Plugins page on the next request, and write
     * to the cache on every request. Boot-time defaults go through here.
     */
    public function enableByDefault(string $name): void
    {
        if ((in_array($name, $this->disabled, true) && ! $this->isLocked($name)) || in_array($name, $this->enabled, true)) {
            return;
        }

        $this->enabled[] = $name;
    }

    public function disable(string $name): void
    {
        if ($this->isLocked($name)) {
            throw new \LogicException("Plugin [{$name}] protects the panel and cannot be disabled.");
        }

        if (! in_array($name, $this->disabled, true)) {
            $this->disabled[] = $name;
        }

        $this->enabled = array_values(array_diff($this->enabled, [$name]));
        $this->persistDisabled();
    }

    public function isEnabled(string $name): bool
    {
        if (in_array($name, $this->disabled, true) && ! $this->isLocked($name)) {
            return false;
        }

        return in_array($name, $this->enabled, true) && $this->plugins->has($name);
    }

    public function all(): Collection
    {
        return $this->plugins;
    }

    public function enabled(): Collection
    {
        return $this->plugins->filter(fn ($plugin, $name) => $this->isEnabled($name));
    }

    public function get(string $name): ?object
    {
        if (! $this->isEnabled($name)) {
            return null;
        }

        $plugin = $this->plugins->get($name);

        return $plugin['instance'] ?? null;
    }

    public function authorizationPlugins(): Collection
    {
        return $this->enabled()->filter(fn ($plugin) => $plugin['type'] === 'authorization');
    }

    public function genericPlugins(): Collection
    {
        return $this->enabled()->filter(fn ($plugin) => $plugin['type'] === 'generic');
    }

    public function enabledWith(string $interface): Collection
    {
        return $this->enabled()->filter(
            fn (array $plugin) => $plugin['instance'] instanceof $interface
        )->map(fn (array $plugin) => $plugin['instance']);
    }

    public function resolveType(object $plugin): string
    {
        return match (true) {
            $plugin instanceof AuthenticationPlugin => 'authentication',
            $plugin instanceof AuthorizationPlugin => 'authorization',
            $plugin instanceof FormfieldPlugin => 'formfield',
            $plugin instanceof ThemePlugin => 'theme',
            $plugin instanceof GenericPlugin => 'generic',
            default => 'unknown',
        };
    }

    public function getPluginInfo(string $name): ?array
    {
        $plugin = $this->plugins->get($name);
        if (! $plugin) {
            return null;
        }

        $instance = $plugin['instance'];
        $version = null;

        if (method_exists($instance, 'version')) {
            $version = $instance->version();
        }

        $composerPath = dirname((new \ReflectionClass($instance))->getFileName()).'/../../composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            $version = $version ?? $composer['version'] ?? null;
        }

        return [
            'name' => $name,
            'class' => $plugin['class'],
            'type' => $plugin['type'],
            'enabled' => $this->isEnabled($name),
            'version' => $version,
            'description' => method_exists($instance, 'description') ? $instance->description() : null,
        ];
    }

    public function allWithInfo(): Collection
    {
        return $this->plugins->map(fn ($plugin, $name) => $this->getPluginInfo($name));
    }
}
