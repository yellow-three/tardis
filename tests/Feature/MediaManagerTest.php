<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tardis\Manager\MediaManager;
use Tardis\Models\Media;

beforeEach(function () {
    Schema::dropIfExists('tardis_media');
    Schema::create('tardis_media', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('original_name');
        $table->string('path');
        $table->string('disk');
        $table->string('mime_type')->nullable();
        $table->unsignedBigInteger('size')->default(0);
        $table->string('alt_text')->nullable();
        $table->string('collection')->default('default');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });

    config()->set('tardis-media.disk', 'public');
    config()->set('tardis-media.path', 'media');

    Storage::fake('public');
});

afterEach(function () {
    Schema::dropIfExists('tardis_media');
});

function manager(): MediaManager
{
    return app(MediaManager::class);
}

function mediaRow(string $path, string $collection = 'media'): Media
{
    return Media::factory()->create([
        'name' => basename($path),
        'original_name' => basename($path),
        'path' => $path,
        'disk' => 'public',
        'collection' => $collection,
    ]);
}

test('upload stores the file and creates a matching row', function () {
    $media = manager()->upload(UploadedFile::fake()->image('photo.jpg'), 'gallery');

    Storage::disk('public')->assertExists('media/gallery/photo.jpg');

    expect($media->path)->toBe('media/gallery/photo.jpg')
        ->and($media->collection)->toBe('media/gallery')
        ->and(Media::query()->where('path', 'media/gallery/photo.jpg')->exists())->toBeTrue();
});

test('upload de-duplicates a colliding filename', function () {
    Storage::disk('public')->put('media/gallery/photo.jpg', 'first');

    $media = manager()->upload(UploadedFile::fake()->image('photo.jpg'), 'gallery');

    expect($media->name)->toBe('photo_1.jpg');
    Storage::disk('public')->assertExists('media/gallery/photo.jpg');
    Storage::disk('public')->assertExists('media/gallery/photo_1.jpg');
});

test('deleteFile removes the stored object and its database row', function () {
    Storage::disk('public')->put('media/gallery/photo.jpg', 'bytes');
    mediaRow('media/gallery/photo.jpg', 'media/gallery');

    expect(manager()->deleteFile('gallery/photo.jpg'))->toBeTrue();

    Storage::disk('public')->assertMissing('media/gallery/photo.jpg');
    expect(Media::query()->where('path', 'media/gallery/photo.jpg')->exists())->toBeFalse();
});

test('deleteFile tolerates a file that has no database row', function () {
    Storage::disk('public')->put('media/orphan.jpg', 'bytes');

    expect(manager()->deleteFile('orphan.jpg'))->toBeTrue();

    Storage::disk('public')->assertMissing('media/orphan.jpg');
});

test('deleteDirectory removes the tree and every descendant row', function () {
    Storage::disk('public')->put('media/box/top.jpg', 'a');
    Storage::disk('public')->put('media/box/deep/nested.jpg', 'b');
    Storage::disk('public')->put('media/keep.jpg', 'c');

    mediaRow('media/box/top.jpg', 'media/box');
    mediaRow('media/box/deep/nested.jpg', 'media/box/deep');
    mediaRow('media/keep.jpg', 'media');

    expect(manager()->deleteDirectory('box'))->toBeTrue();

    Storage::disk('public')->assertMissing('media/box/top.jpg');
    Storage::disk('public')->assertMissing('media/box/deep/nested.jpg');
    Storage::disk('public')->assertExists('media/keep.jpg');

    expect(Media::query()->whereIn('path', ['media/box/top.jpg', 'media/box/deep/nested.jpg'])->count())->toBe(0)
        ->and(Media::query()->where('path', 'media/keep.jpg')->exists())->toBeTrue();
});

test('deleteDirectory does not touch a sibling sharing the name prefix', function () {
    Storage::disk('public')->put('media/boxbox/photo.jpg', 'a');
    mediaRow('media/boxbox/photo.jpg', 'media/boxbox');

    manager()->deleteDirectory('box');

    Storage::disk('public')->assertExists('media/boxbox/photo.jpg');
    expect(Media::query()->where('path', 'media/boxbox/photo.jpg')->exists())->toBeTrue();
});

test('rename moves the file and updates the row path and name', function () {
    Storage::disk('public')->put('media/gallery/photo.jpg', 'bytes');
    mediaRow('media/gallery/photo.jpg', 'media/gallery');

    expect(manager()->rename('gallery/photo.jpg', 'renamed.jpg'))->toBeTrue();

    Storage::disk('public')->assertMissing('media/gallery/photo.jpg');
    Storage::disk('public')->assertExists('media/gallery/renamed.jpg');

    expect(Media::query()->where('path', 'media/gallery/renamed.jpg')->exists())->toBeTrue()
        ->and(Media::query()->where('path', 'media/gallery/photo.jpg')->exists())->toBeFalse();

    // name tracks the current basename, while original_name keeps the uploaded one.
    $media = Media::query()->where('path', 'media/gallery/renamed.jpg')->first();
    expect($media->name)->toBe('renamed.jpg')
        ->and($media->original_name)->toBe('photo.jpg');
});

test('renaming a directory leaves descendant basenames untouched', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'a');
    mediaRow('media/box/photo.jpg', 'media/box');

    manager()->rename('box', 'crate');

    // The directory prefix changes but each file keeps its own basename.
    expect(Media::query()->where('path', 'media/crate/photo.jpg')->value('name'))->toBe('photo.jpg');
});

