<?php

declare(strict_types=1);

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Concerns\SearchesRelatedRecords;
use Tardis\Formfields\Formfield;

/**
 * A foreign key column picked from the related records, such as a post's
 * category_id. The field name is the column; the value stored is the related
 * record's key.
 *
 * Only a record the picker would offer can be stored: one outside the related
 * BREAD's scope or the user's browse permission is written as null.
 */
class BelongsToField extends Formfield
{
    use SearchesRelatedRecords;

    protected array $configurable = ['relation', 'model', 'label_column' => 'labelColumn'];

    /** The relation method on the model; only informative for this type. */
    public ?string $relation = null;

    public ?string $model = null;

    public string $labelColumn = 'name';

    public function store(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->selectableIds([$value]) === [] ? null : $value;
    }

    public function browse(mixed $value): mixed
    {
        return $this->labelFor($value) ?? $value;
    }

    public function read(mixed $value): mixed
    {
        return $this->labelFor($value) ?? $value;
    }

    public function relation(string $relation): self
    {
        $this->relation = $relation;

        return $this;
    }

    public function type(): string
    {
        return 'belongs_to';
    }

    public function render(): string
    {
        return 'tardis::formfields.belongs-to';
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
