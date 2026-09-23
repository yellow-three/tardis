<div>
    <x-tardis::page-header title="Database Explorer" description="Create and manage database tables and columns" />

    @if ($error)
        <div class="alert alert-error mb-4 shadow-sm">
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($message)
        <div class="alert alert-success mb-4 shadow-sm">
            <x-tardis::icon name="check-circle" class="w-3 h-3" />
            <span>{{ $message }}</span>
        </div>
    @endif

    <!-- Table List -->
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="card-title text-sm">
                    <x-tardis::icon name="table-cells" class="w-4 h-4" />
                    Tables
                    <span class="badge badge-ghost badge-sm">{{ count($tables) }}</span>
                </h3>
                <button wire:click="openCreateTable" class="btn btn-primary btn-sm">
                    <x-tardis::icon name="plus" class="w-3 h-3" />
                    New Table
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tables as $table)
                            <tr wire:key="table-{{ $table['name'] }}">
                                <td>
                                    <button wire:click="viewTable('{{ $table['name'] }}')" class="flex items-center gap-2 font-medium hover:text-primary">
                                        <x-tardis::icon name="table-cells" class="w-4 h-4 text-base-content/40" />
                                        {{ $table['name'] }}
                                    </button>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1 flex-wrap">
                                        <button wire:click="viewTable('{{ $table['name'] }}')" class="btn btn-ghost btn-xs">
                                            <x-tardis::icon name="eye" class="w-3 h-3" />
                                            View
                                        </button>
                                        <a href="{{ route('tardis.bread.create') }}" class="btn btn-ghost btn-xs">
                                            <x-tardis::icon name="document-text" class="w-3 h-3" />
                                            Create BREAD
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center py-12 opacity-50">
                                    <x-tardis::icon name="database" class="w-16 h-16 mx-auto opacity-20" />
                                    <p class="mt-2">No tables found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Table Info Modal -->
    @if ($showTableInfoModal && $selectedTable)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-6xl">
                <div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
                    <h3 class="font-bold text-lg flex items-center gap-2">
                        <x-tardis::icon name="table-cells" class="w-5 h-5" />
                        {{ $selectedTable }}
                    </h3>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-ghost">{{ count($columns) }} columns</span>
                        <span class="badge badge-ghost">{{ $totalRows }} rows</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-xs">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Type</th>
                                <th>Null</th>
                                <th>Default</th>
                                <th>Key</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($columns as $col)
                                <tr wire:key="column-{{ $col['name'] }}">
                                    <td class="font-semibold">{{ $col['name'] }}</td>
                                    <td><code class="text-xs">{{ $col['type'] ?? 'unknown' }}</code></td>
                                    <td>{{ ! empty($col['nullable']) ? 'YES' : '' }}</td>
                                    <td class="text-xs">{{ $col['default'] === null ? 'NULL' : $col['default'] }}</td>
                                    <td>
                                        @if (($col['key'] ?? '') === 'PRI')
                                            <span class="badge badge-primary badge-xs">PRI</span>
                                        @elseif (($col['key'] ?? '') === 'UNI')
                                            <span class="badge badge-warning badge-xs">UNI</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1">
                                            <button wire:click="openEditColumn('{{ $col['name'] }}')" class="btn btn-ghost btn-xs" title="Edit {{ $col['name'] }}">
                                                <x-tardis::icon name="pencil-square" class="w-3 h-3" />
                                            </button>
                                            <button wire:click="requestDropColumn('{{ $col['name'] }}')" class="btn btn-ghost btn-xs text-error" title="Drop {{ $col['name'] }}">
                                                <x-tardis::icon name="x-mark" class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-8 opacity-50">No columns found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="modal-action">
                    @if (! $selectedTableHasModel)
                        <button wire:click="generateModel" class="btn btn-ghost btn-sm">
                            <x-tardis::icon name="code-bracket" class="w-3 h-3" />
                            Create Model
                        </button>
                    @endif
                    <button wire:click="openAddColumn" class="btn btn-ghost btn-sm">
                        <x-tardis::icon name="plus" class="w-3 h-3" />
                        Add Column
                    </button>
                    <button wire:click="requestDropTable" class="btn btn-ghost btn-sm text-error">
                        <x-tardis::icon name="trash" class="w-3 h-3" />
                        Drop Table
                    </button>
                    <button wire:click="cancelModals" class="btn btn-primary btn-sm">Close</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelModals">close</button>
            </form>
        </dialog>
    @endif

    <!-- Create Table Modal -->
    @if ($showCreateTableModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-4xl">
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
                        <label class="label cursor-pointer gap-2">
                            <span class="label-text">Create Model</span>
                            <input type="checkbox" wire:model="createModel" class="toggle toggle-sm toggle-primary" />
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
            <div class="modal-box w-full max-w-2xl">
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
            <div class="modal-box w-full max-w-2xl">
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