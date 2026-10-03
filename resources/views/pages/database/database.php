<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Database\Concerns\ManagesColumnDefinitions;
use Tardis\Database\ModelGenerator;

new #[Title('Database Explorer')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use ManagesColumnDefinitions;

    /** @var array<int, array{name: string, has_model: bool}> */
    public array $tables = [];

    public ?string $selectedTable = null;

    /** @var array<int, array<string, mixed>> */
    public array $columns = [];

    public int $totalRows = 0;

    public ?string $error = null;

    public bool $showTableInfoModal = false;

    public bool $selectedTableHasModel = false;

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so a permission revoked after the page opened must
     * still stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::DATABASE);
    }

    public function mount(): void
    {
        $this->loadTables();
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
        $this->selectedTableHasModel = app(ModelGenerator::class)->modelExists($table);
        $this->showTableInfoModal = true;
        $this->error = null;
    }

    public function closeViewTable(): void
    {
        $this->showTableInfoModal = false;
    }
};
