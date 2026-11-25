<?php

declare(strict_types=1);

namespace Tardis\Support;

use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Menu\MenuItem;
use Tardis\Menu\MenuManager;
use Tardis\Plugins\PluginManager;

/**
 * What the command palette (Ctrl+K / ⌘K) offers the signed-in user: every
 * sidebar page they may open and "New …" for each BREAD they may add to.
 * Only what the sidebar would show is listed, so the palette never reveals
 * a screen the user cannot reach.
 */
final class CommandPalette
{
    /**
     * @return list<array{title: string, group: string, url: string, keywords: string}>
     */
    public function entries(): array
    {
        $menu = app(MenuManager::class);
        $menu->collectFromPlugins(app(PluginManager::class));

        $entries = [];

        foreach ($menu->tree() as $item) {
            $this->addItem($entries, $item, null);
        }

        $auth = app(BreadAuthorization::class);

        app(BreadManager::class)->all()
            ->filter(fn (BreadDefinition $definition, string $slug) => BreadDefinition::isModelClass($definition->model) && $auth->allows('add', $slug))
            ->each(function (BreadDefinition $definition, string $slug) use (&$entries) {
                $entries[] = [
                    'title' => (string) __('tardis::shell.palette.new', ['name' => $definition->resolvedName()]),
                    'group' => (string) __('tardis::shell.palette.actions'),
                    'url' => url(trim((string) config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/create'),
                    'keywords' => $slug.' '.$definition->resolvedNamePlural(),
                ];
            });

        return $this->unique($entries);
    }

    /**
     * @param  array<int, array{title: string, group: string, url: string, keywords: string}>  $entries
     */
    private function addItem(array &$entries, MenuItem $item, ?string $parent): void
    {
        if ($item->isDivider) {
            return;
        }

        $title = $item->resolvedTitle();
        $children = $item->children->filter(fn (MenuItem $child) => $child->isVisible());

        if ($children->isEmpty()) {
            $href = $item->href();

            if ($href !== '' && $href !== '#') {
                $entries[] = [
                    'title' => $title,
                    'group' => $parent ?? (string) __('tardis::shell.palette.pages'),
                    'url' => $href,
                    'keywords' => (string) ($item->section ?? ''),
                ];
            }

            return;
        }

        foreach ($children as $child) {
            $this->addItem($entries, $child, $title);
        }
    }

    /**
     * @param  array<int, array{title: string, group: string, url: string, keywords: string}>  $entries
     * @return list<array{title: string, group: string, url: string, keywords: string}>
     */
    private function unique(array $entries): array
    {
        $seen = [];

        return array_values(array_filter($entries, function (array $entry) use (&$seen) {
            if (isset($seen[$entry['url']])) {
                return false;
            }

            return $seen[$entry['url']] = true;
        }));
    }
}
