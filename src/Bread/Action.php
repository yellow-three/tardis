<?php

namespace Tardis\Bread;

abstract class Action
{
    public string $title = '';

    public string $icon = 'cog-6-tooth';

    public string $method = 'POST';

    public ?string $route = null;

    public ?string $permission = null;

    public ?string $confirmMessage = null;

    public ?string $successMessage = 'Action completed successfully.';

    public bool $download = false;

    public bool $bulk = false;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function getConfirmMessage(): ?string
    {
        return $this->confirmMessage;
    }

    public function getSuccessMessage(): string
    {
        return $this->successMessage;
    }

    public function isDownload(): bool
    {
        return $this->download;
    }

    public function isBulk(): bool
    {
        return $this->bulk;
    }

    abstract public function handle($model, array $ids = []): mixed;
}
