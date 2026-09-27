<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

function seedMedia(string $path, string $collection = 'media'): Media
{
    return Media::factory()->create([
        'name' => basename($path),
        'original_name' => basename($path),
        'path' => $path,
        'disk' => 'public',
        'collection' => $collection,
    ]);
}

test('the browser lists folders and files from the current path', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'a');
    Storage::disk('public')->put('media/readme.txt', 'b');

    Livewire::test('tardis::pages.media-browser')
        ->assertOk()
        ->assertSet('currentPath', '')
        ->assertSee('box')
        ->assertSee('readme.txt');
});

test('confirmDelete flags a folder as a directory', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'a');

    Livewire::test('tardis::pages.media-browser')
        ->call('confirmDelete', 'box')
        ->assertSet('deleteIsDirectory', true)
        ->assertSet('deletePath', 'box')
        ->assertSet('showDeleteModal', true);
});

test('confirmDelete flags a file as a file', function () {
    Storage::disk('public')->put('media/photo.jpg', 'a');

    Livewire::test('tardis::pages.media-browser')
        ->call('confirmDelete', 'photo.jpg')
        ->assertSet('deleteIsDirectory', false)
        ->assertSet('deletePath', 'photo.jpg')
        ->assertSet('showDeleteModal', true);
});

test('deleting a folder removes the whole tree and its rows', function () {
    Storage::disk('public')->put('media/box/top.jpg', 'a');
    Storage::disk('public')->put('media/box/deep/nested.jpg', 'b');
    Storage::disk('public')->put('media/keep.jpg', 'c');

    seedMedia('media/box/top.jpg', 'media/box');
    seedMedia('media/box/deep/nested.jpg', 'media/box/deep');
    seedMedia('media/keep.jpg', 'media');

    Livewire::test('tardis::pages.media-browser')
        ->call('confirmDelete', 'box')
        ->call('deleteFile')
        ->assertSet('showDeleteModal', false)
        ->assertSet('deletePath', null)
        ->assertSet('deleteIsDirectory', false);

    Storage::disk('public')->assertMissing('media/box/top.jpg');
    Storage::disk('public')->assertMissing('media/box/deep/nested.jpg');
    Storage::disk('public')->assertExists('media/keep.jpg');

    expect(Media::query()->count())->toBe(1)
        ->and(Media::query()->where('path', 'media/keep.jpg')->exists())->toBeTrue();
});

test('deleting a file removes only that file', function () {
    Storage::disk('public')->put('media/one.jpg', 'a');
    Storage::disk('public')->put('media/two.jpg', 'b');
    seedMedia('media/one.jpg');
    seedMedia('media/two.jpg');

    Livewire::test('tardis::pages.media-browser')
        ->call('confirmDelete', 'one.jpg')
        ->call('deleteFile')
        ->assertSet('showDeleteModal', false);

    Storage::disk('public')->assertMissing('media/one.jpg');
    Storage::disk('public')->assertExists('media/two.jpg');
    expect(Media::query()->count())->toBe(1);
});

test('bulk delete removes a selected folder recursively once confirmed', function () {
    Storage::disk('public')->put('media/box/top.jpg', 'a');
    Storage::disk('public')->put('media/box/deep/nested.jpg', 'b');
    seedMedia('media/box/top.jpg', 'media/box');
    seedMedia('media/box/deep/nested.jpg', 'media/box/deep');

    Livewire::test('tardis::pages.media-browser')
        ->set('selectedFiles', ['box'])
        ->call('bulkDelete')
        ->assertSet('showDeleteModal', true)
        ->call('deleteFile')
        ->assertSet('showDeleteModal', false)
        ->assertSet('selectedFiles', []);

    Storage::disk('public')->assertMissing('media/box/top.jpg');
    Storage::disk('public')->assertMissing('media/box/deep/nested.jpg');
    expect(Media::query()->count())->toBe(0);
});

test('bulk delete only arms the confirmation modal and deletes nothing yet', function () {
    Storage::disk('public')->put('media/one.jpg', 'a');
    Storage::disk('public')->put('media/box/two.jpg', 'b');
    seedMedia('media/one.jpg');
    seedMedia('media/box/two.jpg', 'media/box');

    Livewire::test('tardis::pages.media-browser')
        ->set('selectedFiles', ['one.jpg', 'box'])
        ->call('bulkDelete')
        ->assertSet('showDeleteModal', true)
        ->assertSet('deleteIsBulk', true)
        ->assertSee('Delete 2 Item(s)');

    // The selection must survive until the user actually confirms.
    Storage::disk('public')->assertExists('media/one.jpg');
    Storage::disk('public')->assertExists('media/box/two.jpg');
    expect(Media::query()->count())->toBe(2);
});

test('cancelling the bulk confirmation keeps every selected item', function () {
    Storage::disk('public')->put('media/one.jpg', 'a');
    seedMedia('media/one.jpg');

    Livewire::test('tardis::pages.media-browser')
        ->set('selectedFiles', ['one.jpg'])
        ->call('bulkDelete')
        ->call('$set', 'showDeleteModal', false)
        ->assertSet('showDeleteModal', false);

    Storage::disk('public')->assertExists('media/one.jpg');
    expect(Media::query()->count())->toBe(1);
});

