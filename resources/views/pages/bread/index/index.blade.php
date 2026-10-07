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
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex flex-col gap-2 min-w-64 flex-1 max-w-md">
                    <label class="label" for="bread-search">
                        <span class="text-base-content">{{ __('tardis::bread.search') }}</span>
                    </label>
                    <input id="bread-search" type="search" wire:model.live.debounce.300ms="search" class="input w-full" placeholder="{{ __('tardis::bread.search_name', ['name' => $bread['name_plural'] ?? ucfirst($slug)]) }}" autocomplete="off" />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="label" for="bread-per-page"><span class="text-base-content">{{ __('tardis::bread.per_page') }}</span></label>
                    <select id="bread-per-page" wire:model.live="perPage" class="select">
                        @foreach (\Tardis\Bread\BreadQuery::PER_PAGE_OPTIONS as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($this->query()->supportsTrashed())
                    <div class="flex flex-col gap-2">
                        <label class="label" for="bread-trashed"><span class="text-base-content">{{ __('tardis::bread.deleted_records') }}</span></label>
                        <select id="bread-trashed" wire:model.live="trashed" class="select">
                            @foreach (\Tardis\Bread\BreadQuery::TRASHED as $mode)
                                <option value="{{ $mode }}">{{ __('tardis::bread.trashed.'.$mode) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            @php($namedFilters = $this->namedFilters)
            @if ($namedFilters !== [] || $this->hasFilters)
                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-base-300 pt-4">
                    @foreach ($namedFilters as $name => $filter)
                        @php($active = (bool) ($filters[$name] ?? false))
                        @php($tone = in_array($filter['color'] ?? '', ['primary', 'secondary', 'accent', 'info', 'success', 'warning', 'error', 'neutral'], true) ? $filter['color'] : 'primary')
                        <button
                            type="button"
                            wire:click="toggleFilter('{{ $name }}')"
                            class="badge gap-1 {{ $active ? 'badge-'.$tone : 'badge-outline' }} cursor-pointer"
                            aria-pressed="{{ $active ? 'true' : 'false' }}"
                        >
                            @if ($filter['icon'])
                                <x-tardis::icon :name="$filter['icon']" class="w-3.5 h-3.5" />
                            @endif
                            {{ $filter['label'] }}
                        </button>
                    @endforeach

                    @if ($this->hasFilters)
                        <button type="button" wire:click="clearFilters" class="btn btn-ghost btn-xs">
                            <x-tardis::icon name="x-mark" class="w-3.5 h-3.5" />
                            {{ __('tardis::bread.clear_filters') }}
                        </button>
                    @endif
                </div>
            @endif
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
                @php($bulkActions = $this->actions()->filter(fn ($action) => $action->isBulk()))
                @if ($bulkActions->isNotEmpty() && $selected !== [])
                    <div class="flex flex-wrap items-center gap-2 border-b border-base-300 px-4 py-2">
                        <span class="text-sm">{{ __('tardis::bread.selected_count', ['count' => count($selected)]) }}</span>
                        @foreach ($bulkActions as $action)
                            <button type="button" wire:click="runBulk('{{ $action->name() }}')" {!! $action->getConfirmMessage() ? 'wire:confirm="'.e($action->getConfirmMessage()).'"' : '' !!} class="btn btn-xs {{ $action->tone === 'error' ? 'btn-error btn-outline' : 'btn-ghost' }}">{{ $action->getTitle() }}</button>
                        @endforeach
                    </div>
                @endif

                <table class="table table-zebra">
                    <thead>
                        <tr>
                            @if ($bulkActions->isNotEmpty())
                                <th class="w-8" scope="col"><span class="sr-only">{{ __('tardis::bread.select') }}</span></th>
                            @endif
                            @foreach (($this->layoutFields ?? $this->visibleFields) as $field)
                                @php($fieldName = (string) ($field['name'] ?? ''))
                                @php($sortable = in_array($fieldName, $this->query()->orderable(), true))
                                @php($searchable = in_array($fieldName, $this->searchableColumns, true))
                                <th scope="col" @if ($sortable && $sort === $fieldName) aria-sort="{{ $direction === 'desc' ? 'descending' : 'ascending' }}" @endif>
                                    @if ($sortable)
                                        <button type="button" wire:click="sortBy('{{ $fieldName }}')" class="inline-flex items-center gap-1 font-semibold">
                                            {{ $field['label'] ?? ucfirst($fieldName) }}
                                            @if ($sort === $fieldName)
                                                <span aria-hidden="true">{{ $direction === 'desc' ? '▼' : '▲' }}</span>
                                            @endif
                                        </button>
                                    @else
                                        {{ $field['label'] ?? ucfirst($fieldName) }}
                                    @endif

                                    @if ($searchable)
                                        <input
                                            type="search"
                                            wire:model.live.debounce.400ms="columnSearch.{{ $fieldName }}"
                                            class="input input-xs mt-1 w-full font-normal"
                                            placeholder="{{ __('tardis::bread.filter') }}"
                                            aria-label="{{ __('tardis::bread.filter') }}: {{ $field['label'] ?? ucfirst($fieldName) }}"
                                            autocomplete="off"
                                        />
                                    @endif
                                </th>
                            @endforeach
                            <th class="text-right" scope="col">{{ __('tardis::bread.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows as $row)
                            <tr>
                                @if ($bulkActions->isNotEmpty())
                                    <td><input type="checkbox" wire:model.live="selected" value="{{ $row->getKey() }}" class="checkbox checkbox-sm" aria-label="{{ __('tardis::bread.select') }}" /></td>
                                @endif
                                @foreach (($this->layoutFields ?? $this->visibleFields) as $field)
                                    @php($fieldName = $field['name'] ?? '')
                                    @php($isRelation = in_array($field['type'] ?? '', \Tardis\Bread\BreadQuery::RELATION_TYPES, true))
                                    <td>
                                        @if ($isRelation)
                                            @php($cell = $this->relationCell($row, $field))
                                            @if ($cell['items'] === [])
                                                <span class="text-base-content/40">-</span>
                                            @else
                                                <span class="inline-flex flex-wrap items-center gap-1">
                                                    @foreach ($cell['items'] as $item)
                                                        @if ($item['url'])
                                                            <a href="{{ $item['url'] }}" class="link link-hover">{{ $item['label'] }}</a>
                                                        @else
                                                            {{ $item['label'] }}
                                                        @endif
                                                        @unless ($loop->last)<span class="text-base-content/30">,</span>@endunless
                                                    @endforeach
                                                    @if ($cell['more'] > 0)
                                                        <span class="text-base-content/60">+{{ $cell['more'] }} {{ __('tardis::bread.more') }}</span>
                                                    @endif
                                                </span>
                                            @endif
                                        @else
                                            @php($value = data_get($row, $field['accessor'] ?? $fieldName))
                                            @if (! empty($field['translatable']))
                                                {{ \Tardis\Classes\Translation::value($value, $field['locales'] ?? null) }}
                                            @elseif ($browseField = ($this->formfields[$fieldName] ?? null))
                                                {{ $browseField->browse($value) ?? '-' }}
                                            @else
                                                {{ $value ?? '-' }}
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        @unless (method_exists($row, 'trashed') && $row->trashed())
                                            <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/'.$row->getKey()) }}" class="btn btn-ghost btn-xs">{{ __('tardis::bread.view') }}</a>
                                            <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/'.$row->getKey().'/edit') }}" class="btn btn-ghost btn-xs">{{ __('tardis::bread.edit') }}</a>
                                        @endunless
                                        @foreach ($this->actions() as $action)
                                            @if ($action->appliesTo($row))
                                                <button type="button" wire:click="runAction('{{ $action->name() }}', {{ $row->getKey() }})" {!! $action->getConfirmMessage() ? 'wire:confirm="'.e($action->getConfirmMessage()).'"' : '' !!} class="btn btn-ghost btn-xs {{ $action->tone === 'error' ? 'text-error' : '' }}">{{ $action->getTitle() }}</button>
                                            @endif
                                        @endforeach
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
