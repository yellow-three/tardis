<?php

declare(strict_types=1);

namespace Tardis\Support;

use Illuminate\Support\Facades\File;

/**
 * The languages the panel can speak: those the package ships under lang/<code>
 * plus any a host adds by publishing lang/vendor/tardis/<code>.
 */
final class Locales
{
    /**
     * @return array<int, string>
     */
    public static function available(): array
    {
        $dirs = [
            dirname(__DIR__, 2).'/lang',
            lang_path('vendor/tardis'),
        ];

        $codes = [];

        foreach ($dirs as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }

            foreach (File::directories($dir) as $path) {
                $codes[] = basename($path);
            }
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }

    public static function isAvailable(string $locale): bool
    {
        return in_array($locale, self::available(), true);
    }

    /** Languages written right to left; the panel sets dir="rtl" for them. */
    public const RTL = ['ar', 'fa', 'he', 'ur'];

    public static function direction(?string $locale = null): string
    {
        $language = strtolower(strtok(str_replace('_', '-', $locale ?? app()->getLocale()), '-') ?: '');

        return in_array($language, self::RTL, true) ? 'rtl' : 'ltr';
    }

    /**
     * Native name for the switcher; falls back to the upper-cased code.
     */
    public static function name(string $locale): string
    {
        return [
            'en' => 'English',
            'tr' => 'Türkçe',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
            'ar' => 'العربية',
        ][$locale] ?? strtoupper($locale);
    }
}
