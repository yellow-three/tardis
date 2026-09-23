<?php

namespace Tardis\Bread\Sources;

use Illuminate\Support\Collection;
use Tardis\Bread\BreadDefinition;

interface BreadSource
{
    public function find(string $slug): ?BreadDefinition;

    public function all(): Collection;
}
