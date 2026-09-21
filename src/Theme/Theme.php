<?php

declare(strict_types=1);

namespace Tardis\Theme;

class Theme
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $description = '',
        public readonly string $type = 'light',
        /** @var array<string> OKLCH color strings for preview swatches */
        public readonly array $previewColors = [],
        public readonly bool $isCustom = false,
    ) {}
}
