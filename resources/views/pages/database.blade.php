<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

new #[Title('Database Explorer')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public array $tables = [];

    public ?string $selectedTable = null;

    public array $columns = [];

    public array $rows = [];

    public int $page = 1;

    public int $perPage = 25;

    public int $totalRows = 0;

    public ?string $error = null;

    public function mount(): void
    {
        $this->loadTables();
    }

    public function loadTables(): void
    {
        try {
            $connection = config('database.default');
            $this->tables = Schema::connection($connection)->getTables();
        } catch (\Throwable $e) {
            $this->error = 'Could not load tables: '.$e->getMessage();
            $this->tables = [];
        }
    }

    public function selectTable(string $table): void
    {
        $this->selectedTable = $table;
        $this->page = 1;
        $this->loadTableData();
    }

    public function loadTableData(): void
    {
        if (! $this->selectedTable) {
            return;
        }

        try {
            $connection = config('database.default');

            $this->columns = Schema::connection($connection)->getColumns($this->selectedTable);

            $this->totalRows = DB::connection($connection)->table($this->selectedTable)->count();

            $this->rows = DB::connection($connection)
                ->table($this->selectedTable)
                ->offset(($this->page - 1) * $this->perPage)
                ->limit($this->perPage)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            $this->error = 'Could not load table data: '.$e->getMessage();
            $this->columns = [];
            $this->rows = [];
        }
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadTableData();
        }
    }

    public function nextPage(): void
    {
        if (($this->page * $this->perPage) < $this->totalRows) {
            $this->page++;
            $this->loadTableData();
        }
    }

    public function getTotalPages(): int
    {
        return (int) ceil($this->totalRows / $this->perPage);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Database Explorer</h1>
        <p class="text-base-content/60 mt-1">Browse database tables and their data</p>
    </div>

    @if ($error)
        <div class="alert alert-error mb-4 shadow-sm">
            <span>{{ $error }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Table List -->
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-4">
                <h3 class="card-title text-sm">Tables</h3>
                <div class="overflow-y-auto max-h-96">
                    @forelse ($tables as $table)
                        <button
                            wire:click="selectTable('{{ $table['name'] }}')"
                            class="btn btn-ghost btn-sm w-full justify-start {{ $selectedTable === $table['name'] ? 'btn-active' : '' }}"
                        >
                            <x-tardis::icon name="database" class="w-4 h-4" />
                            {{ $table['name'] }}
                        </button>
                    @empty
                        <p class="text-sm opacity-50 py-4 text-center">No tables found</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Table Data -->
        <div class="lg:col-span-3">
            @if ($selectedTable)
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="card-title">
                                <x-tardis::icon name="database" class="w-5 h-5" />
                                {{ $selectedTable }}
                            </h3>
                            <span class="badge badge-ghost">{{ $totalRows }} rows</span>
                        </div>

                        @if (!empty($columns))
                            <div class="mb-4">
                                <h4 class="text-sm font-semibold mb-2">Columns</h4>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($columns as $col)
                                        <span class="badge badge-ghost badge-xs">
                                            {{ $col['name'] }}
                                            <span class="text-base-content/40">({{ $col['type'] ?? 'unknown' }})</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (!empty($rows))
                            <div class="overflow-x-auto">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            @foreach ($columns as $col)
                                                <th>{{ $col['name'] }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr>
                                                @foreach ($columns as $col)
                                                    <td class="text-xs max-w-[200px] truncate" title="{{ $row->{$col['name']} ?? '' }}">
                                                        {{ $row->{$col['name']} ?? '' }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="flex items-center justify-between mt-4">
                                <span class="text-sm opacity-60">
                                    Showing {{ ($page - 1) * $perPage + 1 }}-{{ min($page * $perPage, $totalRows) }} of {{ $totalRows }}
                                </span>
                                <div class="join">
                                    <button wire:click="previousPage" class="join-item btn btn-sm" {{ $page <= 1 ? 'disabled' : '' }}>«</button>
                                    <span class="join-item btn btn-sm btn-disabled">{{ $page }} / {{ $this->getTotalPages() }}</span>
                                    <button wire:click="nextPage" class="join-item btn btn-sm" {{ $page >= $this->getTotalPages() ? 'disabled' : '' }}>»</button>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8 opacity-50">
                                <p>No data in this table</p>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body text-center py-16">
                        <x-tardis::icon name="database" class="w-16 h-16 mx-auto opacity-20" />
                        <h3 class="text-lg font-semibold mt-4">Select a table</h3>
                        <p class="text-base-content/60 mt-2">Choose a table from the list to browse its data</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
