<div>
    <x-tardis::page-header :title="__('tardis::database.database_explorer')" :description="__('tardis::database.create_and_manage_database_tables_and_9068')" />

    @if ($error)
        <div class="alert alert-error mb-4">
            <span>{{ $error }}</span>
        </div>
    @endif

    @if (session('message'))
        <div class="alert alert-success mb-4">
            <x-tardis::icon name="check-circle" class="w-3 h-3" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <!-- Table List -->
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="card-title text-sm">
                    <x-tardis::icon name="table-cells" class="w-4 h-4" />
                    {{ __('tardis::database.tables') }}
                    <span class="badge badge-ghost badge-sm">{{ count($tables) }}</span>
                </h3>
                <a href="{{ route('tardis.database.create') }}" class="btn btn-primary btn-sm">
                    <x-tardis::icon name="plus" class="w-3 h-3" />
                    {{ __('tardis::database.new_table') }}
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('tardis::database.table') }}</th>
                            <th class="text-right" scope="col">{{ __('tardis::database.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tables as $table)
                            <tr wire:key="table-{{ $table['name'] }}">
                                <td>
                                    <button wire:click="viewTable('{{ $table['name'] }}')" class="flex items-center gap-2 font-medium hover:text-primary">
                                        <x-tardis::icon name="table-cells" class="w-4 h-4 text-base-content/40" />
                                        {{ $table['name'] }}
                                        @if ($table['has_model'])
                                            <span class="badge badge-primary badge-xs">{{ __('tardis::database.model') }}</span>
                                        @endif
                                    </button>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1 flex-wrap">
                                        <button wire:click="viewTable('{{ $table['name'] }}')" class="btn btn-ghost btn-xs">
                                            <x-tardis::icon name="eye" class="w-3 h-3" />
                                            {{ __('tardis::database.view') }}
                                        </button>
                                        <a href="{{ route('tardis.database.edit', $table['name']) }}" class="btn btn-ghost btn-xs">
                                            <x-tardis::icon name="pencil-square" class="w-3 h-3" />
                                            {{ __('tardis::database.edit') }}
                                        </a>
                                        <a href="{{ route('tardis.bread.create') }}" class="btn btn-ghost btn-xs">
                                            <x-tardis::icon name="document-text" class="w-3 h-3" />
                                            {{ __('tardis::database.create_bread') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center py-12 text-base-content/50">
                                    <x-tardis::icon name="database" class="w-16 h-16 mx-auto text-base-content/20" />
                                    <p class="mt-2">{{ __('tardis::database.no_tables_found') }}</p>
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
                        @if ($selectedTableHasModel)
                            <span class="badge badge-primary badge-xs">{{ __('tardis::database.model') }}</span>
                        @endif
                    </h3>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-ghost">{{ __('tardis::database.columns_count', ['count' => count($columns)]) }}</span>
                        <span class="badge badge-ghost">{{ __('tardis::database.rows_count', ['count' => $totalRows]) }}</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-xs">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('tardis::database.field') }}</th>
                                <th scope="col">{{ __('tardis::database.type') }}</th>
                                <th scope="col">{{ __('tardis::database.null') }}</th>
                                <th scope="col">{{ __('tardis::database.default') }}</th>
                                <th scope="col">{{ __('tardis::database.key') }}</th>
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
                                            <span class="badge badge-primary badge-xs">{{ __('tardis::database.pri') }}</span>
                                        @elseif (($col['key'] ?? '') === 'UNI')
                                            <span class="badge badge-warning badge-xs">{{ __('tardis::database.uni') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-base-content/50">{{ __('tardis::database.no_columns_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="modal-action">
                    <a href="{{ route('tardis.database.edit', $selectedTable) }}" class="btn btn-ghost btn-sm">
                        <x-tardis::icon name="pencil-square" class="w-3 h-3" />
                        {{ __('tardis::database.edit_table') }}
                    </a>
                    <button wire:click="closeViewTable" class="btn btn-primary btn-sm">{{ __('tardis::database.close') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="closeViewTable">{{ __('tardis::database.close_2') }}</button>
            </form>
        </dialog>
    @endif
</div>