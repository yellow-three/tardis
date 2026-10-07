<?php

namespace Tardis\Bread\Actions;

use Illuminate\Database\Eloquent\Model;
use Tardis\Bread\Action;
use Tardis\Events\BreadRecordDeleted;

class DeleteAction extends Action
{
    public string $icon = 'trash';

    public string $tone = 'error';

    public ?string $permission = 'delete';

    public bool $bulk = true;

    public function getTitle(): string
    {
        return __('tardis::bread.delete');
    }

    public function getConfirmMessage(): ?string
    {
        return __('tardis::bread.confirm_delete_record');
    }

    public function getSuccessMessage(): string
    {
        return __('tardis::bread.item_deleted');
    }

    public function appliesTo(Model $record): bool
    {
        return ! (method_exists($record, 'trashed') && $record->trashed());
    }

    public function handle(Model $record, string $slug): mixed
    {
        $deleted = $record->delete();

        BreadRecordDeleted::dispatch($slug, $record);

        return $deleted;
    }
}
