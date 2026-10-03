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

new #[Title('Create Table')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use ManagesColumnDefinitions;

    public string $newTableName = '';

    /** @var array<int, array{name: string, type: string, length: string, nullable: bool, default: string, primary: bool}> */
    public array $newTableColumns = [];

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
        if (! $this->validateTableName($this->newTableName)) {
            return;
        }

        $columns = $this->normalizeColumns($this->newTableColumns);

        if (count($columns) === 0) {
            $this->error = 'Add at least one column.';

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

            if ($this->createModel) {
                try {
                    $columns = $this->withColumnKeys(
                        Schema::connection($connection)->getColumns($this->newTableName),
                        Schema::connection($connection)->getIndexes($this->newTableName)
                    );

                    app(ModelGenerator::class)->generate($this->newTableName, $columns, ['force' => true]);

                    session()->flash('message', 'Table and model created successfully.');
                } catch (Throwable $e) {
                    session()->flash('message', 'Table created, but model generation failed: '.$e->getMessage());
                }
            } else {
                session()->flash('message', 'Table created successfully.');
            }

            $this->redirect(route('tardis.database.index'));
        } catch (Throwable $e) {
            $this->error = 'Could not create table: '.$e->getMessage();
        }
    }
};
