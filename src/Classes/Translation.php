<?php

declare(strict_types=1);

namespace Tardis\Classes;

/**
 * Helpers for translatable BREAD field values.
 *
 * A translatable value is stored as a locale-keyed map, e.g.
 * ['en' => 'Hello', 'tr' => 'Merhaba'], in a JSON column with an array cast.
 * The same map can also arrive as a JSON-encoded string from models without
 * a cast, which normalize() accepts defensively.
 */
class Translation
{
    /**
     * Resolve the locales a translatable field should render for:
     * field-level overrides win, then the global tardis.locales config,
     * falling back to the current application locale.
     */
    public static function locales(?array $fieldLocales = null): array
    {
        $locales = $fieldLocales ?: (array) config('tardis.locales', []);
        $locales = array_values(array_filter(array_unique(array_map('strval', $locales))));

        return $locales ?: [app()->getLocale() ?: 'en'];
    }

    /**
     * Normalize any stored representation (locale-keyed array or JSON string)
     * into a locale-keyed map with every resolved locale present.
     */
    public static function normalize(mixed $value, ?array $locales = null): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            $value = [];
        }

        $normalized = [];

        foreach (self::locales($locales) as $locale) {
            $normalized[$locale] = (string) ($value[$locale] ?? '');
        }

        return $normalized;
    }

    /**
     * Resolve the display value for the current (or given) locale, falling
     * back to the first non-empty translation when that locale is missing
     * or empty.
     */
    public static function value(mixed $value, ?array $locales = null, ?string $locale = null): string
    {
        $normalized = self::normalize($value, $locales);
        $locale = $locale ?: app()->getLocale();

        if (isset($normalized[$locale]) && $normalized[$locale] !== '') {
            return (string) $normalized[$locale];
        }

        foreach ($normalized as $translation) {
            if ($translation !== '') {
                return (string) $translation;
            }
        }

        return '';
    }
}
