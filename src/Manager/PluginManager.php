<?php

namespace Tardis\Core\Manager;

use Illuminate\Support\Collection;
use Tardis\Core\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Core\Contracts\Plugins\GenericPlugin;

class PluginManager
{
    protected Collection $plugins;

    protected array $enabled = [];

    protected array $disabled = [];

    public function __construct()
    {
        $this->plugins = collect();
        $this->disabled = $this->loadDisabled();
    }

    protected function loadDisabled(): array
    {
        try {
            return cache()->get('tardis.plugins.disabled', []);
        } catch (\Throwable) {
            return [];
        }
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
        $this->enabled[] = $name;
        $this->disabled = array_values(array_diff($this->disabled, [$name]));
        cache()->put('tardis.plugins.disabled', $this->disabled);
    }

    public function disable(string $name): void
    {
        if (! in_array($name, $this->disabled)) {
            $this->disabled[] = $name;
        }
        $this->enabled = array_values(array_diff($this->enabled, [$name]));
        cache()->put('tardis.plugins.disabled', $this->disabled);
    }

    public function isEnabled(string $name): bool
    {
        if (in_array($name, $this->disabled)) {
            return false;
        }

        if (! empty($this->enabled) && ! in_array($name, $this->enabled)) {
            return false;
        }

        return $this->plugins->has($name);
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
            $plugin instanceof AuthorizationPlugin => 'authorization',
            $plugin instanceof GenericPlugin => 'generic',
            default => 'unknown',
        };
    }
}
