<div>
    <x-tardis::page-header title="Create Table" description="Define a new database table and its columns" />

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

    <form wire:submit="createTable" class="space-y-4">
        <div class="flex items-end gap-4 mb-4">
            <label class="form-control w-full max-w-xs">
                <span class="label-text">Table name</span>
                <input type="text" wire:model="newTableName" class="input input-bordered input-sm" placeholder="e.g. widgets" />
            </label>
            <label class="label cursor-pointer gap-2">
                <span class="label-text text-xs">Auto ID</span>
                <input type="checkbox" wire:model="createAutoId" class="toggle toggle-sm toggle-primary" />
            </label>
            <label class="label cursor-pointer gap-2">
                <span class="label-text text-xs">Timestamps</span>
                <input type="checkbox" wire:model="createTimestamps" class="toggle toggle-sm toggle-primary" />
            </label>
            <label class="label cursor-pointer gap-2">
                <span class="label-text text-xs">Create Model</span>
                <input type="checkbox" wire:model="createModel" class="toggle toggle-sm toggle-primary" />
            </label>
        </div>

        <div class="card bg-base-100 shadow-sm">
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
                                <th class="w-48">Name</th>
                                <th class="w-44">Type</th>
                                <th class="w-28">Length</th>
                                <th class="w-32">Default</th>
                                <th>Nullable</th>
                                <th>Primary</th>
                                <th class="w-16"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($newTableColumns as $index => $column)
                                <tr wire:key="new-column-{{ $index }}">
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.name" class="input input-bordered input-xs" placeholder="name" />
                                    </td>
                                    <td>
                                        <select wire:model="newTableColumns.{{ $index }}.type" class="select select-bordered select-xs">
                                            @foreach ($this->columnTypes() as $columnType)
                                                <option value="{{ $columnType }}">{{ $columnType }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.length" class="input input-bordered input-xs" placeholder="255" />
                                    </td>
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.default" class="input input-bordered input-xs" placeholder="NULL" />
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