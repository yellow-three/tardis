<?php

declare(strict_types=1);

namespace Tardis\Bread\Legacy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Tardis\Bread\BreadDefinition;
use Tardis\Manager\FormfieldManager;

/**
 * Read-only reader for the legacy config/bread/{slug}.php definitions.
 *
 * BREAD definitions live in JSON (JsonBreadSource) since 2.0; this class only
 * exists so `tardis:bread:migrate` can import what older installs still keep in
 * config/bread. It never writes. Each file must `return` an array shaped like
 * BreadDefinition::fromArray() expects; the slug falls back to the file name.
 */
class LegacyConfigReader
{
    public function __construct(
        protected string $path,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function find(string $slug): ?BreadDefinition
    {
        $file = $this->fileFor($slug);

        if (! File::exists($file)) {
            return null;
        }

        $data = require $file;

        if (! is_array($data)) {
            throw new \UnexpectedValueException(
                sprintf('BREAD config file [%s] must return an array.', $file)
            );
        }

        $data['slug'] = $data['slug'] ?? $slug;

        $this->validateFieldTypes($data['fields'] ?? []);

        return BreadDefinition::fromArray($data);
    }

    public function all(): Collection
    {
        if (! File::isDirectory($this->path)) {
            return collect();
        }

        return collect(File::files($this->path))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->mapWithKeys(fn ($file) => [
                $file->getFilenameWithoutExtension() => $this->find($file->getFilenameWithoutExtension()),
            ])
            ->filter()
            ->sortKeys();
    }

    /**
     * Validate that every declared field type has a registered renderer.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateFieldTypes(array $fields): void
    {
        foreach ($fields as $field) {
            if (isset($field['type']) && is_string($field['type'])) {
                app(FormfieldManager::class)->assertRegistered($field['type']);
            }
        }
    }

    protected function fileFor(string $slug): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$slug.'.php';
    }
}
