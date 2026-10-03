<?php

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
        return $record->restore();
    }
}
