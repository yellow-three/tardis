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

    public function listing(string $search = '', ?string $sort = null, string $direction = 'asc', int $perPage = 15, string $trashed = 'without'): BreadListing
    {
        DB::enableQueryLog();
        $start = hrtime(true);

        $rows = $this->build($search, $sort, $direction, $trashed)
            ->paginate(in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 15);

        $ms = round((hrtime(true) - $start) / 1_000_000, 2);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return new BreadListing($rows, $ms, $this->warnings($queries));
    }

    public function build(string $search = '', ?string $sort = null, string $direction = 'asc', string $trashed = 'without'): Builder
    {
        $query = $this->bread->query();

        if ($this->supportsTrashed()) {
            match ($trashed) {
                'with' => $query->withTrashed(),
                'only' => $query->onlyTrashed(),
                default => null,
            };
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

        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if ($sort !== null && in_array($sort, $this->orderable(), true)) {
            $query->orderBy($sort, $direction);
        } elseif ($this->bread->orderColumn) {
            $query->orderBy($this->bread->orderColumn, $this->bread->orderDirection === 'desc' ? 'desc' : 'asc');
        }

        return $query;
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
