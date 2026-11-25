@php
    // Read from the slug on every request; it is not part of the snapshot.
    $bread = $this->bread;
@endphp
<div>
    <x-tardis::page-header
        :title="$bread['name_plural'] ?? ucfirst($slug)"
        :description="$bread['description'] ?? 'Browse records for this resource.'"
    >
        <x-slot:action>
            @if (! empty($bread['order_column']) && app(\Tardis\Auth\BreadAuthorization::class)->allows('edit', $slug))
                <a href="{{ route('tardis.bread.order', ['slug' => $slug]) }}" wire:navigate class="btn btn-ghost">
                    <x-tardis::icon name="bars-3" class="w-4 h-4" />
                    {{ __('tardis::bread.order') }}
                </a>
            @endif
            @if ($this->canImport())
                <button type="button" x-on:click="$dispatch('open-modal', 'bread-import')" class="btn btn-ghost">
                    <x-tardis::icon name="arrow-up-tray" class="w-4 h-4" />
                    {{ __('tardis::bread.import_csv') }}
                </button>
            @endif
            @if (config('tardis.bread.export', true))
                <button type="button" wire:click="export" class="btn btn-ghost">
                    <x-tardis::icon name="arrow-down-tray" class="w-4 h-4" />
                    {{ __('tardis::bread.export_csv') }}
                </button>
                @if ($this->xlsxAvailable())
                    <button type="button" wire:click="export('xlsx')" class="btn btn-ghost">
                        <x-tardis::icon name="table-cells" class="w-4 h-4" />
                        {{ __('tardis::bread.export_xlsx') }}
                    </button>
                @endif
            @endif
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

                @php($savedViews = $this->savedViews)
                <div class="dropdown dropdown-end ms-auto">
                    <div tabindex="0" role="button" class="btn btn-ghost gap-1">
                        <x-tardis::icon name="bookmark" class="w-4 h-4" />
                        {{ __('tardis::bread.views.title') }}
                    </div>
                    <div tabindex="0" class="dropdown-content z-30 mt-2 w-72 rounded-box border border-base-300 bg-base-100 p-3 shadow-lg">
                        @if ($savedViews === [])
                            <p class="mb-2 text-sm text-base-content/60">{{ __('tardis::bread.views.none') }}</p>
                        @else
                            <ul class="menu menu-sm mb-2 p-0">
                                @foreach ($savedViews as $viewKey => $view)
                                    <li wire:key="view-{{ md5($viewKey) }}" class="flex-row items-center">
                                        <button type="button" wire:click="applyView(@js($viewKey))" class="flex-1">{{ $viewKey }}</button>
                                        <button type="button" wire:click="deleteView(@js($viewKey))" class="btn btn-ghost btn-xs" aria-label="{{ __('tardis::bread.views.delete', ['name' => $viewKey]) }}">
                                            <x-tardis::icon name="x-mark" class="w-3.5 h-3.5" />
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <form wire:submit="saveView" class="flex gap-2">
                            <input type="text" wire:model="viewName" maxlength="60" class="input input-sm flex-1" placeholder="{{ __('tardis::bread.views.name') }}" aria-label="{{ __('tardis::bread.views.name') }}" />
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('tardis::bread.views.save') }}</button>
                        </form>
                        @error('viewName')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>
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
                            wire:key="filter-{{ $name }}"
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
                    @php($selectedCount = $selectAllMatching ? $this->matchingCount : count($selected))
                    <div class="flex flex-wrap items-center gap-2 border-b border-base-300 px-4 py-2">
                        <span class="text-sm">{{ __('tardis::bread.selected_count', ['count' => $selectedCount]) }}</span>
                        @if ($this->offersMatching())
                            <button type="button" wire:click="selectMatching" class="link link-primary text-sm">{{ __('tardis::bread.select_all_matching', ['count' => $this->matchingCount]) }}</button>
                        @elseif ($selectAllMatching)
                            <span class="text-sm text-base-content/60">{{ __('tardis::bread.all_matching_selected') }}</span>
                        @endif
                        <button type="button" wire:click="clearSelection" class="btn btn-ghost btn-xs">{{ __('tardis::bread.clear_selection') }}</button>
                        @foreach ($bulkActions as $action)
                            <button type="button" wire:key="bulk-{{ $action->name() }}" wire:click="runBulk(@js($action->name()))" {!! $action->getConfirmMessage() ? 'wire:confirm="'.e($action->getConfirmMessage()).'"' : '' !!} class="btn btn-xs {{ $action->tone === 'error' ? 'btn-error btn-outline' : 'btn-ghost' }}">{{ $action->getTitle() }}</button>
                        @endforeach
                    </div>
                @endif
                @error('selected') <div class="alert alert-warning mx-4 my-2 text-sm" role="alert">{{ $message }}</div> @enderror

                <table class="table table-zebra">
                    <thead>
                        <tr>
                            @if ($bulkActions->isNotEmpty())
                                @php($pageChecked = $this->pageSelected())
                                <th class="w-8" scope="col">
                                    <input type="checkbox" wire:click="togglePage" @checked($pageChecked) class="checkbox checkbox-sm" aria-label="{{ __('tardis::bread.select_page') }}" />
                                </th>
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
                            <th class="text-end" scope="col">{{ __('tardis::bread.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows as $row)
                            <tr wire:key="row-{{ $row->getKey() }}">
                                @if ($bulkActions->isNotEmpty())
                                    <td><input type="checkbox" wire:model.live="selected" value="{{ $row->getKey() }}" class="checkbox checkbox-sm" aria-label="{{ __('tardis::bread.select') }}" /></td>
                                @endif
                                @foreach (($this->layoutFields ?? $this->visibleFields) as $field)
                                    @php($fieldName = $field['name'] ?? '')
                                    @php($isRelation = \Tardis\Bread\BreadQuery::showsRelation($field))
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
                                            @if (isset($this->inlineFields[$fieldName]) && empty($field['accessor']))
                                                @php($inlineKey = $row->getKey())
                                                @php($inlineShown = $value ?? '-')
                                                @php($inlineType = ($field['type'] ?? 'text') === 'number' ? 'number' : 'text')
                                                @php($inlineLabel = __('tardis::bread.edit_inline', ['field' => $field['label'] ?? $fieldName]))
                                                <div x-data="{ editing: false, value: @js($value) }">
                                                    <button type="button" x-show="! editing" x-on:click="editing = true; $nextTick(() => $refs.input.focus())" class="cursor-text border-b border-dashed border-base-content/30 text-start" aria-label="{{ $inlineLabel }}">{{ $inlineShown }}</button>
                                                    <form x-show="editing" x-cloak x-on:submit.prevent="$wire.updateInline(@js($inlineKey), @js($fieldName), value).then((saved) => { if (saved) editing = false })" x-on:keydown.escape="editing = false">
                                                        <input x-ref="input" x-model="value" type="{{ $inlineType }}" class="input input-xs w-full" aria-label="{{ $inlineLabel }}" />
                                                    </form>
                                                </div>
                                            @elseif (! empty($field['translatable']))
                                                {{ \Tardis\Support\Translation::value($value, $field['locales'] ?? null) }}
                                            @elseif ($browseField = ($this->formfields[$fieldName] ?? null))
                                                {{ $browseField->browse($value) ?? '-' }}
                                            @else
                                                {{ $value ?? '-' }}
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-end">
                                    <div class="flex justify-end gap-2">
                                        @unless (method_exists($row, 'trashed') && $row->trashed())
                                            <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/'.$row->getKey()) }}" class="btn btn-ghost btn-xs">{{ __('tardis::bread.view') }}</a>
                                            <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/'.$row->getKey().'/edit') }}" class="btn btn-ghost btn-xs">{{ __('tardis::bread.edit') }}</a>
                                        @endunless
                                        @foreach ($this->actions() as $action)
                                            @if ($action->appliesTo($row))
                                                <button type="button" wire:click="runAction(@js($action->name()), @js($row->getKey()))" {!! $action->getConfirmMessage() ? 'wire:confirm="'.e($action->getConfirmMessage()).'"' : '' !!} class="btn btn-ghost btn-xs {{ $action->tone === 'error' ? 'text-error' : '' }}">{{ $action->getTitle() }}</button>
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

    @if ($this->canImport())
        <x-tardis::modal name="bread-import" :title="__('tardis::bread.import_csv')">
            <p class="mb-3 text-sm text-base-content/70">{{ __('tardis::bread.import_help') }}</p>

            @if ($importReport === null)
                <form wire:submit="import" class="flex flex-col gap-3">
                    <input type="file" wire:model="importFile" accept="{{ $this->xlsxAvailable() ? '.csv,text/csv,.xlsx' : '.csv,text/csv' }}" class="file-input w-full" />
                    @error('importFile') <p class="text-sm text-error" role="alert">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2">
                        <button type="button" x-on:click="$dispatch('close-modal', 'bread-import')" class="btn btn-ghost">{{ __('tardis::bread.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="importFile,import">{{ __('tardis::bread.import_run') }}</button>
                    </div>
                </form>
            @else
                @if ($importReport['errors'] === [])
                    <div class="alert alert-success text-sm" role="status">{{ __('tardis::bread.import_done', ['created' => $importReport['created'], 'updated' => $importReport['updated']]) }}</div>
                @else
                    <div class="alert alert-error text-sm" role="alert">{{ __('tardis::bread.import_failed') }}</div>
                    <ul class="mt-2 max-h-60 overflow-y-auto text-sm">
                        @foreach ($importReport['errors'] as $line => $messages)
                            <li wire:key="import-error-{{ $line }}"><span class="font-medium">{{ __('tardis::bread.import_row', ['row' => $line]) }}</span> {{ implode(' ', $messages) }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($importReport['ignored'] !== [])
                    <p class="mt-2 text-xs text-base-content/60">{{ __('tardis::bread.import_ignored', ['columns' => implode(', ', $importReport['ignored'])]) }}</p>
                @endif
                <div class="mt-4 flex justify-end">
                    <button type="button" wire:click="closeImport" x-on:click="$dispatch('close-modal', 'bread-import')" class="btn btn-primary">{{ __('tardis::bread.close') }}</button>
                </div>
            @endif
        </x-tardis::modal>
    @endif
</div>
