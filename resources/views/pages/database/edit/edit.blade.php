<div>
    <x-tardis::page-header :title="__('tardis::database.edit_table')" :description="$selectedTable" />

    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('tardis.database.index') }}" class="btn btn-ghost btn-xs gap-1">
            <x-tardis::icon name="arrow-left" class="w-3 h-3" />
            {{ __('tardis::database.back_to_database_explorer') }}
        </a>
    </div>

    @if ($error)
        <div class="alert alert-error mb-4">
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($message)
        <div class="alert alert-success mb-4">
            <x-tardis::icon name="check-circle" class="w-3 h-3" />
            <span>{{ $message }}</span>
        </div>
    @endif

    <!-- Table Summary -->
    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="badge badge-lg badge-ghost gap-1">
                <x-tardis::icon name="table-cells" class="w-3 h-3" />
                {{ __('tardis::database.columns_count', ['count' => count($columns)]) }}
            </span>
            <span class="badge badge-lg badge-ghost gap-1">
                <x-tardis::icon name="database" class="w-3 h-3" />
                {{ __('tardis::database.rows_count', ['count' => $totalRows]) }}
            </span>
            @if ($selectedTableHasModel)
                <span class="badge badge-lg badge-primary">{{ __('tardis::database.model') }}</span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if (! $selectedTableHasModel)
                <button wire:click="generateModel" class="btn btn-ghost btn-sm gap-1">
                    <x-tardis::icon name="document-text" class="w-3 h-3" />
                    {{ __('tardis::database.create_model') }}
                </button>
            @endif
            <button wire:click="requestDropTable" class="btn btn-ghost btn-sm text-error gap-1">
                <x-tardis::icon name="trash" class="w-3 h-3" />
                {{ __('tardis::database.drop_table') }}
            </button>
        </div>
    </div>

    <!-- Columns -->
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="card-title text-sm">{{ __('tardis::database.columns') }}</h3>
                <button wire:click="addEditColumnRow" class="btn btn-ghost btn-xs gap-1">
                    <x-tardis::icon name="plus" class="w-3 h-3" />
                    {{ __('tardis::database.add_column') }}
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th class="w-48" scope="col">{{ __('tardis::database.name_2') }}</th>
                            <th class="w-44" scope="col">{{ __('tardis::database.type') }}</th>
                            <th class="w-28" scope="col">{{ __('tardis::database.length') }}</th>
                            <th class="w-32" scope="col">{{ __('tardis::database.default') }}</th>
                            <th scope="col">{{ __('tardis::database.nullable') }}</th>
                            <th class="w-16" scope="col">{{ __('tardis::database.key') }}</th>
                            <th class="w-32" aria-hidden="true"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($editColumns as $index => $column)
                            <tr wire:key="edit-column-{{ $column['original'] !== '' ? $column['original'] : 'new-'.$index }}">
                                <td>
                                    <input type="text" wire:model="editColumns.{{ $index }}.name" class="input input-xs" placeholder="{{ __('tardis::database.name') }}" />
                                </td>
                                <td>
                                    <select wire:model="editColumns.{{ $index }}.type" class="select select-xs">
                                        @foreach ($this->columnTypes() as $columnType)
                                            <option value="{{ $columnType }}">{{ $columnType }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" wire:model="editColumns.{{ $index }}.length" class="input input-xs" placeholder="255" />
                                </td>
                                <td>
                                    <input type="text" wire:model="editColumns.{{ $index }}.default" class="input input-xs" placeholder="{{ __('tardis::database.null_2') }}" />
                                </td>
                                <td>
                                    <input type="checkbox" wire:model="editColumns.{{ $index }}.nullable" class="toggle toggle-xs toggle-primary" />
                                </td>
                                <td>
                                    @if (($column['key'] ?? '') === 'PRI')
                                        <span class="badge badge-primary badge-xs">{{ __('tardis::database.pri') }}</span>
                                    @elseif (($column['key'] ?? '') === 'UNI')
                                        <span class="badge badge-warning badge-xs">{{ __('tardis::database.uni') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button wire:click="saveColumn({{ $index }})" class="btn btn-primary btn-xs gap-1">
                                            <x-tardis::icon name="check" class="w-3 h-3" />
                                            {{ __('tardis::database.save') }}
                                        </button>
                                        <button wire:click="requestRemoveColumnRow({{ $index }})" class="btn btn-ghost btn-xs text-error">
                                            <x-tardis::icon name="trash" class="w-3 h-3" />
                                            {{ __('tardis::database.drop') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-12 text-base-content/50">
                                    <x-tardis::icon name="database" class="w-16 h-16 mx-auto text-base-content/20" />
                                    <p class="mt-2">{{ __('tardis::database.no_columns_found') }}</p>
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
                <h3 class="font-bold text-lg">{{ __('tardis::database.drop_column') }}</h3>
                <p class="py-4 text-sm text-base-content/80">
                    {!! __('tardis::database.confirm_drop_column', ['column' => '<code class="text-xs">'.e($confirmDropColumn).'</code>']) !!}
                </p>
                <div class="modal-action">
                    <button wire:click="cancelDropColumn" class="btn btn-ghost btn-sm">{{ __('tardis::database.cancel') }}</button>
                    <button wire:click="dropColumn" class="btn btn-error btn-sm gap-1">
                        <x-tardis::icon name="trash" class="w-3 h-3" />
                        {{ __('tardis::database.drop_column') }}
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelDropColumn">{{ __('tardis::database.close_2') }}</button>
            </form>
        </dialog>
    @endif

    <!-- Drop Table Confirm Modal -->
    @if ($confirmDropTable)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">{{ __('tardis::database.drop_table') }}</h3>
                <p class="py-4 text-sm text-base-content/80">
                    {!! __('tardis::database.confirm_drop_table', ['table' => '<code class="text-xs">'.e($selectedTable).'</code>']) !!}
                </p>
                <div class="modal-action">
                    <button wire:click="cancelDropTable" class="btn btn-ghost btn-sm">{{ __('tardis::database.cancel') }}</button>
                    <button wire:click="dropTable" class="btn btn-error btn-sm gap-1">
                        <x-tardis::icon name="trash" class="w-3 h-3" />
                        {{ __('tardis::database.drop_table') }}
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelDropTable">{{ __('tardis::database.close_2') }}</button>
            </form>
        </dialog>
    @endif
</div>