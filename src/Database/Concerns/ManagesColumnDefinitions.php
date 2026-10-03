<?php

declare(strict_types=1);

namespace Tardis\Database\Concerns;

use Illuminate\Database\Schema\Blueprint;

trait ManagesColumnDefinitions
{
    /** @var array<string, true> Whitelist of Blueprint column methods that are safe to call. */
    public const COLUMN_TYPES = [
        'bigInteger' => true,
        'boolean' => true,
        'date' => true,
        'dateTime' => true,
        'decimal' => true,
        'float' => true,
        'integer' => true,
        'json' => true,
        'longText' => true,
        'string' => true,
        'text' => true,
        'time' => true,
        'timestamp' => true,
        'uuid' => true,
    ];

    /** @return array<int, string> Column types usable in the create/edit forms. */
    public function columnTypes(): array
    {
        return array_keys(self::COLUMN_TYPES);
    }

    /** @return array{name: string, type: string, length: string, nullable: bool, default: string, primary: bool} */
    protected function emptyColumn(): array
    {
        return [
            'name' => '',
            'type' => 'string',
            'length' => '',
            'nullable' => false,
            'default' => '',
            'primary' => false,
        ];
    }

    /**
     * Framework tables (migrations, sessions, jobs...) and this package's own
     * tardis_* tables are never exposed: one wrong click in the explorer would
     * otherwise take the host application or the permission system down.
     */
    protected function isHiddenTable(string $name): bool
    {
        return str_starts_with($name, 'tardis_')
            || in_array($name, (array) config('tardis.database.hidden_tables', []), true);
    }

    protected function validateTableName(string $name): bool
    {
        if (! preg_match('/^[a-z][a-z0-9_]+$/', $name)) {
            $this->error = 'Table name must start with a letter and contain only lowercase letters, numbers and underscores.';

            return false;
        }

        return true;
    }

    protected function validateColumn(array $column, int $index): bool
    {
        if (! isset($column['name']) || ! preg_match('/^[a-z][a-z0-9_]+$/', (string) $column['name'])) {
            $this->error = 'Column #'.($index + 1).' name must start with a letter and contain only lowercase letters, numbers and underscores.';

            return false;
        }

        if (! isset(self::COLUMN_TYPES[$column['type']])) {
            $this->error = 'Unsupported column type ['.$column['type'].'].';

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, array{name: string, type: string, length: string, nullable: bool, default: string, primary: bool}>  $columns
     * @return array<int, array{name: string, type: string, length: string, nullable: bool, default: string, primary: bool}>
     */
    protected function normalizeColumns(array $columns): array
    {
        return array_values(array_filter($columns, fn (array $column) => $column['name'] !== '' && $column['name'] !== null));
    }

    protected function applyColumnDefinition(Blueprint $table, array $column, bool $primary = false, bool $modify = false): void
    {
        $method = $column['type'];

        if ($method === 'decimal') {
            $parts = array_values(array_filter(array_map('trim', explode(',', (string) ($column['length'] ?? '')))));
            $columnDef = count($parts) === 2
                ? $table->decimal($column['name'], (int) $parts[0], (int) $parts[1])
                : $table->decimal($column['name']);
        } elseif (in_array($method, ['string', 'integer', 'bigInteger', 'float'], true) && ($column['length'] ?? '') !== '') {
            $columnDef = $table->{$method}($column['name'], (int) $column['length']);
        } else {
            $columnDef = $table->{$method}($column['name']);
        }

        if ($primary) {
            $columnDef->primary();
        }

        if (! empty($column['nullable'])) {
            $columnDef->nullable();
        }

        if (($column['default'] ?? '') !== '') {
            $default = $column['default'] === 'null' ? null : $column['default'];
            $columnDef->default($default);
        }

        if ($modify) {
            $columnDef->change();
        }
    }

    /**
     * Annotate raw Schema::getColumns() rows with a MySQL-style Key marker
     * (PRI / UNI / '') derived from the table's indexes.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, array{name: string, columns: list<string>, type: string, unique: bool, primary: bool}>  $indexes
     * @return array<int, array<string, mixed>>
     */
    protected function withColumnKeys(array $columns, array $indexes): array
    {
        $keys = [];

        foreach ($indexes as $index) {
            $key = $index['primary'] ? 'PRI' : ($index['unique'] ? 'UNI' : '');
            foreach ($index['columns'] as $column) {
                $keys[$column] = $key;
            }
        }

        return array_map(function (array $column) use ($keys) {
            $column['key'] = $keys[$column['name']] ?? '';

            return $column;
        }, $columns);
    }

    protected function guessColumnType(string $name): string
    {
        foreach ($this->columns as $column) {
            if ($column['name'] === $name) {
                $type = strtolower((string) $column['type']);

                if (str_contains($type, 'int')) {
                    return str_contains($type, 'big') ? 'bigInteger' : 'integer';
                }
                if (str_starts_with($type, 'varchar')) {
                    return 'string';
                }
                if (str_contains($type, 'text')) {
                    return 'text';
                }
                if (str_contains($type, 'bool')) {
                    return 'boolean';
                }
                if (str_contains($type, 'date')) {
                    return str_contains($type, 'time') ? 'dateTime' : 'date';
                }
                if (str_contains($type, 'time')) {
                    return 'time';
                }
                if (str_contains($type, 'float') || str_contains($type, 'double')) {
                    return 'float';
                }
                if (str_contains($type, 'decimal') || str_contains($type, 'numeric')) {
                    return 'decimal';
                }
                if (str_contains($type, 'json')) {
                    return 'json';
                }
            }
        }

        return 'string';
    }

    protected function isColumnNullable(string $name): bool
    {
        foreach ($this->columns as $column) {
            if ($column['name'] === $name) {
                return (bool) ($column['nullable'] ?? false);
            }
        }

        return false;
    }

    protected function getColumnDefault(string $name): string
    {
        foreach ($this->columns as $column) {
            if ($column['name'] === $name) {
                $default = $column['default'] ?? null;

                return $default === null ? '' : (string) $default;
            }
        }

        return '';
    }
}
