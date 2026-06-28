<?php

namespace Tardis\Bread\Actions;

use Tardis\Bread\Action;

class RestoreAction extends Action
{
    public string $title = 'Restore';

    public string $icon = 'check-circle';

    public string $method = 'POST';

    public ?string $confirmMessage = 'Are you sure you want to restore this item?';

    public bool $bulk = true;

    public function handle($model, array $ids = []): mixed
    {
        if ($this->isBulk() && ! empty($ids)) {
            return $model::withTrashed()->whereIn('id', $ids)->restore();
        }

        return $model->restore();
    }
}
