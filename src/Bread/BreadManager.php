<?php

namespace Tardis\Bread;

use Illuminate\Support\Collection;
use Tardis\Bread\Sources\ConfigBreadSource;

class BreadManager
{
    public function __construct(
        protected ConfigBreadSource $config,
    ) {}

    /**
     * @param  array<string, mixed>|BreadDefinition  $bread
     */
    public function save(array|BreadDefinition $bread): void
    {
        $this->config->save($bread);
    }

    public function find(string $slug): ?BreadDefinition
    {
        return $this->config->find($slug);
    }

    public function all(): Collection
    {
        return $this->config->all();
    }
}
