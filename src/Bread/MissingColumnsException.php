<?php

declare(strict_types=1);

namespace Tardis\Bread;

use RuntimeException;

/**
 * The table refuses NULL for columns the form left blank.
 */
class MissingColumnsException extends RuntimeException
{
    /** @param  array<int, string>  $columns */
    public function __construct(public readonly array $columns)
    {
        parent::__construct('Fields required by the database: '.implode(', ', $columns));
    }
}
