<?php

declare(strict_types=1);

namespace Tardis\Theme;

use Illuminate\Support\Str;

/**
 * A named colour theme for the panel: a DaisyUI `data-theme` made of a colour
 * scheme and a handful of colour variables.
 *
 * Themes are data. fromArray() is the only way in and it refuses anything that
 * could escape the CSS rule it ends up in (names are [a-z0-9-], colours match a
 * short allow-list of formats, unknown colour keys are dropped), so a theme read
 * from a host's themes.json cannot inject markup or arbitrary CSS.
 */
final class Theme
{
    /** DaisyUI colour variables a theme may set, without the --color- prefix. */
    public const COLORS = [
        'primary', 'primary-content', 'secondary', 'secondary-content',
        'accent', 'accent-content', 'neutral', 'neutral-content',
        'base-100', 'base-200', 'base-300', 'base-content',
        'info', 'info-content', 'success', 'success-content',
        'warning', 'warning-content', 'error', 'error-content',
    ];

    /**
     * DaisyUI 5 shape variables, without the leading --, and DaisyUI's own
     * light-theme defaults. Components size and round themselves from these,
     * so every theme sets all of them.
     */
    public const SHAPE = [
        'radius-selector' => '0.5rem',
        'radius-field' => '0.25rem',
        'radius-box' => '0.5rem',
        'size-selector' => '0.25rem',
        'size-field' => '0.25rem',
        'border' => '1px',
        'depth' => '1',
        'noise' => '0',
    ];

    /** Without these a theme cannot render readable text on a surface. */
    public const REQUIRED = ['primary', 'base-100', 'base-content'];

    /**
     * @param  array<string, string>  $colors
     */
    private function __construct(
        public readonly string $name,
        public readonly string $scheme,
        public readonly string $label,
        public readonly array $colors,
        public readonly bool $builtin,
        public readonly array $shape = [],
        public readonly string $source = 'custom',
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): ?self
    {
        $name = $definition['name'] ?? null;
        $scheme = $definition['scheme'] ?? null;

        if (! is_string($name) || preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) !== 1) {
            return null;
        }

        if (! in_array($scheme, ['light', 'dark'], true)) {
            return null;
        }

        $colors = [];

        foreach ((array) ($definition['colors'] ?? []) as $key => $value) {
            if (! in_array($key, self::COLORS, true)) {
                continue;
            }

            if (! is_string($value) || ! self::isSafeColor($value)) {
                return null;
            }

            $colors[$key] = trim($value);
        }

        foreach (self::REQUIRED as $required) {
            if (! isset($colors[$required])) {
                return null;
            }
        }

        $shape = [];

        foreach ((array) ($definition['shape'] ?? []) as $key => $value) {
            if (! array_key_exists($key, self::SHAPE)) {
                continue;
            }

            if (! is_scalar($value) || ! self::isSafeLength((string) $value)) {
                return null;
            }

            $shape[$key] = trim((string) $value);
        }

        $label = isset($definition['label']) && is_string($definition['label']) && trim($definition['label']) !== ''
            ? trim($definition['label'])
            : Str::headline($name);

        $builtin = (bool) ($definition['builtin'] ?? false);
        $source = in_array($definition['source'] ?? null, ['builtin', 'plugin', 'custom'], true)
            ? $definition['source']
            : ($builtin ? 'builtin' : 'custom');

        return new self($name, $scheme, $label, $colors, $builtin, $shape, $source);
    }

    public static function isSafeColor(string $value): bool
    {
        $value = trim($value);

        return preg_match('/^(?:#[0-9a-f]{3,8}|(?:oklch|oklab|rgb|rgba|hsl|hsla|lab|lch)\([0-9a-z\s%.,\/+\-]+\)|[a-z]{3,20})$/i', $value) === 1;
    }

    /**
     * A length or a plain number: "0.5rem", "1px", "0", "1.25". Nothing that
     * could close the declaration it is written into.
     */
    public static function isSafeLength(string $value): bool
    {
        return preg_match('/^\d*\.?\d+(?:rem|em|px|%)?$/', trim($value)) === 1;
    }

    /**
     * The rule that makes this theme selectable. Every theme, built-in ones
     * included, is written from here; $default also makes it the theme of a
     * page that has no data-theme.
     */
    public function css(bool $default = false): string
    {
        $declarations = 'color-scheme:'.$this->scheme.';';

        foreach ($this->colors as $key => $value) {
            $declarations .= '--color-'.$key.':'.$value.';';
        }

        foreach ($this->shape + self::SHAPE as $key => $value) {
            $declarations .= '--'.$key.':'.$value.';';
        }

        $selector = '[data-theme="'.$this->name.'"]';

        return ($default ? ':where(:root),' : '').$selector.'{'.$declarations.'}';
    }

    /**
     * @return list<string> up to four colours for a swatch
     */
    public function previewColors(): array
    {
        $keys = ['primary', 'secondary', 'accent', 'base-100'];

        return array_values(array_filter(array_map(fn (string $key) => $this->colors[$key] ?? null, $keys)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'scheme' => $this->scheme,
            'label' => $this->label,
            'colors' => $this->colors,
            'preview' => $this->previewColors(),
            'builtin' => $this->builtin,
            'source' => $this->source,
            'shape' => $this->shape + self::SHAPE,
        ];
    }
}
