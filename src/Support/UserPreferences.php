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

        $data = $this->read();
        $data[(string) $userId][$key] = $value;

        $this->write($data);
    }

    public function forget(int|string|null $userId, string $key): void
    {
        if ($userId === null) {
            return;
        }

        $data = $this->read();
        unset($data[(string) $userId][$key]);

        if (($data[(string) $userId] ?? []) === []) {
            unset($data[(string) $userId]);
        }

        $this->write($data);
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
     * @param  array<string, array<string, mixed>>  $data
     */
    protected function write(array $data): void
    {
        try {
            $dir = dirname($this->path);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $tmp = tempnam($dir, '.prefs-');
            file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
            rename($tmp, $this->path);
        } catch (\Throwable $e) {
            Log::warning('Could not persist panel preferences.', ['error' => $e->getMessage()]);
        }
    }
}
