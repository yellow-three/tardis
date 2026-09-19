<?php

declare(strict_types=1);

namespace Tardis\Manager;

use Tardis\Theme\Theme;

class ThemeManager
{
    /** @var array<string, Theme> */
    protected array $themes = [];

    /** @var string|null Code of the explicitly set default theme */
    protected ?string $defaultCode = null;

    /**
     * Register a theme manually.
     */
    public function register(
        string $code,
        string $name,
        string $description = '',
        string $type = 'light',
        array $previewColors = [],
        bool $isCustom = false,
    ): Theme {
        $theme = new Theme(
            code: $code,
            name: $name,
            description: $description,
            type: $type,
            previewColors: $previewColors,
            isCustom: $isCustom,
        );

        $this->themes[$code] = $theme;

        return $theme;
    }

    /**
     * Resolve a theme by its code.
     */
    public function resolve(string $code): ?Theme
    {
        return $this->themes[$code] ?? null;
    }

    /**
     * Get the default theme.
     *
     * Returns the theme marked as default in the manifest, or the first
     * registered theme if none is explicitly marked.
     */
    public function default(): ?Theme
    {
        if ($this->defaultCode !== null) {
            return $this->themes[$this->defaultCode] ?? null;
        }

        return array_values($this->themes)[0] ?? null;
    }

    /**
     * Get the currently active theme.
     *
     * Defaults to the default theme for now. This can be extended later
     * to respect a user preference or session value.
     */
    public function active(): Theme
    {
        return $this->default() ?? new Theme(
            code: 'tardis-light',
            name: 'TARDIS Light',
            type: 'light',
        );
    }

    /**
     * Get all registered themes.
     *
     * @return array<string, Theme>
     */
    public function themes(): array
    {
        return $this->themes;
    }

    /**
     * Load themes from a JSON manifest file.
     *
     * Expects the structure produced by the Vite plugin:
     * { "themes": [{ "name": "...", "colorScheme": "...", ... }] }
     *
     * @throws \InvalidArgumentException If the manifest file does not exist
     * @throws \RuntimeException If the JSON content is invalid
     */
    public function loadManifest(string $path): void
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("Theme manifest not found: {$path}");
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new \RuntimeException("Failed to read theme manifest: {$path}");
        }

        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['themes']) || ! is_array($data['themes'])) {
            throw new \RuntimeException("Invalid theme manifest structure: {$path}");
        }

        foreach ($data['themes'] as $themeData) {
            $code = $themeData['name'] ?? '';
            $type = $themeData['colorScheme'] ?? 'light';
            $previewColors = $themeData['previewColors'] ?? [];
            $isDefault = $themeData['default'] ?? false;

            $this->register(
                code: $code,
                name: $code,
                description: '',
                type: $type,
                previewColors: $previewColors,
            );

            if ($isDefault) {
                $this->defaultCode = $code;
            }
        }
    }

    /**
     * Load themes from a remote JSON manifest URL (e.g. Vite dev server).
     */
    public function loadManifestFromUrl(string $url): void
    {
        $context = stream_context_create(['http' => ['timeout' => 2]]);
        $json = @file_get_contents($url, false, $context);

        if ($json === false) {
            throw new \RuntimeException("Failed to fetch theme manifest from: {$url}");
        }

        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['themes']) || ! is_array($data['themes'])) {
            throw new \RuntimeException("Invalid theme manifest structure from: {$url}");
        }

        foreach ($data['themes'] as $themeData) {
            $code = $themeData['name'] ?? '';
            $type = $themeData['colorScheme'] ?? 'light';
            $previewColors = $themeData['previewColors'] ?? [];
            $isDefault = $themeData['default'] ?? false;

            $this->register(
                code: $code,
                name: $code,
                description: '',
                type: $type,
                previewColors: $previewColors,
            );

            if ($isDefault) {
                $this->defaultCode = $code;
            }
        }
    }

    /**
     * Get all registered themes as an associative array.
     *
     * Semantically identical to {@see themes()}, provided for
     * metadata-oriented consumers.
     *
     * @return array<string, Theme>
     */
    public function getThemesWithMetadata(): array
    {
        return $this->themes;
    }
}
