<?php

declare(strict_types=1);

namespace Tardis\Bread\Actions;

use Illuminate\Database\Eloquent\Model;
use Tardis\Bread\Action;

/**
 * Opens the create form filled with a copy of the record. Nothing is saved
 * until the user submits it, so unique values and validation are theirs to
 * settle, the same as any new record.
 */
class DuplicateAction extends Action
{
    public string $icon = 'document-duplicate';

    public ?string $permission = 'add';

    public function getTitle(): string
    {
        return __('tardis::bread.duplicate');
    }

    public function appliesTo(Model $record): bool
    {
        return ! (method_exists($record, 'trashed') && $record->trashed());
    }

    public function redirectTo(Model $record, string $slug): ?string
    {
        return url(trim((string) config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/create').'?duplicate='.rawurlencode((string) $record->getKey());
    }

    public function handle(Model $record, string $slug): mixed
    {
        return null;
    }
}
