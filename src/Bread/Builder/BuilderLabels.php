<?php

declare(strict_types=1);

namespace Tardis\Bread\Builder;

use Tardis\Support\Translation;

/**
 * How the BREAD builder edits a definition's labels (name, plural name,
 * description), which may be a plain string or a locale map.
 */
final class BuilderLabels
{
    /**
     * The locale map behind a definition label, or an empty array when the
     * label is a plain string and therefore not translatable.
     *
     * @return array<string, string>
     */
    public static function translations(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(strval(...), $value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_map(strval(...), $decoded) : [];
    }

    /**
     * Keep a definition's locale map when its resolved label was not retyped.
     *
     * A deliberate edit replaces the map with the single string now shown in
     * the input, so what gets stored is exactly what the form displayed.
     *
     * @param  array<string, string>  $translations
     * @return string|array<string, string>|null
     */
    public static function payload(?string $edited, array $translations): string|array|null
    {
        if ($translations === []) {
            return $edited;
        }

        return $edited === Translation::label($translations) ? $translations : $edited;
    }

    /**
     * Icons the builder offers for a BREAD's menu entry.
     *
     * @return list<string>
     */
    public static function icons(): array
    {
        return [
            'table-cells', 'document-text', 'database', 'folder', 'photo', 'puzzle-piece',
            'squares-2x2', 'key', 'tag', 'hashtag', 'book-open', 'user-group', 'bell',
            'calendar-days', 'clock', 'cog-6-tooth', 'bars-3', 'chevron-up-down',
            'paper-clip', 'link', 'adjustments-horizontal', 'text', 'toggle',
            'pencil-square', 'plus-circle', 'check-circle', 'x-circle', 'power',
            'check', 'x-mark', 'plus', 'sun', 'moon', 'lock-closed',
        ];
    }
}
