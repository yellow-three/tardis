<?php

declare(strict_types=1);

namespace Tardis\Bread\Sources;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\FieldType;

/**
 * Stores BREAD definitions as JSON files, one file per slug, under
 * storage/tardis/bread by default.
 *
 * Unlike the legacy PHP config source, definitions are runtime data: the
 * admin BREAD builder edits them directly. A timestamped backup is
 * snapshotted before every destructive change so a definition can always
 * be rolled back to a previous version.
 */
class JsonBreadSource implements BreadSource
{
    public function __construct(
        protected ?string $path = null,
    ) {}

    public function path(): string
    {
        $this->path ??= (string) (config('tardis.bread.path') ?: storage_path('tardis/bread'));

        return $this->path;
    }

    public function find(string $slug): ?BreadDefinition
    {
        if (! static::isValidSlug($slug)) {
            return null;
        }

        $file = $this->fileFor($slug);

        if (! File::exists($file)) {
            return null;
        }

        $data = json_decode((string) File::get($file), true);

        if (! is_array($data)) {
            throw new \UnexpectedValueException(
                sprintf('BREAD file [%s] does not contain valid JSON.', $file)
            );
        }

        $data['slug'] = $data['slug'] ?? $slug;

        $this->validateFieldTypes($data['fields'] ?? []);

        return BreadDefinition::fromArray($data);
    }

    public function all(): Collection
    {
        if (! File::isDirectory($this->path())) {
            return collect();
        }

        return collect(File::files($this->path()))
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->filter(fn ($file) => ! str_contains($file->getFilename(), '.backup.'))
            ->mapWithKeys(function ($file) {
                $slug = $file->getFilenameWithoutExtension();

                try {
                    return [$slug => $this->find($slug)];
                } catch (\Throwable $e) {
                    Log::warning('Skipping invalid BREAD JSON file.', [
                        'file' => $file->getPathname(),
                        'error' => $e->getMessage(),
                    ]);

                    return [];
                }
            })
            ->filter()
            ->sortKeys();
    }

    public function has(string $slug): bool
    {
        if (! static::isValidSlug($slug)) {
            return false;
        }

        return File::exists($this->fileFor($slug));
    }

    /**
     * Write a BREAD definition as a JSON file.
     *
     * The previous version is snapshotted first so the write is undoable,
     * then the new definition is validated and written atomically (temp
     * file + rename). Backups are pruned to the configured retention.
     *
     * @param  array<string, mixed>|BreadDefinition  $bread
     */
    public function save(array|BreadDefinition $bread): void
    {
        $data = $bread instanceof BreadDefinition ? $bread->toArray() : $bread;

        $slug = $data['slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            throw new \InvalidArgumentException('BREAD definition requires a slug.');
        }

        $this->assertValidSlug($slug);

        $this->backup($slug);

        $this->validateFieldTypes($data['fields'] ?? []);

        $content = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($content === false) {
            throw new \RuntimeException(
                sprintf('Could not encode BREAD definition [%s] as JSON.', $slug)
            );
        }

        File::ensureDirectoryExists($this->path());

        $target = $this->fileFor($slug);

        // Write inside the bread directory so tempnam + rename stay on the
        // same filesystem and the rename is atomic.
        $tmp = tempnam($this->path(), '.bread-');

        if ($tmp === false) {
            throw new \RuntimeException(
                sprintf('Could not create a temporary file in [%s].', $this->path())
            );
        }

        File::put($tmp, $content."\n");

        if (! @rename($tmp, $target)) {
            @unlink($tmp);

            throw new \RuntimeException(
                sprintf('Could not write BREAD JSON file [%s].', $target)
            );
        }

        $this->prune($slug, config('tardis.bread.backup_keep') ?: 10);
    }

