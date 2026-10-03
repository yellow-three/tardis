<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * One page of a BREAD listing together with how it was produced.
 */
final class BreadListing
{
    /** @param  array<int, string>  $warnings */
    public function __construct(
        public readonly LengthAwarePaginator $rows,
        public readonly float $executionMs,
        public readonly array $warnings,
    ) {}
}
