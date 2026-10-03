<?php

declare(strict_types=1);

namespace Tardis\Manager;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tardis\Theme\BuiltinThemes;
use Tardis\Theme\Theme;

/**
 * The panel's themes: the ones compiled into the stylesheet plus the ones a
 * host adds as data in storage/tardis/themes.json.
 *
 * Nothing is read until a theme is asked for, and an unreadable or invalid file
 * only means "no custom themes": a bad theme must never break a page.
 */
class ThemeManager
{
    /** @var Collection<int, Theme>|null */
    protected ?Collection $themes = null;

    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('tardis/themes.json');
    }

    /**
     * @return Collection<int, Theme> built-in first, then custom, in file order
     */
    public function all(): Collection
    {
        if ($this->themes !== null) {
            return $this->themes;
        }

        $themes = collect(BuiltinThemes::all())
            ->map(fn (array $definition) => Theme::fromArray($definition))
            ->filter()
            ->values();

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
     * CSS for the runtime themes only; the built-in ones are in the stylesheet.
     */
    public function css(): string
    {
        return $this->all()
            ->reject(fn (Theme $theme) => $theme->builtin)
            ->map(fn (Theme $theme) => $theme->css())
            ->implode('');
    }

    /**
     * Add or replace a custom theme. Built-in names and invalid definitions are refused.
     *
     * @param  array<string, mixed>  $definition
     */
    public function saveCustom(array $definition): bool
    {
        $theme = Theme::fromArray($definition);

        if ($theme === null || collect(BuiltinThemes::all())->contains(fn (array $builtin) => $builtin['name'] === $theme->name)) {
            return false;
        }

        $custom = collect($this->readCustom())
            ->reject(fn ($existing) => is_array($existing) && ($existing['name'] ?? null) === $theme->name)
            ->push(['name' => $theme->name, 'scheme' => $theme->scheme, 'label' => $theme->label, 'colors' => $theme->colors])
            ->values()
            ->all();

        return $this->writeCustom($custom);
    }

    public function deleteCustom(string $name): bool
    {
        $custom = $this->readCustom();
        $remaining = array_values(array_filter($custom, fn ($existing) => ! (is_array($existing) && ($existing['name'] ?? null) === $name)));

        if (count($remaining) === count($custom)) {
            return false;
        }

        return $this->writeCustom($remaining);
    }

    /**
     * @return array<int, mixed>
     */
    protected function readCustom(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->path), true);

        return is_array($data) && is_array($data['themes'] ?? null) ? array_values($data['themes']) : [];
    }

    /**
     * @param  array<int, mixed>  $themes
     */
    protected function writeCustom(array $themes): bool
    {
        try {
            $dir = dirname($this->path);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $tmp = tempnam($dir, '.themes-');
            file_put_contents($tmp, json_encode(['themes' => $themes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
            rename($tmp, $this->path);

            $this->themes = null;

            return true;
        } catch (\Throwable $e) {
            Log::warning('Could not persist custom themes.', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
