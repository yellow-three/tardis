<?php

declare(strict_types=1);

namespace Tardis\Bread\Import;

/** What a CSV import did, or why it did nothing. */
final class ImportResult
{
    /**
     * @param  array<int, list<string>>  $errors  row number (as in the file) => messages
     * @param  list<string>  $ignoredColumns  header cells that match no field
     */
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly array $errors = [],
        public readonly array $ignoredColumns = [],
    ) {}

    public function failed(): bool
    {
        return $this->errors !== [];
    }
}
