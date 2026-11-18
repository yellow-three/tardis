<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Database\Concerns\ManagesColumnDefinitions;
use Tardis\Database\ModelGenerator;

new #[Title('tardis::shell.titles.create_table')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use ManagesColumnDefinitions;

    public string $newTableName = '';

    /** @var array<int, array{name: string, type: string, length: string, nullable: bool, default: string, primary: bool}> */
    public array $newTableColumns = [];

    /** True where schema changes are switched off (production by default). */
    public bool $readOnly = false;

    public bool $createAutoId = true;

    public bool $createTimestamps = true;

    public bool $createModel = false;

    public ?string $error = null;

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
        $this->readOnly = ! $this->schemaChangesAllowed();
        $this->newTableColumns = [$this->emptyColumn()];
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
        if (! $this->guardSchemaChange()) {
            return;
        }

        if (! $this->validateTableName($this->newTableName)) {
            return;
        }

        $columns = $this->normalizeColumns($this->newTableColumns);

        if (count($columns) === 0) {
            $this->error = __('tardis::database.add_one_column');

            return;
        }

        foreach ($columns as $index => $column) {
            if (! $this->validateColumn($column, $index)) {
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
                    $this->applyColumnDefinition($table, $column, ! empty($column['primary']));
                }

                if ($this->createTimestamps) {
                    $table->timestamps();
                }
            });

            $this->auditSchema('table_created', ['table' => $this->newTableName, 'columns' => array_column($columns, 'name')]);

            if ($this->createModel) {
                try {
                    $columns = $this->withColumnKeys(
                        Schema::connection($connection)->getColumns($this->newTableName),
                        Schema::connection($connection)->getIndexes($this->newTableName)
                    );

                    app(ModelGenerator::class)->generate($this->newTableName, $columns);

                    session()->flash('message', __('tardis::database.table_and_model_created'));
                } catch (Throwable $e) {
                    session()->flash('message', __('tardis::database.table_created_model_failed', ['error' => $e->getMessage()]));
                }
            } else {
                session()->flash('message', __('tardis::database.table_created'));
            }

            $this->redirect(route('tardis.database.index'));
        } catch (Throwable $e) {
            $this->error = __('tardis::database.could_not_create_table', ['error' => $e->getMessage()]);
        }
    }
};
