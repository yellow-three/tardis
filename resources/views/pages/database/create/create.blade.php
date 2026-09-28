<div>
    <x-tardis::page-header title="Create Table" description="Define a new database table and its columns" />

    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('tardis.database.index') }}" class="btn btn-ghost btn-xs gap-1">
            <x-tardis::icon name="arrow-left" class="w-3 h-3" />
            Back to Database Explorer
        </a>
    </div>

    @if ($error)
        <div class="alert alert-error mb-4">
            <span>{{ $error }}</span>
        </div>
    @endif

    <form wire:submit="createTable" class="space-y-4">
        <div class="flex items-end gap-4 mb-4">
            <label class="flex flex-col gap-2 w-full max-w-xs">
                <span class="text-base-content">Table name</span>
                <input type="text" wire:model="newTableName" class="input input-sm" placeholder="e.g. widgets" />
            </label>
            <label class="label cursor-pointer gap-2">
                <span class="text-base-content text-xs">Auto ID</span>
                <input type="checkbox" wire:model="createAutoId" class="toggle toggle-sm toggle-primary" />
            </label>
            <label class="label cursor-pointer gap-2">
                <span class="text-base-content text-xs">Timestamps</span>
                <input type="checkbox" wire:model="createTimestamps" class="toggle toggle-sm toggle-primary" />
            </label>
            <label class="label cursor-pointer gap-2">
                <span class="text-base-content text-xs">Create Model</span>
                <input type="checkbox" wire:model="createModel" class="toggle toggle-sm toggle-primary" />
            </label>
        </div>

        <div class="card bg-base-100 border border-base-300">
            <div class="card-body p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="card-title text-sm">Columns</h3>
                    <button type="button" wire:click="addTableColumnRow" class="btn btn-ghost btn-xs gap-1">
                        <x-tardis::icon name="plus" class="w-3 h-3" />
                        Add Column
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th class="w-48" scope="col">Name</th>
                                <th class="w-44" scope="col">Type</th>
                                <th class="w-28" scope="col">Length</th>
                                <th class="w-32" scope="col">Default</th>
                                <th scope="col">Nullable</th>
                                <th scope="col">Primary</th>
                                <th class="w-16" aria-hidden="true"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($newTableColumns as $index => $column)
                                <tr wire:key="new-column-{{ $index }}">
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.name" class="input input-xs" placeholder="name" />
                                    </td>
                                    <td>
                                        <select wire:model="newTableColumns.{{ $index }}.type" class="select select-xs">
                                            @foreach ($this->columnTypes() as $columnType)
                                                <option value="{{ $columnType }}">{{ $columnType }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.length" class="input input-xs" placeholder="255" />
                                    </td>
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.default" class="input input-xs" placeholder="NULL" />
                                    </td>
                                    <td>
                                        <input type="checkbox" wire:model="newTableColumns.{{ $index }}.nullable" class="toggle toggle-xs toggle-primary" />
                                    </td>
                                    <td>
                                        <input type="checkbox" wire:model="newTableColumns.{{ $index }}.primary" class="toggle toggle-xs toggle-primary" />
                                    </td>
                                    <td>
                                        @if (count($newTableColumns) > 1)
                                            <button type="button" wire:click="removeTableColumnRow({{ $index }})" class="btn btn-ghost btn-xs text-error">
                                                <x-tardis::icon name="trash" class="w-3 h-3" />
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('tardis.database.index') }}" class="btn btn-ghost btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm gap-1">
                <x-tardis::icon name="check" class="w-3 h-3" />
                Create Table
            </button>
        </div>
    </form>
</div>