test('a single delete after a bulk attempt does not sweep the selection', function () {
    Storage::disk('public')->put('media/one.jpg', 'a');
    Storage::disk('public')->put('media/two.jpg', 'b');
    seedMedia('media/one.jpg');
    seedMedia('media/two.jpg');

    Livewire::test('tardis::pages.media-browser')
        ->set('selectedFiles', ['one.jpg', 'two.jpg'])
        ->call('bulkDelete')
        ->call('confirmDelete', 'one.jpg')
        ->assertSet('deleteIsBulk', false)
        ->call('deleteFile')
        ->assertSet('showDeleteModal', false);

    Storage::disk('public')->assertMissing('media/one.jpg');
    Storage::disk('public')->assertExists('media/two.jpg');
    expect(Media::query()->count())->toBe(1);
});

test('bulk delete with an empty selection changes nothing', function () {
    Storage::disk('public')->put('media/one.jpg', 'a');
    seedMedia('media/one.jpg');

    Livewire::test('tardis::pages.media-browser')
        ->call('bulkDelete')
        ->assertOk()
        ->assertSet('showDeleteModal', false);

    Storage::disk('public')->assertExists('media/one.jpg');
    expect(Media::query()->count())->toBe(1);
});

test('the delete modal heading distinguishes folders from files', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'a');

    Livewire::test('tardis::pages.media-browser')
        ->call('confirmDelete', 'box')
        ->assertSee('Delete Folder');

    Storage::disk('public')->put('media/photo.jpg', 'b');

    Livewire::test('tardis::pages.media-browser')
        ->call('confirmDelete', 'photo.jpg')
        ->assertSee('Delete File');
});

test('showFileInfo serves a folder without erroring', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'a');

    Livewire::test('tardis::pages.media-browser')
        ->call('showFileInfo', 'box')
        ->assertOk()
        ->assertSet('showInfoModal', true)
        ->assertSet('infoFile.type', 'directory');
});

test('creating a folder appears in the listing', function () {
    Livewire::test('tardis::pages.media-browser')
        ->set('newDirectoryName', 'gallery')
        ->call('createDirectory')
        ->assertHasNoErrors()
        ->assertSet('newDirectoryName', null);

    Storage::disk('public')->assertExists('media/gallery');
});

test('creating a folder with a traversing name is rejected inline', function () {
    Livewire::test('tardis::pages.media-browser')
        ->set('newDirectoryName', '../escape')
        ->call('createDirectory')
        ->assertHasErrors('newDirectoryName');

    Storage::disk('public')->assertMissing('escape');
});

test('renaming a folder updates the database rows', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'a');
    Storage::disk('public')->put('media/box/deep/nested.jpg', 'b');
    seedMedia('media/box/photo.jpg', 'media/box');
    seedMedia('media/box/deep/nested.jpg', 'media/box/deep');

    Livewire::test('tardis::pages.media-browser')
        ->call('confirmRename', 'box')
        ->assertSet('renamePath', 'box')
        ->set('renameNewName', 'crate')
        ->call('renameFile')
        ->assertHasNoErrors();

    Storage::disk('public')->assertExists('media/crate/photo.jpg');
    expect(Media::query()->where('path', 'media/crate/photo.jpg')->exists())->toBeTrue();
});

test('renaming with a traversing name is rejected inline', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'a');

    Livewire::test('tardis::pages.media-browser')
        ->call('confirmRename', 'box')
        ->set('renameNewName', '../../escape')
        ->call('renameFile')
        ->assertHasErrors('renameNewName');

    Storage::disk('public')->assertExists('media/box/photo.jpg');
});

test('downloadSelected reports a friendly error when nothing is archivable', function () {
    Storage::disk('public')->put('media/box/photo.jpg', 'a');

    Livewire::test('tardis::pages.media-browser')
        ->set('selectedFiles', ['box'])
        ->call('downloadSelected')
        ->assertNotDispatched('download-zip')
        // Selection is preserved so the user can fix it instead of starting over.
        ->assertSet('selectedFiles', ['box']);
});

test('the same file can be uploaded twice in a row', function () {
    $component = Livewire::test('tardis::pages.media-browser')
        ->set('newUploads', UploadedFile::fake()->create('photo.jpg', 10));

    Storage::disk('public')->assertExists('media/photo.jpg');

    // Re-selecting the very same file is what a user does after an upload, and the
    // manager must give it a unique name rather than silently ignoring it.
    $component
        ->set('newUploads', UploadedFile::fake()->create('photo.jpg', 10));

    expect(Media::query()->count())->toBe(2);
    Storage::disk('public')->assertExists('media/photo.jpg');
    Storage::disk('public')->assertExists('media/photo_1.jpg');
});

test('the file input is re-keyed after an upload so it can be re-selected', function () {
    Livewire::test('tardis::pages.media-browser')
        ->assertSet('uploadInputKey', 0)
        ->assertSee('media-upload-0', false)
        ->set('newUploads', UploadedFile::fake()->create('photo.jpg', 10))
        // A fresh key forces Livewire to render a new <input>, which is what empties
        // the browser-side value. Without it the second selection fires no change event.
        ->assertSet('uploadInputKey', 1)
        ->assertSee('media-upload-1', false)
        // The selection buffer is emptied so the next pick is treated as a new upload.
        ->assertSet('newUploads', []);
});
