<div>
    <x-tardis::page-header
        :title="$bread['name_plural'] ?? ucfirst($slug)"
        :description="$bread['description'] ?? 'Browse records for this resource.'"
    >
        <x-slot:action>
            <a href="{{ $this->createUrl }}" class="btn btn-primary">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                {{ __('tardis::bread.new_record', ['name' => $bread['name'] ?? ucfirst($slug)]) }}
            </a>
        </x-slot:action>
    </x-tardis::page-header>

    <div class="card bg-base-100 mb-6 border border-base-300">
        <div class="card-body">
            <div class="flex flex-col gap-2 max-w-md">
                <label class="label">
                    <span class="text-base-content">{{ __('tardis::bread.search') }}</span>
                </label>
                <input type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('tardis::bread.search_name', ['name' => $bread['name_plural'] ?? ucfirst($slug)]) }}" aria-label="Search {{ $bread['name_plural'] ?? ucfirst($slug) }}" autocomplete="off" />
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="overflow-x-auto">
            @if ($this->rows->isEmpty())
                <div class="card-body text-center py-12">
                    <x-tardis::icon name="table-cells" class="w-16 h-16 mx-auto text-base-content/30" />
                    <h3 class="text-lg font-semibold mt-4">{{ __('tardis::bread.no_records_found') }}</h3>
                    <p class="text-base-content/60 mt-2">{{ __('tardis::bread.create_the_first_item_for_this_eb71') }}</p>
                </div>
            @else
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            @foreach ($this->visibleFields as $field)
                                <th scope="col">{{ $field['label'] ?? ucfirst((string) ($field['name'] ?? '')) }}</th>
                            @endforeach
                            <th class="text-right" scope="col">{{ __('tardis::bread.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows as $row)
                            <tr>
                                @foreach ($this->visibleFields as $field)
                                    @php($fieldName = $field['name'] ?? '')
                                    <td>
                                        @if (! empty($field['translatable']))
                                            {{ \Tardis\Classes\Translation::value(data_get($row, $fieldName), $field['locales'] ?? null) }}
                                        @else
                                            {{ data_get($row, $fieldName, '-') }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/'.$row->getKey()) }}" class="btn btn-ghost btn-xs">{{ __('tardis::bread.view') }}</a>
                                        <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/'.$row->getKey().'/edit') }}" class="btn btn-ghost btn-xs">{{ __('tardis::bread.edit') }}</a>
                                        <button type="button" wire:click="delete({{ $row->getKey() }})" wire:confirm="Delete this record?" class="btn btn-ghost btn-xs text-error">{{ __('tardis::bread.delete') }}</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="p-4">
                    {{ $this->rows->links() }}
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-base-300 px-4 py-2">
                    <span class="text-sm text-base-content/60">
                        {{ __('tardis::bread.records_summary', ['count' => $this->rows->total(), 'ms' => $this->executionMs]) }}
                    </span>

                    @if (! empty($this->warnings))
                        <div class="flex flex-col gap-1">
                            @foreach ($this->warnings as $warning)
                                <span class="inline-flex items-center gap-1 text-sm text-warning">
                                    <x-tardis::icon name="exclamation-triangle" class="w-4 h-4" />
                                    {{ $warning }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
