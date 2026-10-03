<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Support\Collection;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Events\BreadRemoved;
use Tardis\Events\BreadSaved;

class BreadManager
{
    public function __construct(
        protected JsonBreadSource $bread,
    ) {}

    /**
     * @param  array<string, mixed>|BreadDefinition  $bread
     */
    public function save(array|BreadDefinition $bread): void
    {
        $definition = $bread instanceof BreadDefinition ? $bread : BreadDefinition::fromArray($bread);

        $wasNew = ! $this->bread->has($definition->slug);

        $this->bread->save($bread);

        BreadSaved::dispatch($definition, $wasNew);
    }

    public function find(string $slug): ?BreadDefinition
    {
        return $this->bread->find($slug);
    }

    public function all(): Collection
    {
        return $this->bread->all();
    }

    public function has(string $slug): bool
    {
        return $this->bread->has($slug);
    }

    public function delete(string $slug): bool
    {
        $deleted = $this->bread->delete($slug);

        if ($deleted) {
            BreadRemoved::dispatch($slug);
        }

        return $deleted;
    }

    public function backup(string $slug): ?string
    {
        return $this->bread->backup($slug);
    }

    public function backups(string $slug): Collection
    {
        return $this->bread->backups($slug);
    }

    public function rollback(string $slug, string $backup): bool
    {
        return $this->bread->rollback($slug, $backup);
    }

    public function prune(string $slug, ?int $keep = null): int
    {
        return $this->bread->prune($slug, $keep);
    }

    public function path(): string
    {
        return $this->bread->path();
    }

    public function source(): JsonBreadSource
    {
        return $this->bread;
    }
}
