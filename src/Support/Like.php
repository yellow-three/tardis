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
    /**
     * "<column> like" for a raw where clause, given the wrapped column. On
     * PostgreSQL the column is cast to text, so a numeric or date column can
     * be searched too instead of failing with "operator does not exist".
     */
    public static function clause(Builder $query, string $wrappedColumn): string
    {
        return self::operator($query) === 'ilike'
            ? 'CAST('.$wrappedColumn.' AS TEXT) ilike'
            : $wrappedColumn.' like';
    }

    public static function operator(Builder $query): string
    {
        $connection = $query->getConnection();

        return $connection instanceof Connection && $connection->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
