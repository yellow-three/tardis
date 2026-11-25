<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * Flatten lang/<locale>/*.php into 'group.key' => value.
 *
 * @return array<string, string>
 */
function tardisLangKeys(string $locale): array
{
    $keys = [];

    foreach (glob(__DIR__.'/../../lang/'.$locale.'/*.php') ?: [] as $file) {
        $group = basename($file, '.php');

        foreach (Arr::dot(require $file) as $key => $value) {
            $keys[$group.'.'.$key] = (string) $value;
        }
    }

    return $keys;
}

/**
 * @return array<int, string> every PHP file that can ask for a translation
 */
function tardisSourceFiles(): array
{
    $files = [];

    foreach ([__DIR__.'/../../src', __DIR__.'/../../resources/views', __DIR__.'/../../routes'] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

test('every translation key the code asks for exists in English', function () {
    $english = tardisLangKeys('en');
    $missing = [];

    foreach (tardisSourceFiles() as $file) {
        preg_match_all("/(?:__|trans|trans_choice)\\(\\s*'tardis::([a-z0-9_.\\-]+)'/i", file_get_contents($file), $matches);

        foreach ($matches[1] as $key) {
            // 'tardis::group.prefix.' . $variable is a family of keys: some key must live under it.
            $exists = str_ends_with($key, '.')
                ? collect(array_keys($english))->contains(fn (string $known) => str_starts_with($known, $key))
                : isset($english[$key]);

            if (! $exists) {
                $missing[] = $key.' ('.basename($file).')';
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});

test('every English string has a Turkish translation and vice versa', function () {
    $english = array_keys(tardisLangKeys('en'));
    $turkish = array_keys(tardisLangKeys('tr'));

    expect(array_values(array_diff($english, $turkish)))->toBe([], 'missing from lang/tr')
        ->and(array_values(array_diff($turkish, $english)))->toBe([], 'lang/tr has keys English lacks');
});

test('every shipped language has exactly the English keys', function (string $locale) {
    $english = array_keys(tardisLangKeys('en'));
    $other = array_keys(tardisLangKeys($locale));

    expect(array_values(array_diff($english, $other)))->toBe([], "missing from lang/{$locale}")
        ->and(array_values(array_diff($other, $english)))->toBe([], "lang/{$locale} has keys English lacks");
})->with(['de', 'fr', 'es', 'ar']);

test('translations keep the same :placeholders as English', function (string $locale) {
    $english = tardisLangKeys('en');
    $turkish = tardisLangKeys($locale);
    $broken = [];

    foreach ($english as $key => $text) {
        if (! isset($turkish[$key])) {
            continue;
        }

        preg_match_all('/:[a-z_]+/i', $text, $en);
        preg_match_all('/:[a-z_]+/i', $turkish[$key], $tr);

        $a = $en[0];
        $b = $tr[0];
        sort($a);
        sort($b);

        if ($a !== $b) {
            $broken[] = $key;
        }
    }

    expect($broken)->toBe([]);
})->with(['tr', 'de', 'fr', 'es', 'ar']);

test('no translation is empty', function () {
    foreach (['en', 'tr', 'de', 'fr', 'es', 'ar'] as $locale) {
        $empty = array_keys(array_filter(tardisLangKeys($locale), fn ($text) => trim($text) === ''));

        expect($empty)->toBe([], "empty strings in lang/{$locale}");
    }
});
