<?php

namespace Tardis\Bread\Sources;

use Illuminate\Support\Collection;

interface BreadSource
{
    public function find(string $slug): ?array;

    public function all(): Collection;
}
