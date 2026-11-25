<?php

declare(strict_types=1);

namespace Tardis\State;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the state entries as rows of tardis_state, so every server of a
 * multi-server or ephemeral deploy sees the same settings, menus, plugin
 * switches and BREAD definitions.
 *
 * The whole table is read once per request (it holds a few small documents)
 * and served from memory after that; the instance is scoped, so a queue job
 * or an Octane request starts with a fresh read. Writes lock the row.
 */
class DatabaseStateStore implements StateStore
{
    public const TABLE = 'tardis_state';

    /** @var array<string, array{0: string, 1: int}>|null key => [value, version] */
    protected ?array $rows = null;

    public function __construct(protected ?string $connection = null) {}

    public function get(string $path): ?string
    {
        return $this->rows()[StatePaths::key($path)][0] ?? null;
    }

    public function put(string $path, string $contents): void
    {
        $key = StatePaths::key($path);

        $this->db()->transaction(function () use ($key, $contents) {
            $this->writeRow($key, $contents, $this->lockRow($key));
        });
    }

    public function exists(string $path): bool
    {
        return isset($this->rows()[StatePaths::key($path)]);
    }

    public function delete(string $path): bool
    {
        $key = StatePaths::key($path);
        $deleted = $this->db()->table(self::TABLE)->where('key', $key)->delete() > 0;

        if ($this->rows !== null) {
            unset($this->rows[$key]);
        }

        return $deleted;
    }

    public function files(string $directory): array
    {
        $prefix = rtrim(StatePaths::key(rtrim($directory, '/\\').'/x'), 'x');
        $names = [];

        foreach (array_keys($this->rows()) as $key) {
            if (str_starts_with($key, $prefix) && ! str_contains(substr($key, strlen($prefix)), '/')) {
                $names[] = substr($key, strlen($prefix));
            }
        }

        sort($names);

        return $names;
    }

    public function signature(string $path): string
    {
        $row = $this->rows()[StatePaths::key($path)] ?? null;

        return $row === null ? 'missing' : 'v'.$row[1];
    }

    public function read(string $path): array
    {
        $data = json_decode((string) $this->get($path), true);

        return is_array($data) ? $data : [];
    }

    public function update(string $path, callable $change, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): array
    {
        $key = StatePaths::key($path);

        return $this->db()->transaction(function () use ($key, $change, $flags) {
            $current = $this->lockRow($key);
            $data = json_decode((string) ($current[0] ?? ''), true);
            $data = $change(is_array($data) ? $data : []);

            $this->writeRow($key, json_encode($data, $flags)."\n", $current);

            return $data;
        });
    }

    public function dump(): array
    {
        $entries = array_map(fn (array $row) => $row[0], $this->rows());
        ksort($entries);

        return $entries;
    }

    /** Forget what was read, so the next call reads the table again. */
    public function refresh(): void
    {
        $this->rows = null;
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    protected function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        try {
            $this->rows = $this->db()->table(self::TABLE)->get(['key', 'value', 'version'])
                ->mapWithKeys(fn ($row) => [(string) $row->key => [(string) $row->value, (int) $row->version]])
                ->all();
        } catch (QueryException) {
            // Before `php artisan migrate` there is no table: no state yet.
            // Not remembered, so the table is used once it exists.
            return [];
        }

        return $this->rows;
    }

    /**
     * Lock the row for this transaction and return its current value.
     *
     * @return array{0: string, 1: int}|null
     */
    protected function lockRow(string $key): ?array
    {
        $row = $this->db()->table(self::TABLE)->where('key', $key)->lockForUpdate()->first(['value', 'version']);

        return $row === null ? null : [(string) $row->value, (int) $row->version];
    }

    /**
     * @param  array{0: string, 1: int}|null  $current
     */
    protected function writeRow(string $key, string $contents, ?array $current): void
    {
        $version = ($current[1] ?? 0) + 1;
        $now = now();

        if ($current === null) {
            $this->db()->table(self::TABLE)->insert(['key' => $key, 'value' => $contents, 'version' => $version, 'created_at' => $now, 'updated_at' => $now]);
        } else {
            $this->db()->table(self::TABLE)->where('key', $key)->update(['value' => $contents, 'version' => $version, 'updated_at' => $now]);
        }

        if ($this->rows !== null) {
            $this->rows[$key] = [$contents, $version];
        }
    }

    protected function db(): ConnectionInterface
    {
        return DB::connection($this->connection);
    }
}
