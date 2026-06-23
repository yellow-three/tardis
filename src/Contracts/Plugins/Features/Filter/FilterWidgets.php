<?php

namespace Tardis\Core\Contracts\Plugins\Features\Filter;

use Illuminate\Support\Collection;

interface FilterWidgets
{
    public function filterWidgets(Collection $widgets): Collection;
}
