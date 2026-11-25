<?php

declare(strict_types=1);

namespace Tardis\Plugins;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tardis\Contracts\Plugins\AuthenticationPlugin;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Contracts\Plugins\FormfieldPlugin;
use Tardis\Contracts\Plugins\GenericPlugin;
use Tardis\Contracts\Plugins\ThemePlugin;
use Tardis\State\UsesStateStore;
use Tardis\Support\FileSignature;

class PluginManager
{
    use UsesStateStore;

    protected Collection $plugins;

    protected array $enabled = [];

    /** Plugins switched on at boot unless an administrator turned them off. */
    protected array $defaults = [];

    protected array $disabled = [];

    /** The version of plugins.json $disabled was read from (FileSignature). */
    protected ?string $signature = null;

    protected string $statePath;

    /**
     * The on/off switches live in a file, not the cache: a `cache:clear` during
     * a deploy would otherwise silently re-enable every plugin an administrator
     * had switched off.
     */
    public function __construct(?string $statePath = null)
    {
        $this->plugins = collect();
        $this->ownFile = $statePath !== null;
        $this->statePath = $statePath ?? storage_path('tardis/plugins.json');
        $this->refreshDisabled();
    }

    /**
     * Re-read the switches when another process changed the file, so a
     * long-running worker follows a plugin turned on or off elsewhere.
     */
    protected function refreshDisabled(): void
    {
        $signature = $this->state()->signature($this->statePath);

        if ($signature !== $this->signature) {
            $this->signature = $signature;
            $this->disabled = $this->loadDisabled();
        }
    }

    protected function loadDisabled(): array
    {
        $data = $this->state()->read($this->statePath);

        if (! is_array($data) || ! is_array($data['disabled'] ?? null)) {
            return [];
        }

        // Before 0.2 the built-in authentication plugin was "tardis-auth".
        return array_values(array_unique(array_map(
            fn (string $name) => $name === 'tardis-auth' ? 'tardis-authentication' : $name,
            array_filter($data['disabled'], 'is_string'),
        )));
    }

    /**
     * Store one plugin's switch. The stored list is re-read under a lock and
     * only this plugin is added or removed, so a request that loaded the
     * list earlier cannot undo another request's change.
     */
    protected function persistDisabled(string $name, bool $disabled): void
    {
        try {
            $data = $this->state()->update($this->statePath, function (array $data) use ($name, $disabled) {
                $list = array_values(array_filter((array) ($data['disabled'] ?? []), 'is_string'));
                $list = array_values(array_diff($list, [$name]));

                if ($disabled) {
                    $list[] = $name;
                }

                return ['disabled' => $list];
            }, JSON_PRETTY_PRINT);

            $this->disabled = $data['disabled'];
            $this->signature = $this->state()->signature($this->statePath);
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
        $this->persistDisabled($name, false);
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
        // Remembered as a default rather than decided now: whether it is on
        // is read from the switches each time (isEnabled()).
        if (! in_array($name, $this->defaults, true)) {
            $this->defaults[] = $name;
        }
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
        $this->persistDisabled($name, true);
    }

    public function isEnabled(string $name): bool
    {
        $this->refreshDisabled();

        if (in_array($name, $this->disabled, true) && ! $this->isLocked($name)) {
            return false;
        }

        return (in_array($name, $this->enabled, true) || in_array($name, $this->defaults, true))
            && $this->plugins->has($name);
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

    /**
     * The plugin that signs users in. When more than one is enabled the one
     * registered last wins, so a host plugin overrides the built-in default.
     */
    public function authenticationPlugin(): ?AuthenticationPlugin
    {
        $plugin = $this->enabledWith(AuthenticationPlugin::class)->last();

        return $plugin instanceof AuthenticationPlugin ? $plugin : null;
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
