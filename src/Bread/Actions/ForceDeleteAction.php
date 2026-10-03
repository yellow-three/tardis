<?php

namespace Tardis\Bread\Actions;

use Illuminate\Database\Eloquent\Model;
use Tardis\Bread\Action;
use Tardis\Events\BreadRecordDeleted;

class ForceDeleteAction extends Action
{
    public string $icon = 'trash';

    public string $tone = 'error';

    public ?string $permission = 'delete';

    public function getTitle(): string
    {
        return __('tardis::bread.delete_permanently');
    }

    public function getConfirmMessage(): ?string
    {
        return __('tardis::bread.confirm_delete_permanently');
    }

    public function getSuccessMessage(): string
    {
        return __('tardis::bread.item_deleted_permanently');
    }

    public function appliesTo(Model $record): bool
    {
        return method_exists($record, 'trashed') && $record->trashed();
    }

    public function handle(Model $record, string $slug): mixed
    {
        $deleted = $record->forceDelete();

        BreadRecordDeleted::dispatch($slug, $record);

        return $deleted;
    }
}
