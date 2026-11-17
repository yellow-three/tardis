<?php

declare(strict_types=1);

namespace Tardis\Database\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tardis\Bread\ActivityLogger;

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
    /**
     * Raw column types (type_name, lower case) each editor type describes
     * exactly. A column whose raw type is not in its guessed type's list (a
     * tinyint(1) read as "integer", a timestamp read as "time", an enum read as
     * "string") cannot be re-declared from the editor without changing it.
     */
    protected const EXACT_TYPES = [
        'string' => ['varchar', 'character varying'],
        'integer' => ['int', 'integer', 'int4'],
        'bigInteger' => ['bigint', 'int8'],
        'text' => ['text'],
        'longText' => ['longtext'],
        'boolean' => ['boolean', 'bool'],
        'date' => ['date'],
        'dateTime' => ['datetime'],
        'timestamp' => ['timestamp'],
        'time' => ['time'],
        'float' => ['float'],
        'decimal' => ['decimal', 'numeric'],
        'json' => ['json', 'jsonb'],
        'uuid' => ['uuid'],
    ];

    /**
     * Schema changes run only where the host allows them (by default local and
     * testing), the same gate the command runner uses: in production the
     * explorer is read-only and the schema belongs to migrations.
     */
    public function schemaChangesAllowed(): bool
    {
        $environments = config('tardis.database.schema_changes.environments', ['local', 'testing']);

        return (bool) config('tardis.database.schema_changes.enabled', true)
            && is_array($environments)
            && $environments !== []
            && app()->environment(array_map('strval', $environments));
    }

    protected function guardSchemaChange(): bool
    {
        if ($this->schemaChangesAllowed()) {
            return true;
        }

        $this->error = __('tardis::database.schema_changes_disabled');

        return false;
    }

    /**
     * Record a schema change in the activity log. A missing log table is not a
     * reason to fail the change that already happened.
     *
     * @param  array<string, mixed>  $values
     */
    protected function auditSchema(string $action, array $values): void
    {
        if (! config('tardis.activity_log.enabled', true)) {
            return;
        }

        try {
            if (Schema::hasTable('activity_logs')) {
                app(ActivityLogger::class)->log('tardis.schema', 0, $action, newValues: $values);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not write a schema change to the activity log.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * The loaded definition of an existing column, as the editor reads it.
     *
     * @return array{type: string, length: string, nullable: bool, default: string}|null
     */
    protected function loadedColumn(string $name): ?array
    {
        foreach ($this->columns as $column) {
            if ($column['name'] === $name) {
                preg_match('/\((\d+(?:,\s*\d+)?)\)/', (string) $column['type'], $length);

                return [
                    'type' => $this->guessColumnType($name),
                    'length' => isset($length[1]) ? str_replace(' ', '', $length[1]) : '',
                    'nullable' => (bool) ($column['nullable'] ?? false),
                    'default' => ($column['default'] ?? null) === null ? '' : (string) $column['default'],
                ];
            }
        }

        return null;
    }

    /**
     * Can the editor re-declare this column without changing what it is?
     */
    protected function isExactlyEditable(string $name): bool
    {
        foreach ($this->columns as $column) {
            if ($column['name'] !== $name) {
                continue;
            }

            if (! empty($column['auto_increment'])
                || ($column['key'] ?? '') === 'PRI'
                || str_contains(strtolower((string) $column['type']), 'unsigned')) {
                return false;
            }

            $typeName = strtolower((string) ($column['type_name'] ?? strtok((string) $column['type'], '(')));

            return in_array($typeName, self::EXACT_TYPES[$this->guessColumnType($name)] ?? [], true);
        }

        return false;
    }

    /**
     * Rename and/or re-declare an existing column, touching only what changed.
     * A pure rename never re-declares the column; a definition change is
     * refused for a column the editor cannot describe exactly.
     *
     * @param  array<string, mixed>  $column  the edited values (name, type, length, nullable, default)
     * @return string|null "renamed", "updated", or null when nothing changed
     */
    protected function modifyColumn(string $table, string $original, array $column): ?string
    {
        $loaded = $this->loadedColumn($original);

        if ($loaded === null) {
            throw new \RuntimeException("Column [{$original}] does not exist.");
        }

        $renamed = $column['name'] !== $original;
        $changed = (string) $column['type'] !== $loaded['type']
            || (string) ($column['length'] ?? '') !== $loaded['length']
            || (bool) ($column['nullable'] ?? false) !== $loaded['nullable']
            || (string) ($column['default'] ?? '') !== $loaded['default'];

        if ($changed && ! $this->isExactlyEditable($original)) {
            throw new \DomainException(__('tardis::database.column_not_editable', ['column' => $original]));
        }

        $connection = config('database.default');

        if ($renamed) {
            Schema::connection($connection)->table($table, fn (Blueprint $t) => $t->renameColumn($original, $column['name']));
        }

        if ($changed) {
            Schema::connection($connection)->table($table, fn (Blueprint $t) => $this->applyColumnDefinition($t, $column, false, true));
        }

        if (! $renamed && ! $changed) {
            return null;
        }

        $this->auditSchema('column_updated', ['table' => $table, 'column' => $original] + ($renamed ? ['renamed_to' => $column['name']] : []) + ($changed ? ['definition' => array_intersect_key($column, array_flip(['type', 'length', 'nullable', 'default']))] : []));

        return $changed ? 'updated' : 'renamed';
    }

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
            $this->error = __('tardis::database.invalid_table_name');

            return false;
        }

        return true;
    }

    protected function validateColumn(array $column, int $index): bool
    {
        if (! isset($column['name']) || ! preg_match('/^[a-z][a-z0-9_]+$/', (string) $column['name'])) {
            $this->error = __('tardis::database.invalid_column_name', ['number' => $index + 1]);

            return false;
        }

        if (! isset(self::COLUMN_TYPES[$column['type']])) {
            $this->error = __('tardis::database.unsupported_column_type', ['type' => (string) $column['type']]);

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