    /**
     * Delete a definition, snapshotting the removed version first.
     */
    public function delete(string $slug): bool
    {
        $file = $this->fileFor($slug);

        if (! File::exists($file)) {
            return false;
        }

        $this->backup($slug);

        return File::delete($file);
    }

    /**
     * Snapshot the current version of a definition.
     *
     * @return string|null the backup file name, or null when there was no live file
     */
    public function backup(string $slug): ?string
    {
        $live = $this->fileFor($slug);

        if (! File::exists($live)) {
            return null;
        }

        $name = sprintf(
            '%s.backup.%s.json',
            $slug,
            now()->format('Y-m-d@H-i-s.u')
        );

        File::ensureDirectoryExists($this->path());
        $dest = rtrim($this->path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;

        if (! @copy($live, $dest)) {
            return null;
        }

        return $name;
    }

    /**
     * List the backups of a definition, newest first.
     *
     * @return Collection<int, array{slug: string, name: string, date: string}>
     */
    public function backups(string $slug): Collection
    {
        if (! File::isDirectory($this->path())) {
            return collect();
        }

        $prefix = $slug.'.backup.';

        return collect(File::files($this->path()))
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->filter(fn ($file) => str_starts_with($file->getFilename(), $prefix))
            ->map(fn ($file) => [
                'slug' => $slug,
                'name' => $file->getFilename(),
                'date' => substr($file->getFilename(), strlen($prefix), -5), // strip ".json"
            ])
            ->sortByDesc('date')
            ->values();
    }

    /**
     * Restore a definition from a named backup.
     *
     * The current state is snapshotted first so the rollback itself is
     * undoable. The backup is copied onto the live file atomically.
     *
     * @throws \InvalidArgumentException when the backup name is not a valid backup of the slug
     */
    public function rollback(string $slug, string $backup): bool
    {
        $this->assertValidSlug($slug);

        if (! $this->backupNameIsValid($slug, $backup)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid backup name [%s] for slug [%s].', $backup, $slug)
            );
        }

        $backupFile = rtrim($this->path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$backup;

        if (! File::exists($backupFile)) {
            return false;
        }

        $this->backup($slug);

        File::ensureDirectoryExists($this->path());

        $target = $this->fileFor($slug);

        $tmp = tempnam($this->path(), '.bread-');

        if ($tmp === false || ! @copy($backupFile, $tmp)) {
            @unlink((string) $tmp);

            return false;
        }

        if (! @rename($tmp, $target)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    /**
     * Remove the oldest backups of a definition, keeping the $keep newest.
     */
    public function prune(string $slug, ?int $keep = null): int
    {
        $keep ??= (int) (config('tardis.bread.backup_keep') ?: 10);

        $backups = $this->backups($slug);

        if ($backups->count() <= $keep) {
            return 0;
        }

        $removed = 0;

        $backups->slice($keep)->each(function (array $backup) use (&$removed): void {
            $file = rtrim($this->path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$backup['name'];

            if (File::delete($file)) {
                $removed++;
            }
        });

        return $removed;
    }

    /**
     * A slug becomes part of a file name, so anything that could address a path
     * (separators, "..", a leading dot) is refused instead of being sanitised.
     */
    public static function isValidSlug(string $slug): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $slug) === 1;
    }

    protected function assertValidSlug(string $slug): void
    {
        if (! static::isValidSlug($slug)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid BREAD slug [%s]: use letters, digits, "-" and "_" only.', $slug)
            );
        }
    }

    protected function fileFor(string $slug): string
    {
        $this->assertValidSlug($slug);

        return rtrim($this->path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$slug.'.json';
    }

    protected function backupNameIsValid(string $slug, string $name): bool
    {
        $pattern = '/^'.preg_quote($slug, '/')
            .'\.backup\.[0-9]{4}-[0-9]{2}-[0-9]{2}@[0-9]{2}-[0-9]{2}-[0-9]{2}\.[0-9]{6}\.json$/';

        return preg_match($pattern, $name) === 1;
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
}
