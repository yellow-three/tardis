<?php

namespace Tardis\Formfields\Types;

use Illuminate\Database\Eloquent\Model;
use Tardis\Formfields\Formfield;

class BelongsToManyField extends Formfield
{
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

        $model->{$this->relation}()->sync($ids);
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

    public function viewData(): array
    {
        return array_merge(parent::viewData(), [
            'relation' => $this->relation,
            'model' => $this->model,
            'labelColumn' => $this->labelColumn,
        ]);
    }
}
