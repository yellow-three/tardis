<?php

declare(strict_types=1);

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
        $table->string('caption')->nullable();
        $table->text('description')->nullable();
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

function editScreenMedia(string $path = 'media/photo.jpg'): Media
{
    return Media::factory()->create([
        'name' => basename($path),
        'original_name' => basename($path),
        'path' => $path,
        'disk' => 'public',
        'collection' => 'media',
    ]);
}

test('save updates the media row with the validated attributes', function () {
    $media = editScreenMedia();

    Livewire::test('tardis::pages.media-edit', ['id' => $media->id])
        ->set('name', 'renamed.jpg')
        ->set('original_name', 'holiday.jpg')
        ->set('alt_text', 'A beach at sunset')
        ->set('caption', 'Sunset')
        ->set('description', 'Taken on the last evening of the trip.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('media.name', 'renamed.jpg');

    expect($media->refresh())
        ->name->toBe('renamed.jpg')
        ->original_name->toBe('holiday.jpg')
        ->alt_text->toBe('A beach at sunset')
        ->caption->toBe('Sunset')
        ->description->toBe('Taken on the last evening of the trip.');
});

test('save rejects an empty name and leaves the row untouched', function () {
    $media = editScreenMedia();

    Livewire::test('tardis::pages.media-edit', ['id' => $media->id])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name');

    expect($media->refresh()->name)->toBe('photo.jpg');
});

test('delete removes the disk file and the row then returns to the browser', function () {
    Storage::disk('public')->put('media/photo.jpg', 'bytes');
    $media = editScreenMedia();

    Livewire::test('tardis::pages.media-edit', ['id' => $media->id])
        ->call('delete')
        ->assertRedirect(route('tardis.media'));

    // The row stores the full storage path, so the file must go with it —
    // a leftover orphan is exactly the bug this screen used to have.
    Storage::disk('public')->assertMissing('media/photo.jpg');
    expect(Media::query()->whereKey($media->id)->exists())->toBeFalse();
});

test('delete tolerates a row whose file is already gone', function () {
    $media = editScreenMedia('media/already-gone.jpg');

    Livewire::test('tardis::pages.media-edit', ['id' => $media->id])
        ->call('delete')
        ->assertRedirect(route('tardis.media'));

    expect(Media::query()->whereKey($media->id)->exists())->toBeFalse();
});

test('the edit screen pre-fills the current media attributes', function () {
    $media = editScreenMedia();
    $media->update([
        'alt_text' => 'Existing alt',
        'caption' => 'Existing caption',
        'description' => 'Existing description',
    ]);

    Livewire::test('tardis::pages.media-edit', ['id' => $media->id])
        ->assertSet('name', 'photo.jpg')
        ->assertSet('original_name', 'photo.jpg')
        ->assertSet('alt_text', 'Existing alt')
        ->assertSet('caption', 'Existing caption')
        ->assertSet('description', 'Existing description');
});
