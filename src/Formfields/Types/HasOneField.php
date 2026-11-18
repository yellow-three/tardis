<?php

declare(strict_types=1);

namespace Tardis\Formfields\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Tardis\Formfields\Concerns\SearchesRelatedRecords;
use Tardis\Formfields\Formfield;

/**
 * The one related record whose foreign key points at this record, such as a
 * user's profile. Picking a record sets its foreign key to this record's key;
 * the record linked before is unlinked (its foreign key set to null, so that
 * column must be nullable). Picking nothing unlinks.
 */
class HasOneField extends Formfield
{
    use SearchesRelatedRecords;

    protected array $configurable = ['relation', 'model', 'label_column' => 'labelColumn'];

    public ?string $relation = null;

    public ?string $model = null;

    public string $labelColumn = 'name';

    public function isRelation(): bool
    {
        return true;
    }

    public function stored(mixed $value, Model $model): void
    {
        $relation = $this->hasOne($model);

        if ($relation === null) {
            return;
        }

        $foreignKey = $relation->getForeignKeyName();
        $parentKey = $relation->getParentKey();
        $related = $relation->getRelated();
        $chosen = $this->selectableIds([is_array($value) ? null : $value])[0] ?? null;

        DB::transaction(function () use ($related, $foreignKey, $parentKey, $chosen) {
            $linked = $related->newQuery()->where($foreignKey, $parentKey);

            if ($chosen !== null) {
                $linked->whereKeyNot($chosen);
            }

            $linked->update([$foreignKey => null]);

            if ($chosen !== null) {
                $related->newQuery()->whereKey($chosen)->update([$foreignKey => $parentKey]);
            }
        });
    }

    /**
     * The detail page hands over the related model itself (the field is named
     * after the relation); show its label.
     */
    public function read(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return data_get($value, $this->labelColumn) ?? $value->getKey();
        }

        return $value;
    }

    /**
     * The key of the related record linked now, for the edit form.
     */
    public function currentKey(Model $model): mixed
    {
        $relation = $this->hasOne($model);

        return $relation?->getQuery()->value($relation->getRelated()->getKeyName());
    }

    public function relation(string $relation): self
    {
        $this->relation = $relation;

        return $this;
    }

    public function type(): string
    {
        return 'has_one';
    }

    public function render(): string
    {
        return 'tardis::formfields.has-one';
    }

    protected function hasOne(Model $model): ?HasOne
    {
        if (! $this->relation || ! $model->exists || ! method_exists($model, $this->relation)) {
            return null;
        }

        $relation = $model->{$this->relation}();

        return $relation instanceof HasOne ? $relation : null;
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
