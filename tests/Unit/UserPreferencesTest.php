<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Support\UserPreferences;

beforeEach(function () {
    $this->prefsFile = sys_get_temp_dir().'/tardis-prefs-'.uniqid().'/preferences.json';
});

afterEach(function () {
    File::deleteDirectory(dirname($this->prefsFile));
});

test('a preference is stored per user and read back', function () {
    $prefs = new UserPreferences($this->prefsFile);

    $prefs->set(1, 'locale', 'tr');
    $prefs->set(2, 'locale', 'en');

    expect($prefs->get(1, 'locale'))->toBe('tr')
        ->and($prefs->get(2, 'locale'))->toBe('en')
        ->and($prefs->get(3, 'locale'))->toBeNull()
        ->and($prefs->get(3, 'locale', 'en'))->toBe('en');
});

test('preferences survive a new instance because they live in a file', function () {
    (new UserPreferences($this->prefsFile))->set(7, 'theme', ['mode' => 'light', 'light' => 'tardis-light', 'dark' => 'tardis-dark']);

    expect((new UserPreferences($this->prefsFile))->get(7, 'theme'))->toBe(['mode' => 'light', 'light' => 'tardis-light', 'dark' => 'tardis-dark']);
});

test('a missing or corrupt file means no preferences', function () {
    File::ensureDirectoryExists(dirname($this->prefsFile));
    File::put($this->prefsFile, '{broken');

    expect((new UserPreferences($this->prefsFile))->get(1, 'locale'))->toBeNull();
});

test('forget removes one preference and leaves the rest', function () {
    $prefs = new UserPreferences($this->prefsFile);
    $prefs->set(1, 'locale', 'tr');
    $prefs->set(1, 'theme', ['mode' => 'dark']);

    $prefs->forget(1, 'locale');

    expect($prefs->get(1, 'locale'))->toBeNull()
        ->and($prefs->get(1, 'theme'))->toBe(['mode' => 'dark']);
});

test('a guest has no stored preferences and cannot save any', function () {
    $prefs = new UserPreferences($this->prefsFile);

    $prefs->set(null, 'locale', 'tr');

    expect($prefs->get(null, 'locale'))->toBeNull()
        ->and(File::exists($this->prefsFile))->toBeFalse();
});

test('the file is written atomically and never leaves temp files behind', function () {
    $prefs = new UserPreferences($this->prefsFile);

    foreach (range(1, 5) as $i) {
        $prefs->set($i, 'locale', 'en');
    }

    expect(array_map('basename', File::files(dirname($this->prefsFile))))->toBe(['preferences.json']);
});
