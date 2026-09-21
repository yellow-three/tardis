<div>
    <x-tardis::page-header title="Database Explorer" description="Browse tables, manage columns and create BREAD definitions" />

    @if ($error)
        <div class="alert alert-error mb-4 shadow-sm">
            <span>{{ $error }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Table List -->
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="card-title text-sm">Tables</h3>
                    <button wire:click="openCreateTable" class="btn btn-primary btn-xs">
                        <x-tardis::icon name="plus" class="w-3 h-3" />
                        New Table
                    </button>
                </div>
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
                        <div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
                            <h3 class="card-title">
                                <x-tardis::icon name="database" class="w-5 h-5" />
                                {{ $selectedTable }}
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-ghost">{{ $totalRows }} rows</span>
                                <a href="{{ route('tardis.bread.create') }}" class="btn btn-primary btn-xs">
                                    <x-tardis::icon name="document-text" class="w-3 h-3" />
                                    Create BREAD
                                </a>
                                <button wire:click="openAddColumn" class="btn btn-ghost btn-xs">
                                    <x-tardis::icon name="plus" class="w-3 h-3" />
                                    Add Column
                                </button>
                                <button wire:click="requestDropTable" class="btn btn-ghost btn-xs text-error">
                                    <x-tardis::icon name="trash" class="w-3 h-3" />
                                    Drop Table
                                </button>
                            </div>
                        </div>

                        @if (!empty($columns))
                            <div class="mb-4">
                                <h4 class="text-sm font-semibold mb-2">Columns</h4>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($columns as $col)
                                        <span class="badge badge-ghost badge-xs gap-1">
                                            {{ $col['name'] }}
                                            <span class="text-base-content/40">{{ $col['type'] ?? 'unknown' }}</span>
                                            <button wire:click="openEditColumn('{{ $col['name'] }}')" class="text-base-content/50 hover:text-base-content" title="Edit {{ $col['name'] }}">
                                                <x-tardis::icon name="pencil-square" class="w-3 h-3" />
                                            </button>
                                            <button wire:click="requestDropColumn('{{ $col['name'] }}')" class="text-error/50 hover:text-error" title="Drop {{ $col['name'] }}">
                                                <x-tardis::icon name="x-mark" class="w-3 h-3" />
                                            </button>
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
                        <p class="text-base-content/60 mt-2">Choose a table from the list to browse its data or create a new one</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Create Table Modal -->
    @if ($showCreateTableModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-3xl">
                <h3 class="font-bold text-lg mb-4">Create Table</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <label class="form-control">
                        <span class="label-text">Table name</span>
                        <input type="text" wire:model="newTableName" class="input input-bordered input-sm" placeholder="e.g. products" />
                    </label>
                    <div class="flex items-end gap-4 pb-1">
                        <label class="label cursor-pointer gap-2">
                            <span class="label-text">Auto ID</span>
                            <input type="checkbox" wire:model="createAutoId" class="toggle toggle-sm toggle-primary" />
                        </label>
                        <label class="label cursor-pointer gap-2">
                            <span class="label-text">Timestamps</span>
                            <input type="checkbox" wire:model="createTimestamps" class="toggle toggle-sm toggle-primary" />
                        </label>
                    </div>
                </div>

                <div class="overflow-x-auto mb-4">
                    <table class="table table-xs">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Length</th>
                                <th>Nullable</th>
                                <th>Default</th>
                                <th>Primary</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($newTableColumns as $index => $column)
                                <tr>
                                    <td>
                                        <button wire:click="removeTableColumnRow({{ $index }})" class="btn btn-ghost btn-xs text-error" title="Remove column">
                                            <x-tardis::icon name="x-mark" class="w-3 h-3" />
                                        </button>
                                    </td>
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.name" class="input input-bordered input-xs" placeholder="name" />
                                    </td>
                                    <td>
                                        <select wire:model="newTableColumns.{{ $index }}.type" class="select select-bordered select-xs">
                                            @foreach ($this->columnTypes() as $columnType)
                                                <option value="{{ $columnType }}">{{ Str::headline($columnType) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.length" class="input input-bordered input-xs w-16" placeholder="255" />
                                    </td>
                                    <td>
                                        <input type="checkbox" wire:model="newTableColumns.{{ $index }}.nullable" class="checkbox checkbox-xs" />
                                    </td>
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.default" class="input input-bordered input-xs w-20" placeholder="" />
                                    </td>
                                    <td>
                                        <input type="checkbox" wire:model="newTableColumns.{{ $index }}.primary" class="checkbox checkbox-xs" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button wire:click="addTableColumnRow" class="btn btn-ghost btn-xs mb-4">
                    <x-tardis::icon name="plus" class="w-3 h-3" />
                    Add Column
                </button>

                <div class="modal-action">
                    <button wire:click="cancelModals" class="btn btn-ghost">Cancel</button>
                    <button wire:click="createTable" class="btn btn-primary">Create Table</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelModals">close</button>
            </form>
        </dialog>
    @endif

    <!-- Add Column Modal -->
    @if ($showAddColumnModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-xl">
                <h3 class="font-bold text-lg mb-4">Add Column to {{ $selectedTable }}</h3>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <label class="form-control col-span-2 sm:col-span-1">
                        <span class="label-text">Name</span>
                        <input type="text" wire:model="newColumn.name" class="input input-bordered input-sm" placeholder="e.g. sku" />
                    </label>
                    <label class="form-control col-span-1">
                        <span class="label-text">Type</span>
                        <select wire:model="newColumn.type" class="select select-bordered select-sm">
                            @foreach ($this->columnTypes() as $columnType)
                                <option value="{{ $columnType }}">{{ Str::headline($columnType) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control col-span-2 sm:col-span-1">
                        <span class="label-text">Length (optional)</span>
                        <input type="text" wire:model="newColumn.length" class="input input-bordered input-sm" placeholder="255" />
                    </label>
                    <label class="form-control col-span-2 sm:col-span-1">
                        <span class="label-text">Default (optional)</span>
                        <input type="text" wire:model="newColumn.default" class="input input-bordered input-sm" placeholder="null for NULL" />
                    </label>
                    <label class="label cursor-pointer col-span-2 sm:col-span-1">
                        <span class="label-text">Nullable</span>
                        <input type="checkbox" wire:model="newColumn.nullable" class="toggle toggle-sm toggle-primary" />
                    </label>
                </div>

                <div class="modal-action">
                    <button wire:click="cancelModals" class="btn btn-ghost">Cancel</button>
                    <button wire:click="addColumn" class="btn btn-primary">Add Column</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelModals">close</button>
            </form>
        </dialog>
    @endif

    <!-- Edit Column Modal -->
    @if ($showEditColumnModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-xl">
                <h3 class="font-bold text-lg mb-4">Edit Column {{ $editColumnOriginal }}</h3>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <label class="form-control col-span-2 sm:col-span-1">
                        <span class="label-text">Name</span>
                        <input type="text" wire:model="editColumn.name" class="input input-bordered input-sm" />
                    </label>
                    <label class="form-control col-span-1">
                        <span class="label-text">Type</span>
                        <select wire:model="editColumn.type" class="select select-bordered select-sm">
                            @foreach ($this->columnTypes() as $columnType)
                                <option value="{{ $columnType }}">{{ Str::headline($columnType) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control col-span-2 sm:col-span-1">
                        <span class="label-text">Length (optional)</span>
                        <input type="text" wire:model="editColumn.length" class="input input-bordered input-sm" placeholder="255" />
                    </label>
                    <label class="form-control col-span-2 sm:col-span-1">
                        <span class="label-text">Default (optional)</span>
                        <input type="text" wire:model="editColumn.default" class="input input-bordered input-sm" placeholder="null for NULL" />
                    </label>
                    <label class="label cursor-pointer col-span-2 sm:col-span-1">
                        <span class="label-text">Nullable</span>
                        <input type="checkbox" wire:model="editColumn.nullable" class="toggle toggle-sm toggle-primary" />
                    </label>
                </div>

                <div class="modal-action">
                    <button wire:click="cancelModals" class="btn btn-ghost">Cancel</button>
                    <button wire:click="saveEditColumn" class="btn btn-primary">Save Column</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelModals">close</button>
            </form>
        </dialog>
    @endif

    <!-- Drop Table Confirm Modal -->
    @if ($confirmDropTable)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-2 text-error">Drop Table</h3>
                <p class="mb-4">Are you sure you want to drop <strong>{{ $selectedTable }}</strong>? This cannot be undone.</p>
                <div class="modal-action">
                    <button wire:click="cancelModals" class="btn btn-ghost">Cancel</button>
                    <button wire:click="dropTable" class="btn btn-error">Drop Table</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelModals">close</button>
            </form>
        </dialog>
    @endif

    <!-- Drop Column Confirm Modal -->
    @if ($confirmDropColumn)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-2 text-error">Drop Column</h3>
                <p class="mb-4">Are you sure you want to drop column <strong>{{ $confirmDropColumn }}</strong> from <strong>{{ $selectedTable }}</strong>? This cannot be undone.</p>
                <div class="modal-action">
                    <button wire:click="cancelModals" class="btn btn-ghost">Cancel</button>
                    <button wire:click="dropColumn" class="btn btn-error">Drop Column</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelModals">close</button>
            </form>
        </dialog>
    @endif
</div>