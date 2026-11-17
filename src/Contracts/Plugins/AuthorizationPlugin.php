<?php

declare(strict_types=1);

namespace Tardis\Contracts\Plugins;

interface AuthorizationPlugin
{
    public function name(): string;

    public function can(string $ability, mixed $model): bool;

    public function authorize(string $ability, mixed $model): void;
}
