<?php

declare(strict_types=1);

namespace Tardis\Bread\Sources;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\FieldType;

/**
 * Reads BREAD definitions from PHP config files, one file per slug.
 *
 * Each file must `return` an array shaped like BreadDefinition::fromArray()
 * expects. The slug key is optional inside the file — it falls back to the
 * file name when missing.
 */
class ConfigBreadSource implements BreadSource
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
     * Write a BREAD definition as a PHP config file.
     *
     * The file is written atomically (temp file + rename) so a crash in the
     * middle of a save never leaves a half-written config behind.
     */
    public function save(array|BreadDefinition $bread): void
    {
        $data = $bread instanceof BreadDefinition ? $bread->toArray() : $bread;

        $slug = $data['slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            throw new \InvalidArgumentException('BREAD config requires a slug.');
        }

        $this->validateFieldTypes($data['fields'] ?? []);

        $content = $this->renderConfig($data);

        File::ensureDirectoryExists($this->path);

        $target = $this->fileFor($slug);

        // Write inside the package directory so tempnam + rename stay on the
        // same filesystem and the rename is atomic.
        $tmp = tempnam($this->path, '.bread-');

        if ($tmp === false) {
            throw new \RuntimeException(
                sprintf('Could not create a temporary file in [%s].', $this->path)
            );
        }

        File::put($tmp, $content);

        if (! @rename($tmp, $target)) {
            @unlink($tmp);

            throw new \RuntimeException(
                sprintf('Could not write BREAD config file [%s].', $target)
            );
        }
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
                FieldType::fromValue($field['type']);
            }
        }
    }

    protected function fileFor(string $slug): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$slug.'.php';
    }

    /**
     * Render a BREAD definition array as a human-readable PHP config file.
     */
    protected function renderConfig(array $data): string
    {
        $header = "<?php\n\n/*\n"
            ."|--------------------------------------------------------------------------\n"
            ."| BREAD definition: {$data['slug']}\n"
            ."|--------------------------------------------------------------------------\n"
            ."| Managed through the Tardis admin BREAD builder. The array shape is\n"
            ."| compatible with BreadDefinition::fromArray().\n"
            ."*/\n\n";

        return $header.'return '.$this->exportArray($data).";\n";
    }

    /**
     * Recursively export an array as PHP source with readable indentation.
     */
    protected function exportArray(array $array, int $depth = 0): string
    {
        if ($array === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth + 1);
        $lines = [];

        foreach ($array as $key => $value) {
            $key = is_int($key) ? $key : var_export($key, true);
            $value = is_array($value) ? $this->exportArray($value, $depth + 1) : var_export($value, true);

            $lines[] = $indent.$key.' => '.$value.',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth).']';
    }
}
