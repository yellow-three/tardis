<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

class HasManyField extends Formfield
{
    public ?string $relation = null;

    public ?string $model = null;

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

    public function type(): string
    {
        return 'has_many';
    }

    public function render(): string
    {
        return 'tardis::formfields.has-many';
    }

    public function viewData(): array
    {
        return array_merge(parent::viewData(), [
            'relation' => $this->relation,
            'model' => $this->model,
        ]);
    }
}
