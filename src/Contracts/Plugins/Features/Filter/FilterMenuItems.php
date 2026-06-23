<?php

namespace Tardis\Core\Contracts\Plugins\Features\Filter;

use Illuminate\Support\Collection;

interface FilterMenuItems
{
    public function filterMenuItems(Collection $items): Collection;
}
