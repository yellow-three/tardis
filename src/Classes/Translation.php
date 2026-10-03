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
     * Resolve a translatable label — a BREAD name, a field label, a menu title.
     *
     * Three shapes are accepted, in this order:
     *
     *  - a locale-keyed map (or its JSON encoding), resolved like any other
     *    translatable value: the requested locale, then the first non-empty;
     *  - a translation key, resolved through __() when the key exists;
     *  - anything else, returned as plain text.
     *
     * Plain strings stay plain strings, so a definition that predates
     * translatable labels keeps rendering exactly as before.
     */
    public static function label(mixed $value, ?array $locales = null, ?string $locale = null): string
    {
        if (is_array($value)) {
            return self::value($value, $locales, $locale);
        }

        if (! is_string($value)) {
            return is_scalar($value) ? (string) $value : '';
        }

        // Only a locale map is translated; "Hello" must not be treated as one.
        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return self::value($decoded, $locales, $locale);
        }

        return self::key($value);
    }

    /**
     * Resolve a translation key, leaving text that is not a key untouched.
     *
     * __() returns its own argument when the key is missing, so a literal
     * label comes back verbatim and needs no special case here.
     */
    public static function key(string $value, array $replace = []): string
    {
        return (string) __($value, $replace);
    }

    /**
     * Whether a translatable value actually carries per-locale translations.
     * A label stored as a plain string is not translatable and must not be
     * rendered as a single-locale tab.
     */
    public static function isTranslatable(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        if (is_string($value)) {
            return is_array(json_decode($value, true));
        }

        return false;
    }

    /**
     * Resolve the display value for the current (or given) locale, falling
     * back to the first non-empty translation when that locale is missing
     * or empty.
     */
    public static function value(mixed $value, ?array $locales = null, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $normalized = self::normalize($value, self::localesFor($locales, $locale));

        if (($normalized[$locale] ?? '') !== '') {
            return (string) $normalized[$locale];
        }

        $filled = self::firstFilled($normalized);

        return $filled === null ? '' : $filled[1];
    }

    /**
     * The locale a resolved value was actually taken from, when the requested
     * locale had nothing of its own: null when it did, or when there is nothing
     * to fall back to.
     *
     * Lets a screen say where a borrowed translation came from instead of
     * showing it as if it were written in the current language.
     */
    public static function sourceLocale(mixed $value, ?array $locales = null, ?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();
        $normalized = self::normalize($value, self::localesFor($locales, $locale));

        if (($normalized[$locale] ?? '') !== '') {
            return null;
        }

        $filled = self::firstFilled($normalized);

        return $filled === null ? null : $filled[0];
    }

    /**
     * The locales to normalize a lookup against: the configured ones, plus the
     * requested locale itself.
     *
     * normalize() projects a value onto the locales it is given and drops the
     * rest, so with an empty tardis.locales the only locale kept is the app
     * one. A caller that asks for a different locale — a menu title rendered
     * for a language other than the current one — would then be answered with
     * another language's text, and sourceLocale() would name that language as
     * the source. Appending the requested locale keeps the answer in the
     * language that was asked for.
     *
     * @return array<int, string>
     */
    protected static function localesFor(?array $fieldLocales, string $locale): array
    {
        $locales = self::locales($fieldLocales);

        if (! in_array($locale, $locales, true)) {
            $locales[] = $locale;
        }

        return $locales;
    }

    /**
     * The first non-empty translation in a normalized map, as [locale, text].
     *
     * @param  array<string, string>  $normalized
     * @return array{0: string, 1: string}|null
     */
    protected static function firstFilled(array $normalized): ?array
    {
        foreach ($normalized as $locale => $translation) {
            if ($translation !== '') {
                return [(string) $locale, (string) $translation];
            }
        }

        return null;
    }
}
