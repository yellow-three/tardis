<?php

declare(strict_types=1);

namespace Tardis\Contracts\Plugins\Features\Filter;

use Illuminate\Support\Collection;

interface FilterWidgets
{
    public function filterWidgets(Collection $widgets): Collection;
}
