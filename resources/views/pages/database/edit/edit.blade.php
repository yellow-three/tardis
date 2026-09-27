<div>
    <x-tardis::page-header title="Edit Table" :description="$selectedTable" />

    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('tardis.database.index') }}" class="btn btn-ghost btn-xs gap-1">
            <x-tardis::icon name="arrow-left" class="w-3 h-3" />
            Back to Database Explorer
        </a>
    </div>

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

    <!-- Table Summary -->
    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="badge badge-lg badge-ghost gap-1">
                <x-tardis::icon name="table-cells" class="w-3 h-3" />
                {{ count($columns) }} columns
            </span>
            <span class="badge badge-lg badge-ghost gap-1">
                <x-tardis::icon name="database" class="w-3 h-3" />
                {{ $totalRows }} rows
            </span>
            @if ($selectedTableHasModel)
                <span class="badge badge-lg badge-primary">Model</span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if (! $selectedTableHasModel)
                <button wire:click="generateModel" class="btn btn-ghost btn-sm gap-1">
                    <x-tardis::icon name="document-text" class="w-3 h-3" />
                    Create Model
                </button>
            @endif
            <button wire:click="requestDropTable" class="btn btn-ghost btn-sm text-error gap-1">
                <x-tardis::icon name="trash" class="w-3 h-3" />
                Drop Table
            </button>
        </div>
    </div>

    <!-- Columns -->
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="card-title text-sm">Columns</h3>
                <button wire:click="addEditColumnRow" class="btn btn-ghost btn-xs gap-1">
                    <x-tardis::icon name="plus" class="w-3 h-3" />
                    Add Column
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th class="w-48">Name</th>
                            <th class="w-44">Type</th>
                            <th class="w-28">Length</th>
                            <th class="w-32">Default</th>
                            <th>Nullable</th>
                            <th class="w-16">Key</th>
                            <th class="w-32"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($editColumns as $index => $column)
                            <tr wire:key="edit-column-{{ $column['original'] !== '' ? $column['original'] : 'new-'.$index }}">
                                <td>
                                    <input type="text" wire:model="editColumns.{{ $index }}.name" class="input input-bordered input-xs" placeholder="name" />
                                </td>
                                <td>
                                    <select wire:model="editColumns.{{ $index }}.type" class="select select-bordered select-xs">
                                        @foreach ($this->columnTypes() as $columnType)
                                            <option value="{{ $columnType }}">{{ $columnType }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" wire:model="editColumns.{{ $index }}.length" class="input input-bordered input-xs" placeholder="255" />
                                </td>
                                <td>
                                    <input type="text" wire:model="editColumns.{{ $index }}.default" class="input input-bordered input-xs" placeholder="NULL" />
                                </td>
                                <td>
                                    <input type="checkbox" wire:model="editColumns.{{ $index }}.nullable" class="toggle toggle-xs toggle-primary" />
                                </td>
                                <td>
                                    @if (($column['key'] ?? '') === 'PRI')
                                        <span class="badge badge-primary badge-xs">PRI</span>
                                    @elseif (($column['key'] ?? '') === 'UNI')
                                        <span class="badge badge-warning badge-xs">UNI</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button wire:click="saveColumn({{ $index }})" class="btn btn-primary btn-xs gap-1">
                                            <x-tardis::icon name="check" class="w-3 h-3" />
                                            Save
                                        </button>
                                        <button wire:click="requestRemoveColumnRow({{ $index }})" class="btn btn-ghost btn-xs text-error">
                                            <x-tardis::icon name="trash" class="w-3 h-3" />
                                            Drop
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-12 opacity-50">
                                    <x-tardis::icon name="database" class="w-16 h-16 mx-auto opacity-20" />
                                    <p class="mt-2">No columns found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Drop Column Confirm Modal -->
    @if ($confirmDropColumn)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Drop Column</h3>
                <p class="py-4 text-sm opacity-80">
                    Are you sure you want to drop the column <code class="text-xs">{{ $confirmDropColumn }}</code>?
                    This cannot be undone.
                </p>
                <div class="modal-action">
                    <button wire:click="cancelDropColumn" class="btn btn-ghost btn-sm">Cancel</button>
                    <button wire:click="dropColumn" class="btn btn-error btn-sm gap-1">
                        <x-tardis::icon name="trash" class="w-3 h-3" />
                        Drop Column
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelDropColumn">close</button>
            </form>
        </dialog>
    @endif

    <!-- Drop Table Confirm Modal -->
    @if ($confirmDropTable)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Drop Table</h3>
                <p class="py-4 text-sm opacity-80">
                    Are you sure you want to drop the table <code class="text-xs">{{ $selectedTable }}</code> and all of its data?
                    This cannot be undone.
                </p>
                <div class="modal-action">
                    <button wire:click="cancelDropTable" class="btn btn-ghost btn-sm">Cancel</button>
                    <button wire:click="dropTable" class="btn btn-error btn-sm gap-1">
                        <x-tardis::icon name="trash" class="w-3 h-3" />
                        Drop Table
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelDropTable">close</button>
            </form>
        </dialog>
    @endif
</div>