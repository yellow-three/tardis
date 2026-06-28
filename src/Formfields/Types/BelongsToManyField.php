<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class BelongsToManyField extends Formfield
{
    public ?string $relation = null;

    public ?string $model = null;

    public string $labelColumn = 'name';

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
