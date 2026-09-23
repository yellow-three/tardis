<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Database\ModelGenerator;

new #[Title('Database Explorer')] #[Layout('tardis::layouts.admin')] class extends Component
{
    /** @var array<int, array{name: string, has_model: bool}> */
    public array $tables = [];

    public ?string $selectedTable = null;

    /** @var array<int, array<string, mixed>> */
    public array $columns = [];

    public int $totalRows = 0;

    public ?string $error = null;

    public bool $showCreateTableModal = false;

    public string $newTableName = '';

    /** @var array<int, array{name: string, type: string, length: string, nullable: bool, default: string, primary: bool}> */
    public array $newTableColumns = [];

    public bool $createAutoId = true;

    public bool $createTimestamps = true;

    public bool $createModel = false;

    public ?string $message = null;

    public bool $showAddColumnModal = false;

    /** @var array{name: string, type: string, length: string, nullable: bool, default: string} */
    public array $newColumn = [];

    public bool $showEditColumnModal = false;

    public string $editColumnOriginal = '';

    /** @var array{name: string, type: string, length: string, nullable: bool, default: string} */
    public array $editColumn = [];

    public bool $confirmDropTable = false;

    public ?string $confirmDropColumn = null;

    public bool $showTableInfoModal = false;

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

    public function mount(): void
    {
        $this->loadTables();
    }

    /** @return array<int, string> Column types usable in the create/edit modals. */
    public function columnTypes(): array
    {
        return array_keys(self::COLUMN_TYPES);
    }

    public function loadTables(): void
    {
        try {
            $connection = config('database.default');

            // MySQL/MariaDB users typically see every database on the server, so
            // schema-scope the listing to the database we are actually connected to.
            // Other drivers (e.g. sqlite tests) rely on schema-less getTables().
            $driver = DB::connection($connection)->getDriverName();
            $tables = in_array($driver, ['mysql', 'mariadb'], true)
                ? Schema::connection($connection)->getTables(DB::connection($connection)->getDatabaseName())
                : Schema::connection($connection)->getTables();

            $this->tables = array_map(function (array $table) {
                $table['has_model'] = app(ModelGenerator::class)->modelExists($table['name']);

                return $table;
            }, $tables);
        } catch (Throwable $e) {
            $this->error = 'Could not load tables: '.$e->getMessage();
            $this->tables = [];
        }
    }

    public function selectTable(string $table): void
    {
        $this->selectedTable = $table;
        $this->message = null;
        $this->loadTableData();
    }

    public function loadTableData(): void
    {
        if (! $this->selectedTable) {
            return;
        }

        try {
            $connection = config('database.default');

            $this->columns = $this->withColumnKeys(
                Schema::connection($connection)->getColumns($this->selectedTable),
                Schema::connection($connection)->getIndexes($this->selectedTable)
            );

            $this->totalRows = DB::connection($connection)->table($this->selectedTable)->count();
        } catch (Throwable $e) {
            $this->error = 'Could not load table data: '.$e->getMessage();
            $this->columns = [];
        }
    }

    public function viewTable(string $table): void
    {
        $this->selectTable($table);
        $this->showTableInfoModal = true;
        $this->error = null;
    }

    public function openCreateTable(): void
    {
        $this->showCreateTableModal = true;
        $this->newTableName = '';
        $this->newTableColumns = [$this->emptyColumn()];
        $this->createAutoId = true;
        $this->createTimestamps = true;
        $this->createModel = false;
        $this->message = null;
        $this->error = null;
    }

    public function openAddColumn(): void
    {
        if (! $this->selectedTable) {
            return;
        }

        $this->showAddColumnModal = true;
        $this->newColumn = $this->emptyColumn();
        $this->error = null;
    }

    public function openEditColumn(string $name): void
    {
        if (! $this->selectedTable) {
            return;
        }

        $this->editColumnOriginal = $name;
        $this->editColumn = [
            'name' => $name,
            'type' => $this->guessColumnType($name),
            'length' => '',
            'nullable' => $this->isColumnNullable($name),
            'default' => $this->getColumnDefault($name),
        ];
        $this->showEditColumnModal = true;
        $this->showTableInfoModal = false;
        $this->error = null;
    }

    public function addTableColumnRow(): void
    {
        $this->newTableColumns[] = $this->emptyColumn();
    }

    public function removeTableColumnRow(int $index): void
    {
        unset($this->newTableColumns[$index]);
        $this->newTableColumns = array_values($this->newTableColumns);
    }

    public function createTable(): void
    {
        if (! $this->validateTableName($this->newTableName)) {
            return;
        }

        $columns = $this->normalizeColumns($this->newTableColumns);

        if (count($columns) === 0) {
            $this->error = 'Add at least one column.';

            return;
        }

        foreach ($columns as $i => $column) {
            if (! $this->validateColumn($column, $i)) {
                return;
            }
        }

        try {
            $connection = config('database.default');

            Schema::connection($connection)->create($this->newTableName, function (Blueprint $table) use ($columns) {
                if ($this->createAutoId) {
                    $table->id();
                }

                foreach ($columns as $column) {
                    $this->applyColumnDefinition($table, $column, $column['primary'] ?? false);
                }

                if ($this->createTimestamps) {
                    $table->timestamps();
                }
            });

            $this->showCreateTableModal = false;
            $this->selectedTable = $this->newTableName;
            $this->loadTables();
            $this->loadTableData();

            if ($this->createModel) {
                try {
                    app(ModelGenerator::class)->generate($this->newTableName, $this->columns, ['force' => true]);
                    $this->loadTables();

                    $this->message = 'Table and model created successfully.';
                } catch (Throwable $e) {
                    $this->message = 'Table created, but model generation failed: '.$e->getMessage();
                }
            }
        } catch (Throwable $e) {
            $this->error = 'Could not create table: '.$e->getMessage();
        }
    }

    public function generateModel(): void
    {
        if (! $this->selectedTable) {
            return;
        }

        try {
            app(ModelGenerator::class)->generate($this->selectedTable, $this->columns, ['force' => true]);

            $this->loadTables();
            $this->message = 'Model created successfully.';
        } catch (Throwable $e) {
            $this->message = 'Model generation failed: '.$e->getMessage();
        }
    }

    public function generateModelFor(string $table): void
    {
        $this->selectTable($table);
        $this->generateModel();
    }

    public function openAddColumnFor(string $table): void
    {
        $this->selectTable($table);
        $this->openAddColumn();
    }

    public function requestDropTableFor(string $table): void
    {
        $this->selectTable($table);
        $this->requestDropTable();
    }

    public function addColumn(): void
    {
        if (! $this->selectedTable) {
            return;
        }

        if (! $this->validateColumn($this->newColumn, 0)) {
            return;
        }

        try {
            Schema::connection(config('database.default'))
                ->table($this->selectedTable, function (Blueprint $table) {
                    $this->applyColumnDefinition($table, $this->newColumn);
                });

            $this->showAddColumnModal = false;
            $this->loadTableData();
        } catch (Throwable $e) {
            $this->error = 'Could not add column: '.$e->getMessage();
        }
    }

    public function saveEditColumn(): void
    {
        if (! $this->selectedTable) {
            return;
        }

        if (! $this->validateColumn($this->editColumn, 0)) {
            return;
        }

        $table = $this->selectedTable;
        $original = $this->editColumnOriginal;
        $renamed = $this->editColumn['name'];

        try {
            $connection = config('database.default');

            // Rename first (must happen on the existing column before change()).
            if ($renamed !== $original) {
                Schema::connection($connection)
                    ->table($table, function (Blueprint $t) use ($original, $renamed) {
                        $t->renameColumn($original, $renamed);
                    });
            }

            // Then apply the new definition to the (possibly renamed) column.
            Schema::connection($connection)
                ->table($table, function (Blueprint $t) use ($renamed) {
                    $current = $this->editColumn;
                    $current['name'] = $renamed;
                    $this->applyColumnDefinition($t, $current, false, true);
                });

            $this->showEditColumnModal = false;
            $this->loadTableData();
        } catch (Throwable $e) {
            $this->error = 'Could not update column: '.$e->getMessage();
        }
    }

    public function requestDropTable(): void
    {
        if ($this->selectedTable) {
            $this->confirmDropTable = true;
        }
    }

    public function dropTable(): void
    {
        if (! $this->selectedTable) {
            return;
        }

        try {
            Schema::connection(config('database.default'))->dropIfExists($this->selectedTable);

            $this->confirmDropTable = false;
            $this->selectedTable = null;
            $this->columns = [];
            $this->totalRows = 0;
            $this->loadTables();
        } catch (Throwable $e) {
            $this->error = 'Could not drop table: '.$e->getMessage();
        }
    }

    public function requestDropColumn(string $name): void
    {
        $this->confirmDropColumn = $name;
        $this->showTableInfoModal = false;
    }

    public function dropColumn(): void
    {
        if (! $this->selectedTable || ! $this->confirmDropColumn) {
            return;
        }

        try {
            Schema::connection(config('database.default'))
                ->table($this->selectedTable, function (Blueprint $table) {
                    $table->dropColumn($this->confirmDropColumn);
                });

            $this->confirmDropColumn = null;
            $this->loadTableData();
        } catch (Throwable $e) {
            $this->error = 'Could not drop column: '.$e->getMessage();
        }
    }

    public function cancelModals(): void
    {
        $this->showCreateTableModal = false;
        $this->showAddColumnModal = false;
        $this->showEditColumnModal = false;
        $this->showTableInfoModal = false;
        $this->confirmDropTable = false;
        $this->confirmDropColumn = null;
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
};
