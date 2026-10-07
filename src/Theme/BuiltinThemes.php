<?php

declare(strict_types=1);

namespace Tardis\Theme;

/**
 * The themes compiled into resources/css/app.css. They are listed here as well
 * so the server knows them without reading a build artifact (the manifest is
 * absent in a fresh checkout, in CI and on a host that never published assets);
 * ThemeTest checks that every entry is declared in the stylesheet.
 */
final class BuiltinThemes
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'tardis-light',
                'scheme' => 'light',
                'label' => 'Tardis Light',
                'builtin' => true,
                'colors' => [
                    'primary' => 'oklch(45% 0.2 260)',
                    'primary-content' => 'oklch(98% 0.01 260)',
                    'secondary' => 'oklch(55% 0.15 180)',
                    'secondary-content' => 'oklch(98% 0.01 180)',
                    'accent' => 'oklch(75% 0.18 80)',
                    'accent-content' => 'oklch(20% 0.05 80)',
                    'neutral' => 'oklch(45% 0.03 260)',
                    'neutral-content' => 'oklch(98% 0.01 260)',
                    'base-100' => 'oklch(97% 0.01 260)',
                    'base-200' => 'oklch(93% 0.015 260)',
                    'base-300' => 'oklch(88% 0.02 260)',
                    'base-content' => 'oklch(20% 0.05 260)',
                ],
            ],
            [
                'name' => 'tardis-dark',
                'scheme' => 'dark',
                'label' => 'Tardis Dark',
                'builtin' => true,
                'colors' => [
                    'primary' => 'oklch(50% 0.22 280)',
                    'primary-content' => 'oklch(98% 0.01 280)',
                    'secondary' => 'oklch(60% 0.18 195)',
                    'secondary-content' => 'oklch(98% 0.01 195)',
                    'accent' => 'oklch(72% 0.17 70)',
                    'accent-content' => 'oklch(20% 0.05 70)',
                    'neutral' => 'oklch(30% 0.02 260)',
                    'neutral-content' => 'oklch(95% 0.01 260)',
                    'base-100' => 'oklch(15% 0.015 260)',
                    'base-200' => 'oklch(20% 0.02 260)',
                    'base-300' => 'oklch(28% 0.025 260)',
                    'base-content' => 'oklch(92% 0.01 260)',
                ],
            ],
        ];
    }
}
