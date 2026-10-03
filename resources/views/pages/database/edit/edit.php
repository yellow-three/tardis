<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Database\Concerns\ManagesColumnDefinitions;
use Tardis\Database\ModelGenerator;

new #[Title('Edit Table')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use ManagesColumnDefinitions;

    public string $selectedTable = '';

    /** @var array<int, array<string, mixed>> */
    public array $columns = [];

    /**
     * Editable column rows bound to the create-style table.
     *
     * @var array<int, array{name: string, original: string, type: string, length: string, nullable: bool, default: string, key: string}>
     */
    public array $editColumns = [];

    public int $totalRows = 0;

    public ?string $error = null;

    public ?string $message = null;

    public bool $selectedTableHasModel = false;

    public bool $showAddColumnForm = false;

    /** @var array{name: string, type: string, length: string, nullable: bool, default: string, primary: bool} */
    public array $newColumn = [];

    public bool $showEditColumnForm = false;

    public string $editColumnOriginal = '';

    /** @var array{name: string, type: string, length: string, nullable: bool, default: string, primary: bool} */
    public array $editColumn = [];

    public bool $confirmDropTable = false;

    public ?string $confirmDropColumn = null;

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so a permission revoked after the page opened must
     * still stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::DATABASE);
    }

    public function mount(string $table): void
    {
        $connection = config('database.default');

        if (! Schema::connection($connection)->hasTable($table)) {
            abort(404);
        }

        $this->selectedTable = $table;
        $this->selectedTableHasModel = app(ModelGenerator::class)->modelExists($table);
        $this->loadTableData();
    }

    public function loadTableData(): void
    {
        try {
            $connection = config('database.default');

            $this->columns = $this->withColumnKeys(
                Schema::connection($connection)->getColumns($this->selectedTable),
                Schema::connection($connection)->getIndexes($this->selectedTable)
            );

            $this->editColumns = array_map(
                fn (array $column) => [
                    'name' => $column['name'],
                    'original' => $column['name'],
                    'type' => $this->guessColumnType($column['name']),
                    'length' => $this->parseColumnLength((string) $column['type']),
                    'nullable' => (bool) ($column['nullable'] ?? false),
                    'default' => $column['default'] === null ? '' : (string) $column['default'],
                    'key' => $column['key'] ?? '',
                ],
                $this->columns
            );

            $this->totalRows = DB::connection($connection)->table($this->selectedTable)->count();
        } catch (Throwable $e) {
            $this->error = 'Could not load table data: '.$e->getMessage();
            $this->columns = [];
            $this->editColumns = [];
        }
    }

    protected function parseColumnLength(string $rawType): string
    {
        if (preg_match('/\((\d+(?:,\s*\d+)?)\)/', $rawType, $matches)) {
            return str_replace(' ', '', $matches[1]);
        }

        return '';
    }

    public function addEditColumnRow(): void
    {
        $this->editColumns[] = [
            'name' => '',
            'original' => '',
            'type' => 'string',
            'length' => '',
            'nullable' => false,
            'default' => '',
            'key' => '',
        ];
        $this->error = null;
    }

    public function saveColumn(int $index): void
    {
        $column = $this->editColumns[$index] ?? null;

        if ($column === null) {
            return;
        }

        if (! $this->validateColumn($column, $index)) {
            return;
        }

        $this->error = null;

        try {
            $connection = config('database.default');

            if ($column['original'] === '') {
                // Newly added row → persist the column.
                Schema::connection($connection)
                    ->table($this->selectedTable, function (Blueprint $table) use ($column) {
                        $this->applyColumnDefinition($table, $column);
                    });

                $this->message = 'Column added successfully.';
            } else {
                // Existing row → rename first, then apply the new definition.
                $original = $column['original'];
                $renamed = $column['name'];

                if ($renamed !== $original) {
                    Schema::connection($connection)
                        ->table($this->selectedTable, function (Blueprint $t) use ($original, $renamed) {
                            $t->renameColumn($original, $renamed);
                        });
                }

                Schema::connection($connection)
                    ->table($this->selectedTable, function (Blueprint $t) use ($column, $renamed) {
                        $current = $column;
                        $current['name'] = $renamed;
                        $this->applyColumnDefinition($t, $current, false, true);
                    });

                $this->message = 'Column updated successfully.';
            }

            $this->loadTableData();
        } catch (Throwable $e) {
            $this->error = 'Could not save column: '.$e->getMessage();
        }
    }

    public function requestRemoveColumnRow(int $index): void
    {
        $column = $this->editColumns[$index] ?? null;

        if ($column === null) {
            return;
        }

        // Unsaved rows are removed from the editor without touching the database.
        if ($column['original'] === '') {
            unset($this->editColumns[$index]);
            $this->editColumns = array_values($this->editColumns);

            return;
        }

        $this->requestDropColumn($column['original']);
    }

    public function openAddColumn(): void
    {
        $this->showAddColumnForm = true;
        $this->newColumn = $this->emptyColumn();
        $this->showEditColumnForm = false;
        $this->error = null;
    }

    public function closeAddColumn(): void
    {
        $this->showAddColumnForm = false;
        $this->error = null;
    }

    public function addColumn(): void
    {
        if (! $this->validateColumn($this->newColumn, 0)) {
            return;
        }

        try {
            Schema::connection(config('database.default'))
                ->table($this->selectedTable, function (Blueprint $table) {
                    $this->applyColumnDefinition($table, $this->newColumn);
                });

            $this->showAddColumnForm = false;
            $this->loadTableData();
        } catch (Throwable $e) {
            $this->error = 'Could not add column: '.$e->getMessage();
        }
    }

    public function openEditColumn(string $name): void
    {
        $this->editColumnOriginal = $name;
        $this->editColumn = [
            'name' => $name,
            'type' => $this->guessColumnType($name),
            'length' => '',
            'nullable' => $this->isColumnNullable($name),
            'default' => $this->getColumnDefault($name),
            'primary' => false,
        ];
        $this->showEditColumnForm = true;
        $this->showAddColumnForm = false;
        $this->error = null;
    }

    public function closeEditColumn(): void
    {
        $this->showEditColumnForm = false;
        $this->error = null;
    }

    public function saveEditColumn(): void
    {
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

                $this->editColumnOriginal = $renamed;
            }

            // Then apply the new definition to the (possibly renamed) column.
            Schema::connection($connection)
                ->table($table, function (Blueprint $t) use ($renamed) {
                    $current = $this->editColumn;
                    $current['name'] = $renamed;
                    $this->applyColumnDefinition($t, $current, false, true);
                });

            $this->showEditColumnForm = false;
            $this->loadTableData();
        } catch (Throwable $e) {
            $this->error = 'Could not update column: '.$e->getMessage();
        }
    }

    public function requestDropColumn(string $name): void
    {
        $this->confirmDropColumn = $name;
    }

    public function cancelDropColumn(): void
    {
        $this->confirmDropColumn = null;
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
            $this->confirmDropColumn = null;
        }
    }

    public function generateModel(): void
    {
        try {
            app(ModelGenerator::class)->generate($this->selectedTable, $this->columns, ['force' => true]);

            $this->selectedTableHasModel = true;
            $this->message = 'Model created successfully.';
        } catch (Throwable $e) {
            $this->message = 'Model generation failed: '.$e->getMessage();
        }
    }

    public function requestDropTable(): void
    {
        $this->confirmDropTable = true;
    }

    public function cancelDropTable(): void
    {
        $this->confirmDropTable = false;
    }

    public function dropTable(): void
    {
        try {
            Schema::connection(config('database.default'))->dropIfExists($this->selectedTable);

            session()->flash('message', 'Table dropped successfully.');

            $this->redirect(route('tardis.database.index'));
        } catch (Throwable $e) {
            $this->error = 'Could not drop table: '.$e->getMessage();
            $this->confirmDropTable = false;
        }
    }
};
