<?php

declare(strict_types=1);

namespace Tardis\Contracts\Plugins\Features\Filter;

use Illuminate\Support\Collection;

interface FilterMenuItems
{
    public function filterMenuItems(Collection $items): Collection;
}
