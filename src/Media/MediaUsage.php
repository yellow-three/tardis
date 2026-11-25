<?php

declare(strict_types=1);

namespace Tardis\Media;

use Illuminate\Support\Facades\Log;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Facades\Tardis;
use Tardis\Settings\Setting;
use Tardis\Support\Like;

/**
 * Where a media file is used: BREAD records whose file, image, media or rich
 * text fields hold its path, and settings whose value does. Shown in the
 * media screen's file details and before a delete, so a file still in use is
 * not removed by accident.
 */
final class MediaUsage
{
    /** Field types that hold a path (or a text that may embed one). */
    public const FIELD_TYPES = ['image', 'file', 'media_picker', 'multiple_images', 'rich_text', 'markdown'];

    /**
     * @param  string  $path  the file's path on the media disk
     * @return list<array{source: string, label: string, url: ?string}>
     */
    public function find(string $path, int $limit = 25): array
    {
        $path = trim($path, '/');

        if ($path === '') {
            return [];
        }

        $usage = [];

        foreach (app(BreadManager::class)->all() as $slug => $definition) {
            $usage = [...$usage, ...$this->inBread((string) $slug, $definition, $path, $limit - count($usage))];

            if (count($usage) >= $limit) {
                return array_slice($usage, 0, $limit);
            }
        }

        foreach (Tardis::settings()->all() as $setting) {
            /** @var Setting $setting */
            if (is_string($setting->value) && str_contains($setting->value, $path)) {
                $usage[] = [
                    'source' => (string) __('tardis::media.usage_setting'),
                    'label' => $setting->getFullKey(),
                    'url' => route('tardis.settings.index'),
                ];
            }
        }

        return array_slice($usage, 0, $limit);
    }

    /**
     * @return list<array{source: string, label: string, url: ?string}>
     */
    private function inBread(string $slug, BreadDefinition $definition, string $path, int $limit): array
    {
        if ($limit <= 0 || ! BreadDefinition::isModelClass($definition->model)) {
            return [];
        }

        $columns = array_values(array_filter(array_map(
            fn (array $field) => in_array($field['type'] ?? '', self::FIELD_TYPES, true) ? (string) ($field['name'] ?? '') : '',
            $definition->fields,
        )));

        if ($columns === []) {
            return [];
        }

        try {
            $query = ($definition->model)::query();
            $like = Like::operator($query->getQuery());
            $pattern = '%'.addcslashes($path, '\\%_').'%';

            $records = $query->where(function ($where) use ($columns, $like, $pattern) {
                foreach ($columns as $column) {
                    $where->orWhere($column, $like, $pattern);
                }
            })->limit($limit)->get();
        } catch (\Throwable $e) {
            Log::notice('Could not look for media usage in a BREAD.', ['bread' => $slug, 'error' => $e->getMessage()]);

            return [];
        }

        $prefix = trim((string) config('tardis.admin.prefix', 'admin'), '/');

        return $records->map(fn ($record) => [
            'source' => $definition->resolvedNamePlural(),
            'label' => '#'.$record->getKey(),
            'url' => url($prefix.'/'.$slug.'/'.$record->getKey().'/edit'),
        ])->values()->all();
    }
}
