<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Assets\AssetHashes;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/tardis-asset-hashes-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    File::ensureDirectoryExists(dirname(AssetHashes::path()));
    @unlink(AssetHashes::path());
    AssetHashes::flush();
});

afterEach(function () {
    File::deleteDirectory($this->dir);
    @unlink(AssetHashes::path());
    AssetHashes::flush();
});

test('a settled file is hashed once and the hash is kept for the next process', function () {
    $file = $this->dir.'/app.css';
    File::put($file, '.a{}');
    touch($file, time() - 60);

    $hash = AssetHashes::of($file);
    AssetHashes::flush();

    expect($hash)->toBe(substr(hash_file('sha256', $file), 0, 16))
        ->and(AssetHashes::path())->toBeFile()
        ->and(array_values(include AssetHashes::path()))->toBe([$hash])
        ->and(AssetHashes::of($file))->toBe($hash);
});

test('a rewritten file gets a new hash', function () {
    $file = $this->dir.'/app.css';
    File::put($file, '.a{}');
    touch($file, time() - 60);
    $first = AssetHashes::of($file);

    File::put($file, '.b{color:red}');
    touch($file, time() - 30);

    expect(AssetHashes::of($file))->not->toBe($first);
});

test('a file written just now is hashed each time and not cached', function () {
    $file = $this->dir.'/app.css';
    File::put($file, '.a{}');
    $first = AssetHashes::of($file);

    File::put($file, '.b{}'); // same size, same second

    expect(AssetHashes::of($file))->not->toBe($first)
        ->and(AssetHashes::path())->not->toBeFile();
});

test('a missing file has no hash', function () {
    expect(AssetHashes::of($this->dir.'/gone.css'))->toBeNull();
});
