<?php

namespace Tardis\Core\Bread;

use Illuminate\Support\Collection;
use Tardis\Core\Bread\Sources\BreadSource;
use Tardis\Core\Bread\Sources\DatabaseBreadSource;
use Tardis\Core\Bread\Sources\JsonBreadSource;

class BreadManager
{
    public function __construct(
        protected JsonBreadSource $json,
        protected DatabaseBreadSource $database,
    ) {}

    public function source(string $slug): BreadSource
    {
        return ($this->json->find($slug) !== null) ? $this->json : $this->database;
    }

    public function find(string $slug): ?array
    {
        return $this->json->find($slug)
            ?? $this->database->find($slug);
    }

    public function all(): Collection
    {
        $fromJson = $this->json->all();
        $fromDb = $this->database->all()
            ->reject(fn ($b, $slug) => $fromJson->has($slug));

        return $fromJson->merge($fromDb);
    }

    public function save(array $bread): void
    {
        $this->json->save($bread);
    }

    public function jsonSource(): JsonBreadSource
    {
        return $this->json;
    }

    public function databaseSource(): DatabaseBreadSource
    {
        return $this->database;
    }
}
