<?php

declare(strict_types=1);

namespace Tardis\Dashboard;

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
        $data = is_file($this->path) ? json_decode((string) file_get_contents($this->path), true) : null;

        return is_array($data) ? array_filter($data, 'is_array') : [];
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    /**
     * @param  array<string, mixed>  $changes  hidden, order, width
     */
    public function change(string $id, array $changes): void
    {
        $layout = $this->all();
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

        $this->write($layout);
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

    /** @param  array<string, mixed>  $layout */
    protected function write(array $layout): void
    {
        $dir = dirname($this->path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmp = tempnam($dir, '.dashboard-');
        file_put_contents($tmp, json_encode($layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
        rename($tmp, $this->path);
    }
}
