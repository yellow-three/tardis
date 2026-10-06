<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tardis\Manager\MediaManager;

beforeEach(function () {
    $this->artisan('migrate');
});

test('rejects unknown token in template', function () {
    Storage::fake('public');
    $manager = new MediaManager(['disk' => 'public']);
    $file = UploadedFile::fake()->image('photo.jpg');

    expect(fn () => $manager->upload($file, '', null, '{unknown}/photo.jpg'))
        ->toThrow(InvalidArgumentException::class);
});

test('template with uid and date and random works', function () {
    Storage::fake('public');
    $manager = new MediaManager(['disk' => 'public']);
    $file = UploadedFile::fake()->image('photo.jpg');

    // No authenticated user in this test, so {uid} falls back to "guest".
    $media = $manager->upload($file, '', null, '{uid}/{date:Y/m}/{random:8}.jpg');
    expect($media->name)->toMatch('#^guest/\d{4}/\d{2}/[a-zA-Z0-9]{8}\.jpg$#');
});

test('rejects path traversal ..', function () {
    Storage::fake('public');
    $manager = new MediaManager(['disk' => 'public']);
    $file = UploadedFile::fake()->image('photo.jpg');

    expect(fn () => $manager->upload($file, '', null, '../evil.jpg'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $manager->upload($file, '', null, 'foo/../../evil.jpg'))
        ->toThrow(InvalidArgumentException::class);
});

test('rejects leading slash or backslash', function () {
    Storage::fake('public');
    $manager = new MediaManager(['disk' => 'public']);
    $file = UploadedFile::fake()->image('photo.jpg');

    expect(fn () => $manager->upload($file, '', null, '/evil.jpg'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $manager->upload($file, '', null, '\\evil.jpg'))
        ->toThrow(InvalidArgumentException::class);
});

test('rejects null byte', function () {
    Storage::fake('public');
    $manager = new MediaManager(['disk' => 'public']);
    $file = UploadedFile::fake()->image('photo.jpg');

    expect(fn () => $manager->upload($file, '', null, "photo\x00.jpg"))
        ->toThrow(InvalidArgumentException::class);
});

test('only allowed tokens are used', function () {
    Storage::fake('public');
    $manager = new MediaManager(['disk' => 'public']);
    $file = UploadedFile::fake()->image('test.png');

    $media = $manager->upload($file, '', null, '{name}-{filename}-{ext}-{uid}');
    expect($media->name)->toContain('test')
        ->toContain('.png');
});

test('rejects the removed {extension} token alias', function () {
    Storage::fake('public');
    $manager = new MediaManager(['disk' => 'public']);
    $file = UploadedFile::fake()->image('test.png');

    expect(fn () => $manager->upload($file, '', null, '{extension}'))
        ->toThrow(InvalidArgumentException::class);
});
