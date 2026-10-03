<?php

namespace Tardis\Formfields\Types;

use Illuminate\Database\Eloquent\Model;
use Tardis\Formfields\Formfield;

class BelongsToManyField extends Formfield
{
    protected array $configurable = ['relation', 'model', 'label_column' => 'labelColumn'];

    public ?string $relation = null;

    public ?string $model = null;

    public string $labelColumn = 'name';

    public function isRelation(): bool
    {
        return true;
    }

    /**
     * Search candidate related records by their label column.
     *
     * Already selected ids are always included so that a filtered search
     * never hides the current selection.
     *
     * @return array<int|string, string> map of id => label
     */
    public function searchOptions(string $search = '', array $selected = [], int $limit = 50): array
    {
        if (! $this->model || ! class_exists($this->model)) {
            return [];
        }

        $query = $this->model::query();

        if ($search !== '') {
            $query->where($this->labelColumn, 'like', '%'.$search.'%');
        }

        $options = $query->limit($limit)->pluck($this->labelColumn, 'id')->all();

        $selectedIds = array_values(array_filter($selected, fn ($id) => $id !== null && $id !== ''));

        if (! empty($selectedIds)) {
            $selectedOptions = $this->model::query()
                ->whereIn('id', $selectedIds)
                ->pluck($this->labelColumn, 'id')
                ->all();

            $options = $selectedOptions + $options;
        }

        return $options;
    }

    public function stored(mixed $value, Model $model): void
    {
        if (! $this->relation || ! $model->exists) {
            return;
        }

        $ids = array_values(array_filter((array) $value, fn ($id) => $id !== null && $id !== ''));

        $model->{$this->relation}()->sync($this->resolvableIds($model, $ids));
    }

    /**
     * Reduce the submitted ids to the ones the relation can actually resolve.
     *
     * sync() writes whatever it is handed straight into the pivot table, so an
     * id naming no record — or one the related model's own scope excludes, such
     * as a soft-deleted row — became a dangling pivot row that the read side
     * then hid, leaving the selection quietly out of sync with the database.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, mixed>
     */
    protected function resolvableIds(Model $model, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $relation = $model->{$this->relation}();
        $related = $relation->getRelated();
        $keyName = $related->getKeyName();

        // Deliberately the related model's own query rather than the relation's:
        // a BelongsToMany constrains its query to the rows already attached to
        // this parent, which on create is nothing at all. Global scopes still
        // apply, so an excluded record stays excluded.
        $resolvable = array_flip(array_map(
            'strval',
            $related->newQuery()->whereIn($keyName, $ids)->pluck($keyName)->all(),
        ));

        // Keep the submitted values rather than the ones read back, so custom
        // and string primary keys survive the round trip untouched.
        return array_values(array_filter(
            $ids,
            static fn ($id): bool => isset($resolvable[(string) $id]),
        ));
    }

    public function relation(string $relation): self
    {
        $this->relation = $relation;

        return $this;
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

    public function type(): string
    {
        return 'belongs_to_many';
    }

    public function render(): string
    {
        return 'tardis::formfields.belongs-to-many';
    }

    protected function extraViewData(): array
    {
        return [
            'relation' => $this->relation,
            'relatedModel' => $this->model,
            'labelColumn' => $this->labelColumn,
        ];
    }
}
