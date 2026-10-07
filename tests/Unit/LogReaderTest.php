<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Diagnostics\LogReader;

beforeEach(function () {
    $this->logDir = sys_get_temp_dir().'/tardis-logs-'.uniqid();
    File::ensureDirectoryExists($this->logDir);

    config()->set('tardis.system.logs.path', $this->logDir);
    config()->set('tardis.system.logs.filename_pattern', '/^[A-Za-z0-9._-]+\.log$/');
    config()->set('tardis.system.logs.tail', 200);
    config()->set('tardis.system.logs.max_bytes', 262144);
});

afterEach(function () {
    File::deleteDirectory($this->logDir);
});

test('files lists only matching log files, sorted', function () {
    File::put($this->logDir.'/b.log', 'b');
    File::put($this->logDir.'/a.log', 'a');
    File::put($this->logDir.'/notes.txt', 'x');
    File::put($this->logDir.'/.hidden', 'x');

    expect((new LogReader)->files())->toBe(['a.log', 'b.log']);
});

test('files returns an empty list when the directory is missing', function () {
    config()->set('tardis.system.logs.path', $this->logDir.'/missing');

    expect((new LogReader)->files())->toBe([]);
});

test('path falls back to the storage logs directory when unset', function () {
    config()->set('tardis.system.logs.path', null);

    expect((new LogReader)->path())->toBe(storage_path('logs'));
});

test('path resolves a relative path against storage', function () {
    config()->set('tardis.system.logs.path', 'logs');

    expect((new LogReader)->path())->toBe(storage_path('logs'));
});

test('tail returns the last lines', function () {
    File::put($this->logDir.'/laravel.log', "one\ntwo\nthree\nfour\n");

    expect((new LogReader)->tail('laravel.log', 2))->toBe(['three', 'four']);
});

test('tail uses the configured default line count', function () {
    config()->set('tardis.system.logs.tail', 2);
    File::put($this->logDir.'/laravel.log', "one\ntwo\nthree\n");

    expect((new LogReader)->tail('laravel.log'))->toBe(['two', 'three']);
});

test('tail honours the max bytes ceiling and drops the partial first line', function () {
    config()->set('tardis.system.logs.max_bytes', 10);
    File::put($this->logDir.'/laravel.log', str_repeat('a', 100)."\nlast\n");

    expect((new LogReader)->tail('laravel.log', 100))->toBe(['last']);
});

test('tail rejects path traversal', function (string $name) {
    File::put($this->logDir.'/laravel.log', 'x');

    expect(fn () => (new LogReader)->tail($name))->toThrow(InvalidArgumentException::class);
})->with(['../laravel.log', 'sub/laravel.log', '..log', 'laravel.log/../x.log']);

test('tail rejects a null byte in the name', function () {
    File::put($this->logDir.'/laravel.log', 'x');

    expect(fn () => (new LogReader)->tail("laravel\0.log"))->toThrow(InvalidArgumentException::class);
});

test('tail rejects a name that does not match the pattern', function () {
    File::put($this->logDir.'/notes.txt', 'x');

    expect(fn () => (new LogReader)->tail('notes.txt'))->toThrow(InvalidArgumentException::class);
});

test('tail rejects a symlink that escapes the log directory', function () {
    $outside = sys_get_temp_dir().'/tardis-secret-'.uniqid().'.log';
    File::put($outside, 'secret');
    symlink($outside, $this->logDir.'/link.log');

    try {
        expect(fn () => (new LogReader)->tail('link.log'))->toThrow(InvalidArgumentException::class);
    } finally {
        File::delete($outside);
    }
});

test('tail rejects a missing file', function () {
    expect(fn () => (new LogReader)->tail('missing.log'))->toThrow(InvalidArgumentException::class);
});
