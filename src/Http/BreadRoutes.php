<?php

declare(strict_types=1);

namespace Tardis\Http;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Bread\ReservedSlugs;

/**
 * Generates the browse/add/read/edit routes of every BREAD definition.
 *
 * Routes are constrained to the slugs that exist, so there is no catch-all:
 * an unknown URL falls through to a plugin's route or a 404 instead of being
 * swallowed by `/{slug}/{id}`. The route names are the same for every BREAD
 * (`tardis.bread.index` with a `slug` parameter) so links keep working; a
 * definition that replaces the component behind an action gets a literal route
 * registered first, under `<name>.<slug>`, for the same address.
 *
 * The slug list is read when routes load. With `php artisan route:cache` a
 * BREAD created afterwards is routable only after the cache is rebuilt.
 */
final class BreadRoutes
{
    /** Action => [uri, route name, definition key]. */
    private const ACTIONS = [
        'browse' => ['/{slug}', 'bread.index'],
        'add' => ['/{slug}/create', 'bread.add'],
        'edit' => ['/{slug}/{id}/edit', 'bread.edit.item'],
        'read' => ['/{slug}/{id}', 'bread.read'],
    ];

    private const STOCK = [
        'browse' => 'tardis::pages.bread.index',
        'add' => 'tardis::pages.bread.create',
        'edit' => 'tardis::pages.bread.edit',
        'read' => 'tardis::pages.bread.read',
    ];

    public static function define(): void
    {
        $definitions = self::definitions();

        Route::middleware(['web', 'tardis.admin'])
            ->prefix(config('tardis.admin.prefix', 'admin'))
            ->name('tardis.')
            ->group(function () use ($definitions) {
                // Overrides first, with the more specific URIs ahead of
                // /{slug}/{id}: that route would otherwise capture "create".
                foreach (['browse', 'add', 'edit', 'read'] as $action) {
                    foreach ($definitions as $slug => $definition) {
                        $component = $definition->components[$action] ?? null;

                        if (is_string($component) && $component !== '') {
                            self::route($action, $component, $slug, $slug);
                        }
                    }
                }

                $pattern = $definitions === []
                    ? '(?!)'
                    : implode('|', array_map(fn (string $slug) => preg_quote((string) $slug, '#'), array_keys($definitions)));

                foreach (['browse', 'add', 'edit', 'read'] as $action) {
                    self::route($action, self::STOCK[$action], null, null, $pattern);
                }
            });
    }

    private static function route(string $action, string $component, ?string $literalSlug, ?string $nameSuffix, ?string $pattern = null): void
    {
        [$uri, $name] = self::ACTIONS[$action];

        if ($literalSlug !== null) {
            $uri = str_replace('{slug}', $literalSlug, $uri);
            $name .= '.'.$nameSuffix;
        }

        $route = Route::livewire($uri, $component)->name($name);

        if ($pattern !== null) {
            $route->where('slug', $pattern);
        }

        if ($action === 'read') {
            $route->where('id', '(?!create$)[^/]+');
        }
    }

    /**
     * @return array<string, BreadDefinition>
     */
    private static function definitions(): array
    {
        try {
            return app(BreadManager::class)->all()
                ->filter(function (BreadDefinition $definition, string $slug) {
                    if (ReservedSlugs::has($slug)) {
                        Log::warning('Skipping routes for a BREAD with a reserved slug.', ['slug' => $slug]);

                        return false;
                    }

                    return true;
                })
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Could not read BREAD definitions for routing.', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
