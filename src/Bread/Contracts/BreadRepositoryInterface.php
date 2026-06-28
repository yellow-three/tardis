<?php

namespace Tardis\Bread\Contracts;

use Tardis\Bread\BreadDefinition;

interface BreadRepositoryInterface
{
    public function find(string $slug): ?BreadDefinition;

    public function all(): array;

    public function save(BreadDefinition $bread): void;

    public function delete(string $slug): void;

    public function exists(string $slug): bool;
}
