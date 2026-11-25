<?php

declare(strict_types=1);

namespace Tardis\State;

/**
 * Where the panel keeps its runtime state: settings, themes, the menu
 * overlay, site menus, the dashboard layout, plugin switches, preferences
 * and BREAD definitions (tardis.state.driver).
 *
 * Every entry is addressed by the path it has under the file driver
 * (storage/tardis/... or tardis.bread.path), so the stores do not change
 * with the driver. The database driver keeps the same entries as rows keyed
 * by that path relative to storage/tardis, which is the same on every server.
 */
interface StateStore
{
    public function get(string $path): ?string;

    /** Write the whole entry atomically. */
    public function put(string $path, string $contents): void;

    public function exists(string $path): bool;

    public function delete(string $path): bool;

    /**
     * The file names directly inside a directory, sorted.
     *
     * @return array<int, string>
     */
    public function files(string $directory): array;

    /**
     * Changes whenever the entry changes, so a long-running worker can tell
     * that another process wrote it. "missing" when there is none.
     */
    public function signature(string $path): string;

    /**
     * Decode a JSON entry; a missing or corrupt entry is an empty array.
     *
     * @return array<mixed>
     */
    public function read(string $path): array;

    /**
     * Read-modify-write a JSON entry under a lock, so two writers both land.
     *
     * @param  callable(array<mixed>): array<mixed>  $change
     * @return array<mixed> the data as written
     */
    public function update(string $path, callable $change, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): array;

    /**
     * Every entry, keyed by its path relative to storage/tardis (used to copy
     * state from one driver to the other).
     *
     * @return array<string, string>
     */
    public function dump(): array;
}
