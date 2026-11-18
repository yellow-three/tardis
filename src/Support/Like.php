<?php

declare(strict_types=1);

namespace Tardis\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * The operator for a search box: case-insensitive everywhere. MySQL's usual
 * collations and SQLite already compare LIKE without case; PostgreSQL's LIKE
 * does not, so it gets ILIKE there.
 */
final class Like
{
    public static function operator(Builder $query): string
    {
        $connection = $query->getConnection();

        return $connection instanceof Connection && $connection->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
