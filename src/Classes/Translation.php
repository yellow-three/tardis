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
     * Normalize any stored representation (locale-keyed array, JSON string, or
     * plain value) into a locale-keyed map with every resolved locale present.
     */
    public static function normalize(mixed $value, ?array $locales = null): array
    {
        $locales = self::locales($locales);

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            // Only an object or array decodes to a locale map. A plain value
            // stored as text is kept as-is: treating it as broken JSON is what
            // silently emptied a translatable field on the edit page, and
            // "123" is the sharper case, since json_decode turns that into an
            // int rather than failing.
            $value = is_array($decoded) ? $decoded : $value;
        }

        // A plain scalar is the translation for the locale the field is being
        // edited in, rather than a value to discard.
        if (is_string($value) || is_int($value) || is_float($value)) {
            return [$locales[0] => (string) $value] + array_fill_keys($locales, '');
        }

        if (! is_array($value)) {
            return array_fill_keys($locales, '');
        }

        $normalized = [];

        foreach ($locales as $locale) {
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
