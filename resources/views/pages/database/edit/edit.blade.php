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
            <button wire:click="openAddColumn" class="btn btn-ghost btn-sm gap-1">
                <x-tardis::icon name="plus" class="w-3 h-3" />
                Add Column
            </button>
            <button wire:click="requestDropTable" class="btn btn-ghost btn-sm text-error gap-1">
                <x-tardis::icon name="trash" class="w-3 h-3" />
                Drop Table
            </button>
        </div>
    </div>

    <!-- Add Column Inline Form -->
    @if ($showAddColumnForm)
        <div class="card bg-base-100 shadow-sm mb-4 border border-primary/20">
            <div class="card-body p-4">
                <h3 class="card-title text-sm mb-2">Add Column</h3>
                @include('tardis::pages.database._column-fields', ['bind' => 'newColumn', 'types' => $this->columnTypes()])
                <div class="flex items-center justify-end gap-2 mt-3">
                    <button wire:click="closeAddColumn" class="btn btn-ghost btn-sm">Cancel</button>
                    <button wire:click="addColumn" class="btn btn-primary btn-sm gap-1">
                        <x-tardis::icon name="check" class="w-3 h-3" />
                        Add Column
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Edit Column Inline Form -->
    @if ($showEditColumnForm)
        <div class="card bg-base-100 shadow-sm mb-4 border border-primary/20">
            <div class="card-body p-4">
                <h3 class="card-title text-sm mb-2">Edit Column: {{ $editColumnOriginal }}</h3>
                @include('tardis::pages.database._column-fields', ['bind' => 'editColumn', 'types' => $this->columnTypes()])
                <div class="flex items-center justify-end gap-2 mt-3">
                    <button wire:click="closeEditColumn" class="btn btn-ghost btn-sm">Cancel</button>
                    <button wire:click="saveEditColumn" class="btn btn-primary btn-sm gap-1">
                        <x-tardis::icon name="check" class="w-3 h-3" />
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Columns -->
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body p-4">
            <div class="overflow-x-auto">
                <table class="table">
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
                                        <button wire:click="openEditColumn('{{ $col['name'] }}')" class="btn btn-ghost btn-xs">
                                            <x-tardis::icon name="pencil-square" class="w-3 h-3" />
                                            Edit
                                        </button>
                                        <button wire:click="requestDropColumn('{{ $col['name'] }}')" class="btn btn-ghost btn-xs text-error">
                                            <x-tardis::icon name="trash" class="w-3 h-3" />
                                            Drop
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-12 opacity-50">
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