<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\Action;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadListing;
use Tardis\Bread\BreadManager;
use Tardis\Bread\BreadQuery;
use Tardis\Formfields\Formfield;
use Tardis\Manager\ActionManager;
use Tardis\Manager\FormfieldManager;

new #[Title('BREAD')] #[Layout('tardis::layouts.admin')] class extends Component
{
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

    /** @var array<string, bool> named filters currently applied, keyed by name */
    public array $filters = [];

    /** @var array<string, string> inline per-column search terms, keyed by column */
    public array $columnSearch = [];

    #[Locked]
    public array $bread = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $definition = app(BreadManager::class)->find($slug);

        if (! $definition) {
            abort(404);
        }

        $this->bread = $definition->toDisplayArray();

        app(BreadAuthorization::class)->authorize('browse', $this->slug);
    }

    protected function bread(): BreadDefinition
    {
        return BreadDefinition::fromArray($this->bread);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingTrashed(): void
    {
        $this->resetPage();
    }

    public function updatingColumnSearch(): void
    {
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
        return new BreadQuery($this->bread());
    }

    #[Computed]
    public function listing(): ?BreadListing
    {
        $model = $this->bread['model'] ?? null;

        if (! $model || ! class_exists($model)) {
            return null;
        }

        return $this->query()->listing($this->search, $this->sort ?: null, $this->direction, $this->perPage, $this->trashed, $this->columnSearch, $this->filters);
    }

    /** @return array<string, array<string, mixed>> named filters declared by the layout */
    public function getNamedFiltersProperty(): array
    {
        return $this->query()->namedFilters();
    }

    /** @return array<int, string> columns that accept an inline search term */
    public function getSearchableColumnsProperty(): array
    {
        return $this->query()->searchable();
    }

    public function getHasFiltersProperty(): bool
    {
        return $this->search !== ''
            || array_filter($this->columnSearch) !== []
            || array_filter($this->filters) !== []
            || $this->sort !== ''
            || $this->trashed !== 'without';
    }

    public function getRowsProperty()
    {
        return $this->listing?->rows ?? collect();
    }

    public function getExecutionMsProperty(): float
    {
        return $this->listing?->executionMs ?? 0.0;
    }

    public function getWarningsProperty(): array
    {
        return $this->listing?->warnings ?? [];
    }

    public function getLayoutFieldsProperty(): array
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

    public function getVisibleFieldsProperty(): array
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
    public function getFormfieldsProperty(): array
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

        if (! $related instanceof Collection) {
            $related = collect($related);
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

    public function getCreateUrlProperty(): string
    {
        return url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$this->slug.'/create');
    }

    protected function findRecord(int|string $id): Model
    {
        $model = $this->bread['model'] ?? null;

        if (! $model || ! class_exists($model)) {
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

    /** Run a bulk action on the ticked rows. */
    public function runBulk(string $name): void
    {
        $action = app(ActionManager::class)->find($this->slug, $name) ?? abort(404);

        abort_unless($action->isBulk(), 404);

        $done = 0;

        foreach (array_unique($this->selected) as $id) {
            $done += (int) $this->perform($action, $this->findRecord($id));
        }

        $this->selected = [];

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
