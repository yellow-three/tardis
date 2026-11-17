<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * The layout translates the #[Title] value, so a page title written as plain
 * English stays English in every panel language.
 */
test('every page title is a translation key that exists in each panel language', function () {
    $titles = collect(File::allFiles(__DIR__.'/../../resources/views'))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php'))
        ->flatMap(function ($file) {
            preg_match_all("/#\\[Title\\('([^']+)'\\)\\]/", $file->getContents(), $matches);

            return array_map(fn ($title) => [$file->getRelativePathname(), $title], $matches[1]);
        });

    expect($titles)->not->toBeEmpty();

    foreach ($titles as [$file, $title]) {
        expect($title)->toStartWith('tardis::', "{$file} has an untranslated title [{$title}]");

        foreach (['en', 'tr'] as $locale) {
            expect(trans()->hasForLocale($title, $locale))->toBeTrue("{$file}: [{$title}] is missing in {$locale}");
        }
    }
});
