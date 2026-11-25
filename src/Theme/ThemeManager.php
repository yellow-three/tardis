<?php

declare(strict_types=1);

namespace Tardis\Theme;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tardis\Contracts\Plugins\ThemePlugin;
use Tardis\Plugins\PluginManager;
use Tardis\State\UsesStateStore;
use Tardis\Support\FileSignature;

/**
 * The panel's themes: the ones compiled into the stylesheet plus the ones a
 * host adds as data in storage/tardis/themes.json.
 *
 * Nothing is read until a theme is asked for, and an unreadable or invalid file
 * only means "no custom themes": a bad theme must never break a page.
 */
class ThemeManager
{
    use UsesStateStore;

    /** @var Collection<int, Theme>|null */
    protected ?Collection $themes = null;

    /** The version of themes.json the list was built from (FileSignature). */
    protected ?string $signature = null;

    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->ownFile = $path !== null;
        $this->path = $path ?? storage_path('tardis/themes.json');
    }

    /**
     * @return Collection<int, Theme> built-in first, then custom, in file order
     */
    public function all(): Collection
    {
        // Another worker may have saved a theme since: rebuild when the file
        // changed, so a long-running process never serves a stale list.
        $signature = $this->state()->signature($this->path);

        if ($this->themes !== null && $this->signature === $signature) {
            return $this->themes;
        }

        $this->signature = $signature;

        $themes = collect(BuiltinThemes::all())
            ->map(fn (array $definition) => Theme::fromArray($definition + ['source' => 'builtin']))
            ->filter()
            ->values();

        foreach ($this->pluginDefinitions() as $definition) {
            $theme = is_array($definition) ? Theme::fromArray(['builtin' => false] + $definition + ['source' => 'plugin']) : null;

            if ($theme === null || $themes->contains(fn (Theme $existing) => $existing->name === $theme->name)) {
                Log::notice('Skipping an invalid or duplicate plugin theme.', ['theme' => is_array($definition) ? ($definition['name'] ?? null) : null]);

                continue;
            }

            $themes->push($theme);
        }

        foreach ($this->readCustom() as $definition) {
            $theme = is_array($definition) ? Theme::fromArray($definition) : null;

            if ($theme === null || $themes->contains(fn (Theme $existing) => $existing->name === $theme->name)) {
                Log::notice('Skipping an invalid or duplicate custom theme.', ['theme' => is_array($definition) ? ($definition['name'] ?? null) : null]);

                continue;
            }

            $themes->push($theme);
        }

        return $this->themes = $themes->values();
    }

    public function find(string $name): ?Theme
    {
        return $this->all()->first(fn (Theme $theme) => $theme->name === $name);
    }

    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    /**
     * @return list<string>
     */
    public function names(?string $scheme = null): array
    {
        return $this->all()
            ->filter(fn (Theme $theme) => $scheme === null || $theme->scheme === $scheme)
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * The CSS of every theme. This is the only place themes become CSS: the
     * stylesheet carries none, so a built-in theme is defined once (in
     * BuiltinThemes). The first light built-in is also the theme of a page
     * without data-theme.
     */
    public function css(): string
    {
        $default = $this->all()->first(fn (Theme $theme) => $theme->builtin && $theme->scheme === 'light');

        return $this->all()
            ->map(fn (Theme $theme) => $theme->css($theme === $default))
            ->implode('');
    }

    /**
     * Theme definitions offered by enabled ThemePlugins.
     *
     * @return list<mixed>
     */
    protected function pluginDefinitions(): array
    {
        $definitions = [];

        foreach (app(PluginManager::class)->enabledWith(ThemePlugin::class) as $plugin) {
            try {
                foreach ((array) $plugin->themes() as $definition) {
                    $definitions[] = $definition;
                }
            } catch (\Throwable $e) {
                Log::warning('A theme plugin failed to list its themes.', ['plugin' => $plugin::class, 'error' => $e->getMessage()]);
            }
        }

        return $definitions;
    }

    /**
     * Add or replace a custom theme. Built-in names and invalid definitions are refused.
     *
     * @param  array<string, mixed>  $definition
     */
    public function saveCustom(array $definition): bool
    {
        $theme = Theme::fromArray(['builtin' => false] + $definition);

        if ($theme === null || collect(BuiltinThemes::all())->contains(fn (array $builtin) => $builtin['name'] === $theme->name)) {
            return false;
        }

        $entry = array_filter(['name' => $theme->name, 'scheme' => $theme->scheme, 'label' => $theme->label, 'colors' => $theme->colors, 'shape' => $theme->shape]);

        return $this->changeCustom(fn (array $custom) => collect($custom)
            ->reject(fn ($existing) => is_array($existing) && ($existing['name'] ?? null) === $theme->name)
            ->push($entry)
            ->values()
            ->all());
    }

    public function deleteCustom(string $name): bool
    {
        $isNamed = fn ($existing) => is_array($existing) && ($existing['name'] ?? null) === $name;

        if (! collect($this->readCustom())->contains($isNamed)) {
            return false;
        }

        return $this->changeCustom(fn (array $custom) => array_values(array_filter($custom, fn ($existing) => ! $isNamed($existing))));
    }

    /**
     * @return array<int, mixed>
     */
    protected function readCustom(): array
    {
        $data = $this->state()->read($this->path);

        return is_array($data['themes'] ?? null) ? array_values($data['themes']) : [];
    }

    /**
     * Change the custom themes under the file's lock, starting from what is
     * on disk now, so two editors saving at once both keep their theme.
     *
     * @param  callable(array<int, mixed>): array<int, mixed>  $change
     */
    protected function changeCustom(callable $change): bool
    {
        try {
            $this->state()->update($this->path, function (array $data) use ($change) {
                $custom = is_array($data['themes'] ?? null) ? array_values($data['themes']) : [];

                return ['themes' => $change($custom)];
            });

            $this->themes = null;

            return true;
        } catch (\Throwable $e) {
            Log::warning('Could not persist custom themes.', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
