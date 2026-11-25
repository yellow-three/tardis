<?php

declare(strict_types=1);

namespace Tardis\Menu;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Tardis\State\UsesStateStore;
use Tardis\Support\JsonFile;
use Tardis\Support\SafeUrl;

/**
 * Named menus for the host's own site (header, footer, ...), edited in the
 * panel and read with menu('main') or <x-tardis::site-menu name="main" />.
 *
 * The panel's own sidebar is not one of these: it is built in code and
 * adjusted through MenuOverlay. Menus are stored in
 * storage/tardis/site-menus.json as trees of items:
 *   {"menus": {"main": [{"id": "...", "title": "Blog", "url": "/blog", "new_tab": false, "children": [...]}]}}
 *
 * Every change rewrites the file under a lock (JsonFile), so two editors
 * saving at once both land.
 */
final class SiteMenuStore
{
    use UsesStateStore;

    /** How deep items may nest (top level is 1). */
    public const MAX_DEPTH = 3;

    public function __construct(private ?string $path = null)
    {
        $this->ownFile = $path !== null;
    }

    public function path(): string
    {
        return $this->path ??= storage_path('tardis/site-menus.json');
    }

    /** @return list<string> */
    public function names(): array
    {
        $names = array_keys($this->menus());
        sort($names);

        return $names;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->menus());
    }

    /**
     * @return list<array{id: string, title: string, url: string, new_tab: bool, children: list<array<string, mixed>>}>
     */
    public function items(string $name): array
    {
        return $this->menus()[$name] ?? [];
    }

    public static function isValidName(string $name): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $name) === 1;
    }

    public function create(string $name): void
    {
        if (! self::isValidName($name)) {
            throw new InvalidArgumentException("Invalid menu name [{$name}]: use lowercase letters, digits, - and _.");
        }

        $this->change(function (array $menus) use ($name) {
            $menus[$name] ??= [];

            return $menus;
        });
    }

    public function delete(string $name): void
    {
        $this->change(function (array $menus) use ($name) {
            unset($menus[$name]);

            return $menus;
        });
    }

    /**
     * @param  array{title?: mixed, url?: mixed, new_tab?: mixed}  $item
     * @return string the new item's id
     */
    public function add(string $name, array $item, ?string $parentId = null): string
    {
        $clean = self::clean($item) + ['id' => Str::lower(Str::random(8)), 'children' => []];

        $this->changeMenu($name, function (array $items) use ($clean, $parentId) {
            if ($parentId === null) {
                return [...$items, $clean];
            }

            $depth = self::depthOf($items, $parentId);

            if ($depth === null || $depth >= self::MAX_DEPTH) {
                throw new InvalidArgumentException('The parent item does not exist or is nested too deep.');
            }

            return self::mapItem($items, $parentId, fn (array $parent) => [...$parent, 'children' => [...$parent['children'], $clean]]);
        });

        return $clean['id'];
    }

    /**
     * @param  array{title?: mixed, url?: mixed, new_tab?: mixed}  $changes
     */
    public function update(string $name, string $id, array $changes): void
    {
        $this->changeMenu($name, fn (array $items) => self::mapItem(
            $items,
            $id,
            fn (array $item) => [...$item, ...self::clean($changes + $item)],
        ));
    }

    public function remove(string $name, string $id): void
    {
        $this->changeMenu($name, fn (array $items) => self::without($items, $id));
    }

    /** Swap an item with its previous (-1) or next (1) sibling. */
    public function move(string $name, string $id, int $direction): void
    {
        $this->changeMenu($name, fn (array $items) => self::mapSiblings($items, $id, function (array $siblings, int $index) use ($direction) {
            $target = $index + ($direction < 0 ? -1 : 1);

            if ($target < 0 || $target >= count($siblings)) {
                return $siblings;
            }

            [$siblings[$index], $siblings[$target]] = [$siblings[$target], $siblings[$index]];

            return $siblings;
        }));
    }

    /** Make an item the last child of its previous sibling. */
    public function indent(string $name, string $id): void
    {
        $this->changeMenu($name, function (array $items) use ($id) {
            $depth = self::depthOf($items, $id) ?? self::MAX_DEPTH;

            if ($depth + self::height(self::find($items, $id) ?? []) - 1 >= self::MAX_DEPTH) {
                return $items;
            }

            return self::mapSiblings($items, $id, function (array $siblings, int $index) {
                if ($index === 0) {
                    return $siblings;
                }

                $item = $siblings[$index];
                $previous = $siblings[$index - 1];
                $previous['children'] = [...$previous['children'], $item];
                $siblings[$index - 1] = $previous;
                array_splice($siblings, $index, 1);

                return $siblings;
            });
        });
    }

    /** Move an item out of its parent, to just after it. */
    public function outdent(string $name, string $id): void
    {
        $this->changeMenu($name, function (array $items) use ($id) {
            $parentId = self::parentOf($items, $id);

            if ($parentId === null) {
                return $items;
            }

            $item = self::find($items, $id);
            $items = self::without($items, $id);

            return self::mapSiblings($items, $parentId, function (array $siblings, int $index) use ($item) {
                array_splice($siblings, $index + 1, 0, [$item]);

                return $siblings;
            });
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, url: string, new_tab: bool}
     */
    private static function clean(array $item): array
    {
        $title = mb_substr(trim((string) ($item['title'] ?? '')), 0, 120);
        $rawUrl = (string) ($item['url'] ?? '');
        $url = trim($rawUrl);

        if ($title === '') {
            throw new InvalidArgumentException('A menu item needs a title.');
        }

        // Written into an href on the host's pages: only http(s), a
        // root-relative path or a fragment; check before trimming so padded
        // javascript: links cannot slip through.
        if (! SafeUrl::isAllowed($rawUrl, allowFragment: true)) {
            throw new InvalidArgumentException('A menu item needs an http(s), root-relative or #fragment url.');
        }

        return ['title' => $title, 'url' => mb_substr($url, 0, 2048), 'new_tab' => (bool) ($item['new_tab'] ?? false)];
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function menus(): array
    {
        $menus = $this->state()->read($this->path())['menus'] ?? [];

        return is_array($menus) ? array_filter($menus, 'is_array') : [];
    }

    private function change(callable $change): void
    {
        $this->state()->update($this->path(), function (array $data) use ($change) {
            $menus = is_array($data['menus'] ?? null) ? $data['menus'] : [];

            return ['menus' => $change($menus)];
        });
    }

    private function changeMenu(string $name, callable $change): void
    {
        $this->change(function (array $menus) use ($name, $change) {
            if (! array_key_exists($name, $menus)) {
                throw new InvalidArgumentException("Unknown menu [{$name}].");
            }

            $menus[$name] = array_values($change($menus[$name]));

            return $menus;
        });
    }

    // Tree helpers: each returns a new tree, the input is never changed.

    /** @param list<array<string, mixed>> $items */
    private static function mapItem(array $items, string $id, callable $change): array
    {
        return array_map(function (array $item) use ($id, $change) {
            if (($item['id'] ?? null) === $id) {
                return $change($item);
            }

            return [...$item, 'children' => self::mapItem($item['children'] ?? [], $id, $change)];
        }, $items);
    }

    /** Apply $change(siblings, index) to the list that holds $id. */
    private static function mapSiblings(array $items, string $id, callable $change): array
    {
        foreach ($items as $index => $item) {
            if (($item['id'] ?? null) === $id) {
                return array_values($change($items, $index));
            }
        }

        return array_map(fn (array $item) => [...$item, 'children' => self::mapSiblings($item['children'] ?? [], $id, $change)], $items);
    }

    private static function without(array $items, string $id): array
    {
        $kept = array_filter($items, fn (array $item) => ($item['id'] ?? null) !== $id);

        return array_values(array_map(fn (array $item) => [...$item, 'children' => self::without($item['children'] ?? [], $id)], $kept));
    }

    private static function find(array $items, string $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }

            if (($found = self::find($item['children'] ?? [], $id)) !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function parentOf(array $items, string $id, ?string $parent = null): ?string
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $parent;
            }

            if (($found = self::parentOf($item['children'] ?? [], $id, (string) $item['id'])) !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function depthOf(array $items, string $id, int $depth = 1): ?int
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $depth;
            }

            if (($found = self::depthOf($item['children'] ?? [], $id, $depth + 1)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /** Levels in an item's subtree, the item included. */
    private static function height(array $item): int
    {
        $children = $item['children'] ?? [];

        return 1 + ($children === [] ? 0 : max(array_map(self::height(...), $children)));
    }
}
