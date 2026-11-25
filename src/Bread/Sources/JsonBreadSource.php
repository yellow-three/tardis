<?php

declare(strict_types=1);

namespace Tardis\Bread\Sources;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\ReservedSlugs;
use Tardis\Formfields\FormfieldManager;
use Tardis\State\UsesStateStore;

/**
 * Stores BREAD definitions as JSON, one entry per slug: files under
 * storage/tardis/bread by default, or rows of tardis_state with the
 * database state driver (tardis.state.driver).
 *
 * Unlike the legacy PHP config source, definitions are runtime data: the
 * admin BREAD builder edits them directly. A timestamped backup is
 * snapshotted before every destructive change so a definition can always
 * be rolled back to a previous version.
 */
class JsonBreadSource implements BreadSource
{
    use UsesStateStore;

    /**
     * Parsed definitions of this instance, keyed by file. The source is bound
     * per request (scoped), so this lives for one request and does not grow
     * in a long-running worker.
     *
     * @var array<string, array{0: string, 1: BreadDefinition}> file => [content hash, definition]
     */
    private array $parsed = [];

    public function __construct(
        protected ?string $path = null,
    ) {
        // An explicit directory is read as files; the default follows the driver.
        $this->ownFile = $path !== null;
    }

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

        $raw = $this->state()->get($file);

        if ($raw === null) {
            return null;
        }

        $hash = hash('xxh128', $raw);

        // Routes, the menu, search and every authorisation check read the
        // definitions on each request; decoding and validating them again is
        // the expensive part, so a file whose content has not changed is
        // served from memory. Keyed by content, so an edit (from another
        // process too) is picked up at once.
        if (($this->parsed[$file][0] ?? null) === $hash) {
            return clone $this->parsed[$file][1];
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw new \UnexpectedValueException(
                sprintf('BREAD file [%s] does not contain valid JSON.', $file)
            );
        }

        $data['slug'] = $data['slug'] ?? $slug;

        $this->validateFieldTypes($data['fields'] ?? []);

        $definition = BreadDefinition::fromArray($data);
        $this->parsed[$file] = [$hash, $definition];

        return clone $definition;
    }

    public function all(): Collection
    {
        return collect($this->state()->files($this->path()))
            ->filter(fn (string $name) => str_ends_with($name, '.json'))
            ->filter(fn (string $name) => ! str_contains($name, '.backup.'))
            ->mapWithKeys(function (string $name) {
                $slug = substr($name, 0, -5);

                try {
                    return [$slug => $this->find($slug)];
                } catch (\Throwable $e) {
                    Log::warning('Skipping invalid BREAD JSON file.', [
                        'file' => $name,
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

        return $this->state()->exists($this->fileFor($slug));
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

        if (ReservedSlugs::has($slug)) {
            throw new \InvalidArgumentException(
                sprintf('BREAD slug [%s] is reserved for a built-in admin screen.', $slug)
            );
        }

        $this->validateFieldTypes($data['fields'] ?? []);

        // A class that does not exist (yet) is allowed: the pages answer 404
        // until it does. One that exists must be an Eloquent model.
        $model = $data['model'] ?? '';

        if (is_string($model) && $model !== '' && class_exists($model) && ! BreadDefinition::isModelClass($model)) {
            throw new \InvalidArgumentException(sprintf('BREAD model [%s] is not an Eloquent model.', $model));
        }

        $this->backup($slug);

        $content = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($content === false) {
            throw new \RuntimeException(
                sprintf('Could not encode BREAD definition [%s] as JSON.', $slug)
            );
        }

        // Atomic in both drivers: temp file + rename, or one row update.
        $this->state()->put($this->fileFor($slug), $content."\n");

        $this->prune($slug, config('tardis.bread.backup_keep') ?: 10);
    }

    /**
     * Delete a definition, snapshotting the removed version first.
     */
    public function delete(string $slug): bool
    {
        $file = $this->fileFor($slug);

        if (! $this->state()->exists($file)) {
            return false;
        }

        $this->backup($slug);

        return $this->state()->delete($file);
    }

    /**
     * Snapshot the current version of a definition.
     *
     * @return string|null the backup file name, or null when there was no live file
     */
    public function backup(string $slug): ?string
    {
        $live = $this->state()->get($this->fileFor($slug));

        if ($live === null) {
            return null;
        }

        $name = sprintf(
            '%s.backup.%s.json',
            $slug,
            now()->format('Y-m-d@H-i-s.u')
        );

        try {
            $this->state()->put(rtrim($this->path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name, $live);
        } catch (\RuntimeException) {
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
        $prefix = $slug.'.backup.';

        return collect($this->state()->files($this->path()))
            ->filter(fn (string $name) => str_ends_with($name, '.json'))
            ->filter(fn (string $name) => str_starts_with($name, $prefix))
            ->map(fn (string $name) => [
                'slug' => $slug,
                'name' => $name,
                'date' => substr($name, strlen($prefix), -5), // strip ".json"
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

        $content = $this->state()->get(rtrim($this->path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$backup);

        if ($content === null) {
            return false;
        }

        $this->backup($slug);

        try {
            $this->state()->put($this->fileFor($slug), $content);
        } catch (\RuntimeException) {
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

            if ($this->state()->delete($file)) {
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
                app(FormfieldManager::class)->assertRegistered($field['type']);
            }
        }
    }
}
