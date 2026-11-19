<?php

declare(strict_types=1);

namespace Tardis\Dashboard;

use Tardis\Support\JsonFile;

/**
 * How the administrator arranged the dashboard, kept in
 * storage/tardis/dashboard.json on top of the widgets code provides: hide,
 * re-order and re-size. Deleting the file restores the default layout.
 *
 * File shape: { "<widget id>": { "hidden": true, "order": 20, "width": 6 } }
 */
class DashboardLayout
{
    /** Widths a widget may take, out of a 12-column grid. */
    public const WIDTHS = [3, 4, 6, 8, 12];

    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('tardis/dashboard.json');
    }

    /** @return array<string, array{hidden?: bool, order?: int, width?: int}> */
    public function all(): array
    {
        return array_filter(JsonFile::read($this->path), 'is_array');
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    /**
     * Changed under the file's lock, from what is on disk now, so a change
     * made in another request at the same moment is kept.
     *
     * @param  array<string, mixed>  $changes  hidden, order, width
     */
    public function change(string $id, array $changes): void
    {
        JsonFile::update($this->path, function (array $layout) use ($id, $changes) {
            $layout = array_filter($layout, 'is_array');
            $entry = $layout[$id] ?? [];

            foreach ($changes as $key => $value) {
                if ($key === 'hidden') {
                    $value ? $entry['hidden'] = true : $this->forget($entry, 'hidden');
                } elseif ($key === 'order' && is_numeric($value)) {
                    $entry['order'] = (int) $value;
                } elseif ($key === 'width' && in_array((int) $value, self::WIDTHS, true)) {
                    $entry['width'] = (int) $value;
                }
            }

            if ($entry === []) {
                unset($layout[$id]);
            } else {
                $layout[$id] = $entry;
            }

            return $layout;
        });
    }

    public function reset(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    protected function forget(array &$entry, string $key): void
    {
        unset($entry[$key]);
    }
}
