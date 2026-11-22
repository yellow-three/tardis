<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\Action;
use Tardis\Bread\ActionManager;
use Tardis\Bread\ActivityLogger;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadListing;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\Export\CsvExporter;
use Tardis\Bread\Livewire\ReadsBreadDefinition;
use Tardis\Formfields\Formfield;
use Tardis\Formfields\FormfieldManager;

new #[Title('tardis::shell.titles.bread')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use ReadsBreadDefinition;
    use WithPagination;

    #[Locked]
    public string $slug = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    #[Url(except: 15)]
    public int $perPage = 15;

    #[Url(except: 'without')]
    public string $trashed = 'without';

    /** @var array<int, int|string> ids ticked for a bulk action */
    public array $selected = [];

    /**
     * The bulk action runs on every record the current search and filters
     * match, not only the ticked rows of this page.
     */
    public bool $selectAllMatching = false;

    /** @var array<string, bool> named filters currently applied, keyed by name */
    public array $filters = [];

    /** @var array<string, string> inline per-column search terms, keyed by column */
    public array $columnSearch = [];

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between requests, so a permission removed while the page is open has to
     * stop the next one. mount() still authorises the first render, where the
     * slug is not known yet.
     */
    public function boot(): void
    {
        if ($this->slug !== '') {
            app(BreadAuthorization::class)->authorize('browse', $this->slug);
        }
    }

    /** @return array<string, mixed> read from the slug on every request */
    #[Computed]
    public function bread(): array
    {
        return $this->breadArray();
    }

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->definition(); // a missing definition is a 404 before anything else

        app(BreadAuthorization::class)->authorize('browse', $this->slug);
    }

    public function updatingSearch(): void
    {
        $this->selectAllMatching = false;
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->selectAllMatching = false;
        $this->resetPage();
    }

    public function updatingTrashed(): void
    {
        $this->selectAllMatching = false;
        $this->resetPage();
    }

    public function updatingColumnSearch(): void
    {
        $this->selectAllMatching = false;
        $this->resetPage();
    }

    /** Toggle a named filter declared by the definition's layout. */
    public function toggleFilter(string $name): void
    {
        if (! array_key_exists($name, $this->query()->namedFilters())) {
            return;
        }

        $this->filters[$name] = ! ($this->filters[$name] ?? false);
        $this->resetPage();
    }

    /** Reset every listing refinement (search, column search, filters, trashed, sort). */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->columnSearch = [];
        $this->filters = [];
        $this->trashed = 'without';
        $this->sort = '';
        $this->direction = 'asc';
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, $this->query()->orderable(), true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function query(): BreadQuery
    {
        return new BreadQuery($this->definition());
    }

    #[Computed]
    public function listing(): ?BreadListing
    {
        $model = $this->bread['model'] ?? null;

        if (! BreadDefinition::isModelClass($model)) {
            return null;
        }

        return $this->query()->listing($this->search, $this->sort ?: null, $this->direction, $this->perPage, $this->trashed, $this->columnSearch, $this->filters);
    }

    /** @return array<string, array<string, mixed>> named filters declared by the layout */
    #[Computed]
    public function namedFilters(): array
    {
        return $this->query()->namedFilters();
    }

    /** @return array<int, string> columns that accept an inline search term */
    #[Computed]
    public function searchableColumns(): array
    {
        return $this->query()->searchable();
    }

    #[Computed]
    public function hasFilters(): bool
    {
        return $this->search !== ''
            || array_filter($this->columnSearch) !== []
            || array_filter($this->filters) !== []
            || $this->sort !== ''
            || $this->trashed !== 'without';
    }

    #[Computed]
    public function rows()
    {
        return $this->listing?->rows ?? collect();
    }

    #[Computed]
    public function executionMs(): float
    {
        return $this->listing?->executionMs ?? 0.0;
    }

    #[Computed]
    public function warnings(): array
    {
        return $this->listing?->warnings ?? [];
    }

    #[Computed]
    public function layoutFields(): array
    {
        $layout = $this->bread['layout'] ?? [];
        $listLayout = $layout['list'] ?? $layout['browse'] ?? [];

        if (! empty($listLayout) && is_array($listLayout)) {
            $result = [];
            foreach ($listLayout as $item) {
                if (is_string($item)) {
                    $field = collect($this->visibleFields)->first(fn ($f) => ($f['name'] ?? null) === $item);
                    if ($field) {
                        $result[] = $field;
                    }
                } elseif (is_array($item) && isset($item['name'])) {
                    $field = collect($this->visibleFields)->first(fn ($f) => ($f['name'] ?? null) === $item['name']);
                    if ($field) {
                        $result[] = array_merge($field, $item);
                    } else {
                        $result[] = $item;
                    }
                }
            }
            if (! empty($result)) {
                return $result;
            }
        }

        return $this->visibleFields;
    }

    #[Computed]
    public function visibleFields(): array
    {
        if (empty($this->bread)) {
            return [];
        }

        return array_values(array_filter($this->bread['fields'] ?? [], fn (array $field) => (bool) ($field['browse'] ?? true)));
    }

    /**
     * The field objects behind the listing, keyed by name, so each cell can ask
     * its type how to show the value (browse()).
     *
     * @return array<string, Formfield>
     */
    #[Computed]
    public function formfields(): array
    {
        // Build from layout columns first so per-column config (relation target,
        // label column, …) reaches the field type; fall back to browse fields.
        $definitions = [];

        foreach ($this->layoutFields as $field) {
            if (isset($field['name'], $field['type'])) {
                $definitions[$field['name']] = $field;
            }
        }

        foreach ($this->visibleFields as $field) {
            if (isset($field['name'], $field['type']) && ! isset($definitions[$field['name']])) {
                $definitions[$field['name']] = $field;
            }
        }

        return collect(app(FormfieldManager::class)->fields(array_values($definitions)))
            ->keyBy(fn (Formfield $field) => $field->name)
            ->all();
    }

    /**
     * A relation cell: the first few related labels plus how many were hidden.
     *
     * @param  array<string, mixed>  $field
     * @return array{items: array<int, array{label: string, url: ?string}>, more: int}
     */
    public function relationCell(Model $row, array $field): array
    {
        $relation = (string) ($field['relation'] ?? $field['name'] ?? '');

        if ($relation === '' || ! method_exists($row, $relation)) {
            return ['items' => [], 'more' => 0];
        }

        $related = $row->{$relation};

        // A to-one relation (belongs_to, has_one) is a single model or null;
        // collect() on a model would list its attributes instead.
        if (! $related instanceof Collection) {
            $related = collect($related instanceof Model || $related === null ? array_filter([$related]) : $related);
        }

        $limit = max(1, (int) ($field['display_limit'] ?? $field['limit'] ?? 3));
        $labelColumn = (string) ($field['label_column'] ?? 'name');
        $linkTo = isset($field['link_to']) && $field['link_to'] !== '' ? (string) $field['link_to'] : null;
        $prefix = trim((string) config('tardis.admin.prefix', 'admin'), '/');

        $items = $related->take($limit)->map(function (mixed $item) use ($labelColumn, $linkTo, $prefix): array {
            $label = data_get($item, $labelColumn);
            if ($label === null || $label === '') {
                $label = data_get($item, 'name') ?? data_get($item, 'id') ?? '';
            }

            return [
                'label' => (string) $label,
                'url' => $linkTo !== null && is_object($item) && method_exists($item, 'getKey')
                    ? url($prefix.'/'.$linkTo.'/'.$item->getKey())
                    : null,
            ];
        })->all();

        return [
            'items' => $items,
            'more' => max(0, $related->count() - $limit),
        ];
    }

    #[Computed]
    public function createUrl(): string
    {
        return url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$this->slug.'/create');
    }

    protected function findRecord(int|string $id): Model
    {
        $model = $this->bread['model'] ?? null;

        if (! BreadDefinition::isModelClass($model)) {
            abort(404);
        }

        return $this->query()->build(trashed: 'with')->findOrFail($id);
    }

    /** Run one action on one row. */
    public function runAction(string $name, int|string $id): void
    {
        $action = app(ActionManager::class)->find($this->slug, $name) ?? abort(404);

        if ($this->perform($action, $this->findRecord($id))) {
            session()->flash('message', $action->getSuccessMessage());
        }
    }

    /** The number of records the current search and filters match. */
    #[Computed]
    public function matchingCount(): int
    {
        return $this->listing?->rows?->total() ?? 0;
    }

    /** @return list<string> the keys of the rows on this page, as the checkboxes send them */
    public function pageKeys(): array
    {
        return $this->rows->map(fn (Model $row) => (string) $row->getKey())->values()->all();
    }

    public function pageSelected(): bool
    {
        $keys = $this->pageKeys();

        return $keys !== [] && array_diff($keys, array_map('strval', $this->selected)) === [];
    }

    /** Offer "select all matching" once the whole page is ticked and there is more. */
    public function offersMatching(): bool
    {
        return ! $this->selectAllMatching && $this->pageSelected() && $this->matchingCount > count($this->pageKeys());
    }

    public function togglePage(): void
    {
        $this->selectAllMatching = false;
        $this->selected = $this->pageSelected() ? [] : $this->pageKeys();
    }

    public function selectMatching(): void
    {
        $this->selectAllMatching = true;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAllMatching = false;
    }

    /**
     * The listing as CSV: the same columns, search, filters, sort and trashed
     * filter as on screen. Anyone who may browse the BREAD may export it; set
     * tardis.bread.export to false to switch the button off.
     */
    public function export(): StreamedResponse
    {
        abort_unless((bool) config('tardis.bread.export', true), 404);
        app(BreadAuthorization::class)->authorize('browse', $this->slug);

        $exporter = new CsvExporter(array_values($this->layoutFields ?: $this->visibleFields), $this->formfields);
        $filename = $this->slug.'-'.now()->format('Y-m-d-His').'.csv';

        app(ActivityLogger::class)->audit('tardis.bread', 'exported', ['slug' => $this->slug, 'search' => $this->search, 'filters' => array_filter($this->filters)]);

        return $exporter->download($this->matchingQuery(), $filename);
    }

    /** The records the listing shows on every page, in its order. */
    protected function matchingQuery(): Builder
    {
        return $this->query()->build($this->search, $this->sort ?: null, $this->direction, $this->trashed, $this->columnSearch, $this->filters);
    }

    /**
     * The keys a bulk action runs on: the ticked rows, or every match when
     * "select all matching" is on, up to tardis.bread.bulk_limit.
     *
     * @return list<int|string>
     */
    protected function bulkKeys(): array
    {
        if (! $this->selectAllMatching) {
            return array_values(array_unique($this->selected));
        }

        $query = $this->matchingQuery();
        $limit = max(1, (int) config('tardis.bread.bulk_limit', 1000));
        $keys = $query->limit($limit + 1)->pluck($query->getModel()->getQualifiedKeyName())->all();

        if (count($keys) > $limit) {
            throw ValidationException::withMessages(['selected' => __('tardis::bread.bulk_too_many', ['limit' => $limit])]);
        }

        return $keys;
    }

    /** Run a bulk action on the ticked rows, or on every match. */
    public function runBulk(string $name): void
    {
        $action = app(ActionManager::class)->find($this->slug, $name) ?? abort(404);

        abort_unless($action->isBulk(), 404);

        $keys = $this->bulkKeys();

        // All or nothing: a record that vanished or a refused permission
        // half-way must not leave the first rows changed and the rest not.
        $done = DB::transaction(function () use ($action, $keys) {
            $done = 0;

            foreach ($keys as $id) {
                $done += (int) $this->perform($action, $this->findRecord($id));
            }

            return $done;
        });

        $this->clearSelection();

        if ($done > 0) {
            session()->flash('message', __('tardis::bread.bulk_done', ['title' => $action->getTitle(), 'count' => $done]));
        }
    }

    /** Authorised per record, so a bulk run cannot touch a row the user may not act on. */
    protected function perform(Action $action, Model $record): bool
    {
        app(BreadAuthorization::class)->authorize($action->getPermission(), $this->slug, $record);

        if (! $action->appliesTo($record)) {
            return false;
        }

        $action->handle($record, $this->slug);

        return true;
    }

    /** @return Collection<string, Action> the actions the user may use on this BREAD */
    public function actions(): Collection
    {
        $auth = app(BreadAuthorization::class);

        return app(ActionManager::class)->for($this->slug)
            ->filter(fn (Action $action) => $auth->allows($action->getPermission(), $this->slug));
    }

    public function delete(int|string $id): void
    {
        $this->runAction('delete', $id);
    }

    public function restore(int|string $id): void
    {
        $this->runAction('restore', $id);
    }

    public function forceDelete(int|string $id): void
    {
        $this->runAction('force-delete', $id);
    }
};
