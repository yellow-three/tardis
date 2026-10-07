<div>
    <x-tardis::page-header :title="__('tardis::database.create_table')" :description="__('tardis::database.define_a_new_database_table_and_6500')" />

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

    <form wire:submit="createTable" class="space-y-4">
        <div class="flex items-end gap-4 mb-4">
            <label class="flex flex-col gap-2 w-full max-w-xs">
                <span class="text-base-content">{{ __('tardis::database.table_name') }}</span>
                <input type="text" wire:model="newTableName" class="input input-sm" placeholder="{{ __('tardis::database.e_g_widgets') }}" />
            </label>
            <label class="label cursor-pointer gap-2">
                <span class="text-base-content text-xs">{{ __('tardis::database.auto_id') }}</span>
                <input type="checkbox" wire:model="createAutoId" class="toggle toggle-sm toggle-primary" />
            </label>
            <label class="label cursor-pointer gap-2">
                <span class="text-base-content text-xs">{{ __('tardis::database.timestamps') }}</span>
                <input type="checkbox" wire:model="createTimestamps" class="toggle toggle-sm toggle-primary" />
            </label>
            <label class="label cursor-pointer gap-2">
                <span class="text-base-content text-xs">{{ __('tardis::database.create_model') }}</span>
                <input type="checkbox" wire:model="createModel" class="toggle toggle-sm toggle-primary" />
            </label>
        </div>

        <div class="card bg-base-100 border border-base-300">
            <div class="card-body p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="card-title text-sm">{{ __('tardis::database.columns') }}</h3>
                    <button type="button" wire:click="addTableColumnRow" class="btn btn-ghost btn-xs gap-1">
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
                                <th scope="col">{{ __('tardis::database.primary') }}</th>
                                <th class="w-16" aria-hidden="true"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($newTableColumns as $index => $column)
                                <tr wire:key="new-column-{{ $index }}">
                                    <td>
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.name" class="input input-xs" placeholder="{{ __('tardis::database.name') }}" />
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
                                        <input type="text" wire:model="newTableColumns.{{ $index }}.default" class="input input-xs" placeholder="{{ __('tardis::database.null_2') }}" />
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
            <a href="{{ route('tardis.database.index') }}" class="btn btn-ghost btn-sm">{{ __('tardis::database.cancel') }}</a>
            <button type="submit" class="btn btn-primary btn-sm gap-1">
                <x-tardis::icon name="check" class="w-3 h-3" />
                {{ __('tardis::database.create_table') }}
            </button>
        </div>
    </form>
</div>