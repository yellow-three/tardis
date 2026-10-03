<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Models\Permission;

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
        $this->bread->save($bread);

        $slug = $bread instanceof BreadDefinition ? $bread->slug : (string) ($bread['slug'] ?? '');

        $this->provisionPermissions($slug);
    }

    /**
     * Create the browse/read/edit/add/delete abilities for a resource so they
     * show up on the Roles page the moment the BREAD exists (Voyager's
     * generate_permissions). Skipped on an install that has not run the package
     * migrations: saving a definition must not depend on them.
     */
    protected function provisionPermissions(string $slug): void
    {
        if ($slug === '') {
            return;
        }

        try {
            if (Schema::hasTable('tardis_permissions')) {
                Permission::forBread($slug);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not provision BREAD permissions.', ['slug' => $slug, 'error' => $e->getMessage()]);
        }
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
        return $this->bread->delete($slug);
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
