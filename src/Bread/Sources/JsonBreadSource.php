<?php

namespace Tardis\Bread\Sources;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class JsonBreadSource implements BreadSource
{
    public function __construct(
        protected string $path,
    ) {}

    public function find(string $slug): ?array
    {
        $file = $this->path.'/'.$slug.'.json';

        if (! File::exists($file)) {
            return null;
        }

        return array_merge(
            json_decode(File::get($file), true),
            ['source' => 'json'],
        );
    }

    public function all(): Collection
    {
        if (! File::isDirectory($this->path)) {
            return collect();
        }

        return collect(File::files($this->path))
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->mapWithKeys(function ($file) {
                $slug = $file->getBasename('.json');
                $data = json_decode(File::get($file->getRealPath()), true);

                return [$slug => array_merge($data, ['source' => 'json'])];
            });
    }

    public function save(array $bread): void
    {
        $slug = $bread['slug'];
        $file = $this->path.'/'.$slug.'.json';

        if (File::exists($file)) {
            File::copy($file, $this->path.'/'.$slug.'.bak.'.now()->timestamp.'.json');
        }

        File::ensureDirectoryExists($this->path);
        File::put($file, json_encode($bread, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
