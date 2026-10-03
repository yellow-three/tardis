<?php

namespace Tardis\Bread;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Something an administrator can do to one record, or to many selected rows,
 * from a BREAD listing. Register it with Tardis::addAction().
 */
abstract class Action
{
    public string $title = '';

    public string $icon = 'cog-6-tooth';

    /** The BREAD ability needed to run it (browse, read, edit, add, delete). */
    public ?string $permission = null;

    public ?string $confirmMessage = null;

    public ?string $successMessage = null;

    /** Whether it can be run on several selected rows at once. */
    public bool $bulk = false;

    /** Colour of its button: ghost, error, ... */
    public string $tone = 'ghost';

    /** Stable key used to register, replace and invoke the action. */
    public function name(): string
    {
        return Str::kebab(Str::beforeLast(class_basename($this), 'Action') ?: class_basename($this));
    }

    public function permission(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title !== '' ? $this->title : Str::headline($this->name());
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getPermission(): string
    {
        return $this->permission ?? 'browse';
    }

    public function getConfirmMessage(): ?string
    {
        return $this->confirmMessage;
    }

    public function getSuccessMessage(): string
    {
        return $this->successMessage ?? __('tardis::bread.action_completed');
    }

    public function isBulk(): bool
    {
        return $this->bulk;
    }

    /** Whether it is offered for this row (e.g. restore only for deleted ones). */
    public function appliesTo(Model $record): bool
    {
        return true;
    }

    /** Do the work on one record. The record was found through the BREAD's own scope. */
    abstract public function handle(Model $record, string $slug): mixed;
}
