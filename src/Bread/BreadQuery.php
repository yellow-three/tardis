<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Builds the browse listing of a BREAD: search, sorting, page size and
 * soft-delete visibility. Every column that reaches the query comes from the
 * definition's own field list, never straight from the request.
 */
class BreadQuery
{
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

    public const TRASHED = ['without', 'with', 'only'];

    /** Comparison operators a named filter may use. */
    public const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'like'];

    /** Field types backed by a relationship rather than a column. */
    public const RELATION_TYPES = ['belongs_to_many', 'has_many'];

    /** Field types that have no column of their own. */
    protected const NO_COLUMN = ['belongs_to_many', 'has_many'];

    public function __construct(protected BreadDefinition $bread) {}

    /**
     * Columns the search box looks in: fields flagged `searchable`, else the
     * legacy `search_key`.
     *
     * @return array<int, string>
     */
    public function searchable(): array
    {
        $names = [];

        $layout = $this->bread->layout['list'] ?? $this->bread->layout['browse'] ?? [];
        if (! empty($layout) && is_array($layout)) {
            foreach ($layout as $key => $config) {
                if (is_string($key) && is_array($config) && ! empty($config['searchable'])) {
                    $field = $this->bread->getField($key);
                    if ($field && $this->hasColumn($field)) {
                        $names[] = (string) $key;
                    }
                } elseif (is_array($config) && isset($config['name']) && ! empty($config['searchable'])) {
                    $field = $this->bread->getField($config['name']);
                    if ($field && $this->hasColumn($field)) {
                        $names[] = (string) $config['name'];
                    }
                }
            }
        }

        if ($names === []) {
            foreach ($this->bread->fields as $field) {
                if (! empty($field['searchable']) && $this->hasColumn($field)) {
                    $names[] = (string) $field['name'];
                }
            }
        }

        if ($names === [] && $this->bread->searchKey) {
            $names[] = $this->bread->searchKey;
        }

        return array_values(array_unique($names));
    }

    /**
     * Columns a header click may sort by: visible fields with a column, unless
     * the definition says `orderable: false`.
     *
     * @return array<int, string>
     */

    /**
     * Browse columns from layout (named list/browse) if defined, otherwise fallback to fields.
     *
     * @return array<int, array<string, mixed>>
     */
    public function browseColumns(): array
    {
        $layout = $this->bread->layout['list'] ?? $this->bread->layout['browse'] ?? [];

        if (! empty($layout) && is_array($layout)) {
            $result = [];
            foreach ($layout as $key => $config) {
                if (is_string($key) && is_array($config)) {
                    $field = $this->bread->getField($key);
                    if ($field) {
                        $result[] = array_merge($field, $config);
                    }
                } elseif (is_string($config)) {
                    $field = $this->bread->getField($config);
                    if ($field) {
                        $result[] = $field;
                    }
                } elseif (is_array($config) && isset($config['name'])) {
                    $field = $this->bread->getField($config['name']);
                    if ($field) {
                        $result[] = array_merge($field, $config);
                    } else {
                        $result[] = $config;
                    }
                }
            }

            return $result;
        }

        return $this->bread->getBrowseFields();
    }

    public function orderable(): array
    {
        $names = [];

        $layout = $this->bread->layout['list'] ?? $this->bread->layout['browse'] ?? [];
        if (! empty($layout) && is_array($layout)) {
            foreach ($layout as $key => $config) {
                if (is_string($key) && is_array($config)) {
                    $orderable = $config['orderable'] ?? ($config['sortable'] ?? true);
                    if ($orderable) {
                        $field = $this->bread->getField($key);
                        if ($field && $this->hasColumn($field)) {
                            $names[] = (string) $key;
                        }
                    }
                } elseif (is_array($config) && isset($config['name'])) {
                    $orderable = $config['orderable'] ?? ($config['sortable'] ?? true);
                    if ($orderable) {
                        $field = $this->bread->getField($config['name']);
                        if ($field && $this->hasColumn($field)) {
                            $names[] = (string) $config['name'];
                        }
                    }
                }
            }
        }

        if ($names === []) {
            foreach ($this->bread->fields as $field) {
                if (($field['browse'] ?? true) && ($field['orderable'] ?? true) && $this->hasColumn($field)) {
                    $names[] = (string) $field['name'];
                }
            }
        }

        return $names;
    }

    public function supportsTrashed(): bool
    {
        return $this->bread->softDelete
            && class_exists($this->bread->model)
            && in_array(SoftDeletes::class, class_uses_recursive($this->bread->model), true);
    }

    /**
     * Named filters declared in the layout, keyed by name. Each filter targets
     * either a column (with an operator and value) or a model scope.
     *
     * @return array<string, array{key: string, label: string, color: ?string, icon: ?string, column: ?string, operator: string, value: mixed, scope: ?string}>
     */
    public function namedFilters(): array
    {
        $filters = [];

        foreach ($this->filterOptions() as $key => $config) {
            if (! is_array($config)) {
                continue;
            }

            $name = (string) ($config['key'] ?? (is_string($key) ? $key : ($config['name'] ?? '')));
            if ($name === '') {
                continue;
            }

            $column = isset($config['column']) && $config['column'] !== '' ? (string) $config['column'] : null;
            $scope = isset($config['scope']) && $config['scope'] !== '' ? (string) $config['scope'] : null;

            // A filter is only actionable when it targets a column or a scope.
            if ($column === null && $scope === null) {
                continue;
            }

            $filters[$name] = [
                'key' => $name,
                'label' => (string) ($config['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $name))),
                'color' => isset($config['color']) ? (string) $config['color'] : null,
                'icon' => isset($config['icon']) ? (string) $config['icon'] : null,
                'column' => $column,
                'operator' => $this->normalizeOperator((string) ($config['operator'] ?? '=')),
                'value' => $config['value'] ?? null,
                'scope' => $scope,
            ];
        }

        return $filters;
    }

    /**
     * Relations that visible columns read through, for eager loading.
     *
     * @return array<int, string>
     */
    public function eagerLoads(): array
    {
        $relations = [];

        foreach ($this->browseColumns() as $field) {
            if (! in_array($field['type'] ?? '', self::RELATION_TYPES, true)) {
                continue;
            }

            $relation = (string) ($field['relation'] ?? $field['name'] ?? '');
            if ($relation === '' || ! $this->hasRelation($relation)) {
                continue;
            }

            $relations[] = $relation;
        }

        return array_values(array_unique($relations));
    }

    public function listing(string $search = '', ?string $sort = null, string $direction = 'asc', int $perPage = 15, string $trashed = 'without', array $columnSearch = [], array $activeFilters = []): BreadListing
    {
        DB::enableQueryLog();
        $start = hrtime(true);

        $rows = $this->build($search, $sort, $direction, $trashed, $columnSearch, $activeFilters)
            ->paginate(in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 15);

        $ms = round((hrtime(true) - $start) / 1_000_000, 2);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return new BreadListing($rows, $ms, $this->warnings($queries));
    }

    public function build(string $search = '', ?string $sort = null, string $direction = 'asc', string $trashed = 'without', array $columnSearch = [], array $activeFilters = []): Builder
    {
        $query = $this->bread->query();

        if ($this->supportsTrashed()) {
            match ($trashed) {
                'with' => $query->withTrashed(),
                'only' => $query->onlyTrashed(),
                default => null,
            };
        }

        $eager = $this->eagerLoads();
        if ($eager !== []) {
            $query->with($eager);
        }

        $columns = $this->searchable();

        if ($search !== '' && $columns !== []) {
            // `!` is the escape character: unlike a backslash it means the same in every database.
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $grammar = $query->getQuery()->getGrammar();

            $query->where(function (Builder $inner) use ($columns, $term, $grammar) {
                foreach ($columns as $column) {
                    $inner->orWhereRaw($grammar->wrap($column)." like ? escape '!'", [$term]);
                }
            });
        }

        $this->applyColumnSearch($query, $columnSearch);
        $this->applyNamedFilters($query, $activeFilters);

        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if ($sort !== null && in_array($sort, $this->orderable(), true)) {
            $query->orderBy($sort, $direction);
        } elseif ($this->bread->orderColumn) {
            $query->orderBy($this->bread->orderColumn, $this->bread->orderDirection === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }

    /**
     * Inline per-column search. Only columns the definition already declares as
     * searchable are honoured, so a forged column name can never reach SQL.
     *
     * @param  array<string, mixed>  $columnSearch
     */
    protected function applyColumnSearch(Builder $query, array $columnSearch): void
    {
        $allowed = $this->searchable();
        if ($allowed === []) {
            return;
        }

        $grammar = $query->getQuery()->getGrammar();

        foreach ($columnSearch as $column => $term) {
            $column = (string) $column;
            $term = is_string($term) ? trim($term) : '';

            if ($term === '' || ! in_array($column, $allowed, true)) {
                continue;
            }

            $escaped = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

            $query->whereRaw($grammar->wrap($column)." like ? escape '!'", [$escaped]);
        }
    }

    /**
     * Named filters: a column comparison or a model scope. Unknown filters and
     * columns the definition does not expose are silently ignored.
     *
     * @param  array<string|int, mixed>  $active  either {name: true} or a list of names
     */
    protected function applyNamedFilters(Builder $query, array $active): void
    {
        $defined = $this->namedFilters();
        $keys = [];

        foreach ($active as $key => $value) {
            if (is_int($key)) {
                $keys[] = (string) $value;
            } elseif ($value) {
                $keys[] = (string) $key;
            }
        }

        foreach (array_unique($keys) as $key) {
            $filter = $defined[$key] ?? null;
            if ($filter === null) {
                continue;
            }

            if ($filter['scope'] !== null && $this->hasScope($filter['scope'])) {
                $query->scopes([$filter['scope']]);

                continue;
            }

            if ($filter['column'] !== null && $this->columnExists($filter['column'])) {
                $query->where($filter['column'], $filter['operator'], $filter['value']);
            }
        }
    }

    /** @return array<int|string, mixed> */
    protected function filterOptions(): array
    {
        $layout = $this->bread->layout;

        $filters = $layout['options']['filters']
            ?? $layout['list']['options']['filters']
            ?? $layout['browse']['options']['filters']
            ?? [];

        return is_array($filters) ? $filters : [];
    }

    protected function normalizeOperator(string $operator): string
    {
        $operator = strtolower(trim($operator));

        return in_array($operator, self::OPERATORS, true) ? $operator : '=';
    }

    protected function hasRelation(string $relation): bool
    {
        return $this->bread->model !== ''
            && class_exists($this->bread->model)
            && method_exists($this->bread->model, $relation);
    }

    protected function hasScope(string $scope): bool
    {
        return $this->bread->model !== ''
            && class_exists($this->bread->model)
            && method_exists($this->bread->model, 'scope'.ucfirst($scope));
    }

    protected function columnExists(string $column): bool
    {
        $field = $this->bread->getField($column);

        return $field !== null && $this->hasColumn($field);
    }

    protected function hasColumn(array $field): bool
    {
        return ! empty($field['name']) && ! in_array($field['type'] ?? 'text', self::NO_COLUMN, true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $queries
     * @return array<int, string>
     */
    protected function warnings(array $queries): array
    {
        $warnings = [];

        foreach ($queries as $entry) {
            if (($entry['time'] ?? 0) > 200) {
                $warnings[] = __('tardis::bread.slow_query', ['ms' => $entry['time'], 'sql' => substr($entry['query'] ?? '', 0, 120)]);
            }
        }

        if (count($queries) > 15) {
            $warnings[] = __('tardis::bread.high_query_count', ['count' => count($queries)]);
        }

        return $warnings;
    }
}
