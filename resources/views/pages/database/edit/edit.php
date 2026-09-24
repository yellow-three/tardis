<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Database\Concerns\ManagesColumnDefinitions;
use Tardis\Database\ModelGenerator;

new #[Title('Edit Table')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use ManagesColumnDefinitions;

    public string $selectedTable = '';

    /** @var array<int, array<string, mixed>> */
    public array $columns = [];

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

            $this->totalRows = DB::connection($connection)->table($this->selectedTable)->count();
        } catch (Throwable $e) {
            $this->error = 'Could not load table data: '.$e->getMessage();
            $this->columns = [];
        }
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
