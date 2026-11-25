<?php

declare(strict_types=1);

namespace Tardis\Formfields\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Tardis\Bread\RelatedRecords;
use Tardis\Support\Like;

/**
 * The searchable picker behind the relation fields: options come from the
 * related model, labelled by one column, limited the way the related BREAD
 * (if any) limits its own listing.
 *
 * Used by belongs_to, has_one and belongs_to_many. The using class declares
 * public ?string $model and public string $labelColumn.
 */
trait SearchesRelatedRecords
{
    /** @var array<string, string|null> id => label, per field instance */
    private array $labelCache = [];

    /**
     * Search candidate related records by their label column.
     *
     * Already selected ids are always included so that a filtered search
     * never hides the current selection.
     *
     * @param  array<int, mixed>  $selected
     * @return array<int|string, string> map of id => label
     */
    public function searchOptions(string $search = '', array $selected = [], int $limit = 50): array
    {
        $query = $this->optionQuery();

        if ($query === null) {
            return [];
        }

        $key = $query->getModel()->getKeyName();

        if ($search !== '') {
            $query->where($this->labelColumn, Like::operator($query->getQuery()), '%'.$search.'%');
        }

        $options = $query->limit($limit)->pluck($this->labelColumn, $key)->all();

        $selectedIds = array_values(array_filter($selected, fn ($id) => $id !== null && $id !== ''));

        if ($selectedIds !== []) {
            // The same restricted query: a selected id must not reveal a record
            // the scope or the user's permissions keep out of the picker.
            $options = $this->optionQuery()->whereIn($key, $selectedIds)->pluck($this->labelColumn, $key)->all() + $options;
        }

        return $options;
    }

    /**
     * The ids among $ids the picker would offer: a submitted id outside the
     * related BREAD's scope or the user's permissions is dropped.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, mixed>
     */
    public function selectableIds(array $ids): array
    {
        $ids = array_values(array_filter($ids, fn ($id) => $id !== null && $id !== ''));
        $query = $this->optionQuery();

        if ($ids === [] || $query === null) {
            return [];
        }

        $key = $query->getModel()->getKeyName();
        $found = array_flip(array_map('strval', $query->whereIn($key, $ids)->pluck($key)->all()));

        return array_values(array_filter($ids, static fn ($id): bool => isset($found[(string) $id])));
    }

    /**
     * The label of one related record, for listing and detail pages. Labels
     * are remembered per field instance, so a listing asks once per id.
     */
    public function labelFor(mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (! array_key_exists((string) $id, $this->labelCache)) {
            $query = $this->optionQuery();
            $label = $query?->whereKey($id)->value($this->labelColumn);
            $this->labelCache[(string) $id] = $label === null ? null : (string) $label;
        }

        return $this->labelCache[(string) $id];
    }

    /**
     * The query the picker draws its options from.
     *
     * When a BREAD manages the related model, its scope applies and the user
     * needs to be allowed to browse it, so the picker cannot list records the
     * resource itself would hide. A model with no BREAD of its own is listed in
     * full (there is no definition to defer to); null means "show nothing".
     */
    protected function optionQuery(): ?Builder
    {
        return RelatedRecords::scopedQuery($this->model);
    }

    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function labelColumn(string $column): self
    {
        $this->labelColumn = $column;

        return $this;
    }
}
