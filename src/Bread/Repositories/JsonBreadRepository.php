<?php

namespace Tardis\Bread\Repositories;

use Illuminate\Support\Facades\File;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\Contracts\BreadRepositoryInterface;

class JsonBreadRepository implements BreadRepositoryInterface
{
    protected string $path;

    public function __construct()
    {
        $this->path = storage_path('tardis/breads');

        if (! is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function find(string $slug): ?BreadDefinition
    {
        $file = $this->path.'/'.$slug.'.json';

        if (! File::exists($file)) {
            return null;
        }

        $data = json_decode(File::get($file), true);

        return BreadDefinition::fromArray($data);
    }

    public function all(): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $files = File::files($this->path);

        $breads = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'json') {
                $slug = $file->getBasename('.json');
                $bread = $this->find($slug);
                if ($bread) {
                    $breads[$slug] = $bread;
                }
            }
        }

        return $breads;
    }

    public function save(BreadDefinition $bread): void
    {
        $file = $this->path.'/'.$bread->slug.'.json';
        $data = json_encode($bread->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        File::put($file, $data);
    }

    public function delete(string $slug): void
    {
        $file = $this->path.'/'.$slug.'.json';

        if (File::exists($file)) {
            File::delete($file);
        }
    }

    public function exists(string $slug): bool
    {
        return File::exists($this->path.'/'.$slug.'.json');
    }
}
