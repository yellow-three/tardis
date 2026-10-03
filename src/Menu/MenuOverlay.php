<?php

declare(strict_types=1);

namespace Tardis\Menu;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * What an administrator changed about the sidebar, kept in
 * storage/tardis/menus.json on top of the items code defines: hide, rename,
 * re-section, re-order, and add links of their own. Code-defined items are
 * never edited; deleting the file restores the original menu.
 *
 * File shape:
 *   { "items":  { "<item id>": { "hidden": true, "title": "…", "order": 5, "section": "…" } },
 *     "custom": [ { "id": "custom-ab12", "title": "…", "url": "…", "icon": "…", "section": "…",
 *                   "order": 90, "permission": null, "new_tab": false } ] }
 */
class MenuOverlay
{
    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('tardis/menus.json');
    }

    /** @return array<string, array<string, mixed>> */
    public function items(): array
    {
        return $this->read()['items'];
    }

    /** @return array<int, array<string, mixed>> */
    public function custom(): array
    {
        return $this->read()['custom'];
    }

    public function isEmpty(): bool
    {
        $data = $this->read();

        return $data['items'] === [] && $data['custom'] === [];
    }

    /**
     * Change one code-defined item. Unknown keys are ignored; an empty change
     * removes the entry.
     *
     * @param  array<string, mixed>  $changes  hidden, title, order, section
     */
    public function change(string $id, array $changes): void
    {
        $data = $this->read();
        $entry = $data['items'][$id] ?? [];

        foreach ($changes as $key => $value) {
            match ($key) {
                'hidden' => $value ? $entry['hidden'] = true : $this->forgetKey($entry, 'hidden'),
                'title', 'section' => is_string($value) && trim($value) !== '' ? $entry[$key] = mb_substr(trim($value), 0, 120) : $this->forgetKey($entry, $key),
                'order' => is_numeric($value) ? $entry['order'] = (int) $value : $this->forgetKey($entry, 'order'),
                default => null,
            };
        }

        if ($entry === []) {
            unset($data['items'][$id]);
        } else {
            $data['items'][$id] = $entry;
        }

        $this->write($data);
    }

    /**
     * Add a link. The url must be http(s) or root-relative; it is rendered as an
     * href, so anything else (javascript:, data:) is refused.
     *
     * @param  array<string, mixed>  $link
     */
    public function addCustom(array $link): string
    {
        $title = trim((string) ($link['title'] ?? ''));
        $url = trim((string) ($link['url'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('A custom menu link needs a title.');
        }

        if (preg_match('#^(https?://|/(?!/))#i', $url) !== 1) {
            throw new InvalidArgumentException('A custom menu link needs an http(s) or root-relative url.');
        }

        $data = $this->read();
        $id = 'custom-'.Str::lower(Str::random(6));

        $data['custom'][] = [
            'id' => $id,
            'title' => mb_substr($title, 0, 120),
            'url' => $url,
            'icon' => $this->icon($link['icon'] ?? null),
            'section' => ($section = trim((string) ($link['section'] ?? ''))) !== '' ? mb_substr($section, 0, 120) : null,
            'order' => (int) ($link['order'] ?? 90),
            'permission' => ($permission = trim((string) ($link['permission'] ?? ''))) !== '' ? $permission : null,
            'new_tab' => (bool) ($link['new_tab'] ?? false),
        ];

        $this->write($data);

        return $id;
    }

    public function removeCustom(string $id): void
    {
        $data = $this->read();
        $data['custom'] = array_values(array_filter($data['custom'], fn (array $link) => ($link['id'] ?? null) !== $id));

        $this->write($data);
    }

    /** Restore the menu code defines. */
    public function reset(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /** A heroicon name (letters, digits, dashes); anything else falls back to a link icon. */
    protected function icon(mixed $icon): string
    {
        $icon = trim((string) $icon);

        if ($icon === '' || preg_match('/^[a-z0-9-]+$/i', $icon) !== 1) {
            return 'heroicon-o-link';
        }

        return str_starts_with($icon, 'heroicon-') ? $icon : 'heroicon-o-'.$icon;
    }

    protected function forgetKey(array &$entry, string $key): void
    {
        unset($entry[$key]);
    }

    /** @return array{items: array<string, array<string, mixed>>, custom: array<int, array<string, mixed>>} */
    protected function read(): array
    {
        $data = is_file($this->path) ? json_decode((string) file_get_contents($this->path), true) : null;
        $data = is_array($data) ? $data : [];

        return [
            'items' => is_array($data['items'] ?? null) ? $data['items'] : [],
            'custom' => is_array($data['custom'] ?? null) ? array_values($data['custom']) : [],
        ];
    }

    /** @param  array{items: array<string, mixed>, custom: array<int, mixed>}  $data */
    protected function write(array $data): void
    {
        $dir = dirname($this->path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmp = tempnam($dir, '.menus-');
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        rename($tmp, $this->path);
    }
}
