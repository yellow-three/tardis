<?php

declare(strict_types=1);

namespace Tardis\Bread\Actions;

use Illuminate\Database\Eloquent\Model;
use Tardis\Bread\Action;

class RestoreAction extends Action
{
    public string $icon = 'arrow-uturn-left';

    public ?string $permission = 'edit';

    public bool $bulk = true;

    public function getTitle(): string
    {
        return __('tardis::bread.restore');
    }

    public function getSuccessMessage(): string
    {
        return __('tardis::bread.item_restored');
    }

    public function appliesTo(Model $record): bool
    {
        return method_exists($record, 'trashed') && $record->trashed();
    }

    public function handle(Model $record, string $slug): mixed
    {
        // appliesTo() only offers this on a trashed record of a SoftDeletes model.
        return method_exists($record, 'restore') ? $record->restore() : false;
    }
}