test('rename rewrites descendant paths and collections for a directory', function () {
    Storage::disk('public')->put('media/box/top.jpg', 'a');
    Storage::disk('public')->put('media/box/deep/nested.jpg', 'b');
    Storage::disk('public')->put('media/boxbox/other.jpg', 'c');

    mediaRow('media/box/top.jpg', 'media/box');
    mediaRow('media/box/deep/nested.jpg', 'media/box/deep');
    mediaRow('media/boxbox/other.jpg', 'media/boxbox');

    expect(manager()->rename('box', 'crate'))->toBeTrue();

    Storage::disk('public')->assertExists('media/crate/top.jpg');
    Storage::disk('public')->assertExists('media/crate/deep/nested.jpg');
    Storage::disk('public')->assertExists('media/boxbox/other.jpg');

    expect(Media::query()->where('path', 'media/crate/top.jpg')->value('collection'))->toBe('media/crate')
        ->and(Media::query()->where('path', 'media/crate/deep/nested.jpg')->value('collection'))->toBe('media/crate/deep')
        ->and(Media::query()->where('path', 'media/boxbox/other.jpg')->exists())->toBeTrue();
});

test('rename returns false when the source does not exist', function () {
    expect(manager()->rename('gallery/missing.jpg', 'renamed.jpg'))->toBeFalse();

    Storage::disk('public')->assertMissing('media/gallery/renamed.jpg');
});

test('createDirectory creates the folder inside the media root', function () {
    expect(manager()->createDirectory('gallery', '2026'))->toBeTrue();

    Storage::disk('public')->assertExists('media/gallery/2026');
});

test('createDirectory rejects a traversing name', function (string $name) {
    expect(fn () => manager()->createDirectory('gallery', $name))
        ->toThrow(InvalidArgumentException::class);

    Storage::disk('public')->assertMissing('media/2026');
})->with([
    'parent escape' => '..',
    'nested escape' => '../outside',
    'absolute path' => '/etc',
    'backslash escape' => '..\\outside',
    'trailing separator' => 'sub/child',
]);

test('rename rejects a traversing name', function (string $name) {
    Storage::disk('public')->put('media/gallery/photo.jpg', 'bytes');

    expect(fn () => manager()->rename('gallery/photo.jpg', $name))
        ->toThrow(InvalidArgumentException::class);

    Storage::disk('public')->assertExists('media/gallery/photo.jpg');
})->with([
    'parent escape' => '..',
    'nested escape' => '../../escape.jpg',
    'absolute path' => '/etc/passwd',
    'backslash escape' => '..\\escape.jpg',
]);

test('deleteFile refuses a traversing path instead of deleting outside the media root', function () {
    Storage::disk('public')->put('outside.txt', 'precious');

    expect(fn () => manager()->deleteFile('../../outside.txt'))
        ->toThrow(InvalidArgumentException::class);

    Storage::disk('public')->assertExists('outside.txt');
});

test('getFileInfo describes a directory without touching file-only apis', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'bytes');

    $info = manager()->getFileInfo('box');

    expect($info)->not->toBeNull()
        ->and($info['type'])->toBe('directory')
        ->and($info['mime_type'])->toBe('directory')
        ->and($info['size'])->toBe(0)
        ->and($info['last_modified'])->toBeNull()
        ->and($info['name'])->toBe('box')
        ->and($info['relative_path'])->toBe('box');
});

test('getFileInfo still reports the mime type for a file', function () {
    Storage::disk('public')->put('media/gallery/photo.jpg', 'bytes');

    $info = manager()->getFileInfo('gallery/photo.jpg');

    expect($info['type'])->toBe('image/jpeg')
        ->and($info['mime_type'])->toBe('image/jpeg')
        ->and($info['size'])->toBe(5)
        ->and($info['relative_path'])->toBe('gallery/photo.jpg');
});

test('getFileInfo returns null for a missing path', function () {
    expect(manager()->getFileInfo('gallery/missing.jpg'))->toBeNull();
});

test('downloadZip produces a readable archive of the selected files', function () {
    Storage::disk('public')->put('media/one.jpg', 'first');
    Storage::disk('public')->put('media/two.jpg', 'second');

    $zipPath = manager()->downloadZip(['one.jpg', 'two.jpg'], 'export-test.zip');

    try {
        expect(is_file($zipPath))->toBeTrue();

        $zip = new ZipArchive;
        expect($zip->open($zipPath))->toBeTrue();
        expect($zip->numFiles)->toBe(2)
            ->and($zip->getFromName('one.jpg'))->toBe('first')
            ->and($zip->getFromName('two.jpg'))->toBe('second');

        $zip->close();
    } finally {
        if (is_file($zipPath)) {
            unlink($zipPath);
        }
    }
});

test('downloadZip rejects a selection that yields no archivable file', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'bytes');

    // Folders are selectable in the UI, so "select all + download" can reach this
    // path. Returning $zipPath here used to hand back a file that was never written.
    expect(fn () => manager()->downloadZip(['box', 'missing.jpg'], 'export-skip.zip'))
        ->toThrow(RuntimeException::class, 'None of the selected media entries could be archived.');

    Storage::disk('public')->assertExists('media/box/photo.jpg');
});
