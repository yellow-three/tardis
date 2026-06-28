<?php

namespace Tardis\Bread\Actions;

use Tardis\Bread\Action;

class DeleteAction extends Action
{
    public string $title = 'Delete';

    public string $icon = 'x-mark';

    public string $method = 'DELETE';

    public ?string $confirmMessage = 'Are you sure you want to delete this item?';

    public bool $bulk = true;

    public function handle($model, array $ids = []): mixed
    {
        if ($this->isBulk() && ! empty($ids)) {
            return $model::whereIn('id', $ids)->delete();
        }

        return $model->delete();
    }
}
