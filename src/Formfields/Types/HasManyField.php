<?php

namespace Tardis\Formfields\Types;

use Illuminate\Database\Eloquent\Model;
use Tardis\Formfields\Formfield;

class HasManyField extends Formfield
{
    protected array $configurable = ['relation', 'model'];

    public ?string $relation = null;

    public ?string $model = null;

    public function isRelation(): bool
    {
        return true;
    }

    public function stored(mixed $value, Model $model): void
    {
        if (! $this->relation || ! $model->exists || ! is_array($value) || $value === []) {
            return;
        }

        $model->{$this->relation}()->createMany($value);
    }

    public function updated(mixed $value, Model $model): void
    {
        if (! $this->relation || ! $model->exists || ! is_array($value)) {
            return;
        }

        $existing = $model->{$this->relation}()->get()->keyBy('id');
        $keep = [];

        foreach ($value as $row) {
            $row = (array) $row;
            $id = isset($row['id']) ? (int) $row['id'] : null;

            if ($id !== null && $existing->has($id)) {
                $data = $row;
                unset($data['id']);
                $existing[$id]->update($data);
                $keep[] = $id;
            } else {
                unset($row['id']);
                $created = $model->{$this->relation}()->create($row);
                $keep[] = (int) $created->getKey();
            }
        }

        $existing->each(function (Model $related) use ($keep) {
            if (! in_array((int) $related->getKey(), $keep, true)) {
                $related->delete();
            }
        });
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

    public function type(): string
    {
        return 'has_many';
    }

    public function render(): string
    {
        return 'tardis::formfields.has-many';
    }

    protected function extraViewData(): array
    {
        return [
            'relation' => $this->relation,
            'relatedModel' => $this->model,
        ];
    }
}
