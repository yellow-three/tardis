<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\Action;
use Tardis\Bread\ActionManager;
use Tardis\Bread\ActivityLogger;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadListing;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\BreadSaver;
use Tardis\Bread\Export\CsvExporter;
use Tardis\Bread\Export\Xlsx;
use Tardis\Bread\FieldValidationRules;
use Tardis\Bread\Import\CsvImporter;
use Tardis\Bread\Livewire\ReadsBreadDefinition;
use Tardis\Formfields\Formfield;
use Tardis\Formfields\FormfieldManager;
use Tardis\Support\UserPreferences;

new #[Title('tardis::shell.titles.bread')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use ReadsBreadDefinition;
    use WithFileUploads;
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

    /** The CSV chosen in the import dialog. */
    public $importFile = null;

    /** @var array{created: int, updated: int, errors: array<int, list<string>>, ignored: list<string>}|null */
    public ?array $importReport = null;

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
        $record = $this->findRecord($id);

        if (($target = $action->redirectTo($record, $this->slug)) !== null) {
            app(BreadAuthorization::class)->authorize($action->getPermission(), $this->slug, $record);

            $this->redirect($target, navigate: true);

            return;
        }

        if ($this->perform($action, $record)) {
            session()->flash('message', $action->getSuccessMessage());
        }
    }

    // ------------------------------------------------------- saved list views

    /** The refinements a saved view keeps. */
    protected const VIEW_STATE = ['search', 'sort', 'direction', 'perPage', 'trashed', 'filters', 'columnSearch'];

    public string $viewName = '';

    /** @return array<string, array<string, mixed>> this user's saved views of this BREAD, by name */
    #[Computed]
    public function savedViews(): array
    {
        $views = app(UserPreferences::class)->get(auth()->id(), 'list_views', []);

        return is_array($views[$this->slug] ?? null) ? $views[$this->slug] : [];
    }

    public function saveView(): void
    {
        $name = trim($this->viewName);
        $this->validate(['viewName' => ['required', 'string', 'max:60']]);

        $views = $this->savedViews;

        if (! isset($views[$name]) && count($views) >= 20) {
            $this->addError('viewName', __('tardis::bread.views.too_many'));

            return;
        }

        $views[$name] = collect(self::VIEW_STATE)->mapWithKeys(fn (string $key) => [$key => $this->{$key}])->all();
        $this->storeViews($views);
        $this->viewName = '';
        $this->dispatch('tardis-toast', message: __('tardis::bread.views.saved', ['name' => $name]), type: 'success');
    }

    public function applyView(string $name): void
    {
        $view = $this->savedViews[$name] ?? null;

        if (! is_array($view)) {
            return;
        }

        $this->search = (string) ($view['search'] ?? '');
        $this->sort = in_array($view['sort'] ?? '', $this->query()->orderable(), true) ? (string) $view['sort'] : '';
        $this->direction = ($view['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $this->perPage = in_array((int) ($view['perPage'] ?? 15), BreadQuery::PER_PAGE_OPTIONS, true) ? (int) $view['perPage'] : 15;
        $this->trashed = in_array($view['trashed'] ?? '', BreadQuery::TRASHED, true) ? (string) $view['trashed'] : 'without';
        $this->filters = array_intersect_key(array_map('boolval', (array) ($view['filters'] ?? [])), $this->query()->namedFilters());
        $this->columnSearch = array_map('strval', array_filter((array) ($view['columnSearch'] ?? []), 'is_scalar'));
        $this->selectAllMatching = false;
        $this->resetPage();
    }

    public function deleteView(string $name): void
    {
        $views = $this->savedViews;
        unset($views[$name]);
        $this->storeViews($views);
    }

    /** @param  array<string, array<string, mixed>>  $views */
    protected function storeViews(array $views): void
    {
        $preferences = app(UserPreferences::class);
        $all = (array) $preferences->get(auth()->id(), 'list_views', []);

        if ($views === []) {
            unset($all[$this->slug]);
        } else {
            $all[$this->slug] = $views;
        }

        $preferences->set(auth()->id(), 'list_views', $all);
        unset($this->savedViews);
    }

    // ---------------------------------------------------------- inline editing

    /** Field types a list cell can edit in place. */
    protected const INLINE_TYPES = ['text', 'number', 'email', 'url'];

    /** @return array<string, true> names of the list fields this user may edit in place */
    #[Computed]
    public function inlineFields(): array
    {
        if (! app(BreadAuthorization::class)->allows('edit', $this->slug)) {
            return [];
        }

        $names = [];

        foreach ($this->bread['fields'] ?? [] as $field) {
            if (! empty($field['inline']) && ($field['edit'] ?? true) !== false && empty($field['translatable'])
                && in_array($field['type'] ?? 'text', self::INLINE_TYPES, true)) {
                $names[(string) $field['name']] = true;
            }
        }

        return $names;
    }

    /** Save one cell, with the field's own rules and the record's edit ability. */
    public function updateInline(int|string $id, string $name, mixed $value): bool
    {
        if (! isset($this->inlineFields[$name])) {
            return false;
        }

        $record = $this->findRecord($id);
        app(BreadAuthorization::class)->authorize('edit', $this->slug, $record);

        $field = collect($this->bread['fields'] ?? [])->firstWhere('name', $name);
        $form = [$name => is_scalar($value) || $value === null ? $value : null];

        $validator = Validator::make(['form' => $form], FieldValidationRules::for([$field], $form, null, 'edit'));

        if ($validator->fails()) {
            $this->dispatch('tardis-toast', message: $validator->errors()->first(), type: 'error');

            return false;
        }

        app(BreadSaver::class)->update($this->slug, $record, [$field], $form);
        unset($this->listing);
        $this->dispatch('tardis-toast', message: __('tardis::bread.item_updated'), type: 'success');

        return true;
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
    public function export(string $format = 'csv'): StreamedResponse|BinaryFileResponse
    {
        abort_unless((bool) config('tardis.bread.export', true), 404);
        app(BreadAuthorization::class)->authorize('browse', $this->slug);

        $xlsx = $format === 'xlsx' && Xlsx::available();
        $exporter = new CsvExporter(array_values($this->layoutFields ?: $this->visibleFields), $this->formfields);
        $filename = $this->slug.'-'.now()->format('Y-m-d-His').($xlsx ? '.xlsx' : '.csv');

        app(ActivityLogger::class)->audit('tardis.bread', 'exported', ['slug' => $this->slug, 'format' => $xlsx ? 'xlsx' : 'csv', 'search' => $this->search, 'filters' => array_filter($this->filters)]);

        return $xlsx
            ? Xlsx::download($exporter, $this->matchingQuery(), $filename)
            : $exporter->download($this->matchingQuery(), $filename);
    }

    /** Whether .xlsx export and import are offered (openspout/openspout installed). */
    public function xlsxAvailable(): bool
    {
        return Xlsx::available();
    }

    /** Whether the import button is offered: the user may add or edit records. */
    public function canImport(): bool
    {
        $auth = app(BreadAuthorization::class);

        return (bool) config('tardis.bread.import', true) && ($auth->allows('add', $this->slug) || $auth->allows('edit', $this->slug));
    }

    /**
     * Read the chosen CSV into the BREAD (see CsvImporter): all rows or none,
     * each authorised as an add or an edit.
     */
    public function import(): void
    {
        abort_unless($this->canImport(), 403);

        $mimes = Xlsx::available() ? 'mimes:csv,txt,xlsx' : 'mimes:csv,txt';
        $this->validate(['importFile' => ['required', 'file', $mimes, 'max:'.max(1, (int) config('tardis.bread.import_max_kb', 5120))]]);

        $path = $this->importFile->getRealPath();
        $converted = null;

        if (Xlsx::available() && strtolower($this->importFile->getClientOriginalExtension()) === 'xlsx') {
            try {
                $path = $converted = Xlsx::toCsv($path);
            } catch (Throwable) {
                $this->addError('importFile', __('tardis::bread.import_unreadable'));

                return;
            }
        }

        try {
            $result = (new CsvImporter($this->slug, $this->definition()))->import($path);
        } finally {
            if ($converted !== null) {
                @unlink($converted);
            }
        }

        $this->importReport = [
            'created' => $result->created,
            'updated' => $result->updated,
            'errors' => array_slice($result->errors, 0, 20, true),
            'ignored' => $result->ignoredColumns,
        ];
        $this->reset('importFile');

        if (! $result->failed()) {
            app(ActivityLogger::class)->audit('tardis.bread', 'imported', ['slug' => $this->slug, 'created' => $result->created, 'updated' => $result->updated]);
            $this->resetPage();
        }
    }

    public function closeImport(): void
    {
        $this->reset('importFile', 'importReport');
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
