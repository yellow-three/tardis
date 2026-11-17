<?php

declare(strict_types=1);

namespace Tardis\Support;

use Illuminate\Support\Facades\Log;

/**
 * Per-user panel preferences (language, theme) kept in
 * storage/tardis/preferences.json, so they follow the user across devices
 * without adding a column to the host's users table.
 *
 * Writes are atomic (temp file + rename); a missing or corrupt file means "no
 * preferences". The file holds small values per user id and is meant for the
 * admin audience, not for every visitor of a site.
 */
class UserPreferences
{
    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('tardis/preferences.json');
    }

    public function get(int|string|null $userId, string $key, mixed $default = null): mixed
    {
        if ($userId === null) {
            return $default;
        }

        return $this->read()[(string) $userId][$key] ?? $default;
    }

    public function set(int|string|null $userId, string $key, mixed $value): void
    {
        if ($userId === null) {
            return;
        }

        $this->write(function (array $data) use ($userId, $key, $value) {
            $data[(string) $userId][$key] = $value;

            return $data;
        });
    }

    public function forget(int|string|null $userId, string $key): void
    {
        if ($userId === null) {
            return;
        }

        $this->write(function (array $data) use ($userId, $key) {
            unset($data[(string) $userId][$key]);

            if (($data[(string) $userId] ?? []) === []) {
                unset($data[(string) $userId]);
            }

            return $data;
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function read(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Apply a change to the stored preferences under the file lock.
     *
     * @param  callable(array<string, array<string, mixed>>): array<string, array<string, mixed>>  $change
     */
    protected function write(callable $change): void
    {
        try {
            JsonFile::update($this->path, $change);
        } catch (\Throwable $e) {
            Log::warning('Could not persist panel preferences.', ['error' => $e->getMessage()]);
        }
    }
}
