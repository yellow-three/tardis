<?php

declare(strict_types=1);

namespace Tardis\Formfields\Types;

use Illuminate\Database\Eloquent\Model;
use Tardis\Formfields\Formfield;

class HasManyField extends Formfield
{
    protected array $configurable = ['relation', 'model', 'fields'];

    public ?string $relation = null;

    public ?string $model = null;

    /** @var array<int, mixed> Declared child fields (definitions or bare names). */
    public array $fields = [];

    public function isRelation(): bool
    {
        return true;
    }

    /**
     * The field names a child row may write. A definition that declares none
     * keeps the historical behaviour of writing the whole submitted row.
     *
     * @return list<string>|null
     */
    private function declaredChildKeys(bool $includeKey = false): ?array
    {
        if ($this->fields === []) {
            return null;
        }

        $names = [];

        foreach ($this->fields as $field) {
            if (is_string($field)) {
                $names[] = $field;

                continue;
            }

            if (is_array($field) && ! empty($field['name'])) {
                $names[] = (string) $field['name'];
            }
        }

        if ($includeKey) {
            $names[] = 'id';
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function writableRow(array $row, bool $includeKey = false): array
    {
        $allowed = $this->declaredChildKeys($includeKey);

        return $allowed === null ? $row : array_intersect_key($row, array_flip($allowed));
    }

    public function stored(mixed $value, Model $model): void
    {
        if (! $this->relation || ! $model->exists || ! is_array($value) || $value === []) {
            return;
        }

        $rows = array_map(fn (mixed $row): array => $this->writableRow((array) $row), $value);

        $model->{$this->relation}()->createMany($rows);
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
                // The child key is needed to identify the row, never to write it.
                $data = $this->writableRow($row, true);
                unset($data['id']);
                $existing[$id]->update($data);
                $keep[] = $id;
            } else {
                $data = $this->writableRow($row);
                unset($data['id']);
                $created = $model->{$this->relation}()->create($data);
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
