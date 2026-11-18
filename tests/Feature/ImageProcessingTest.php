<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tardis\Bread\FieldValidationRules;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Formfields\Types\ImageField;
use Tardis\Media\ImageProcessor;
use Tardis\Media\MediaManager;
use Tardis\Models\Media;

beforeEach(function () {
    if (! app(ImageProcessor::class)->available()) {
        $this->markTestSkipped('The GD extension is not installed.');
    }

    Storage::fake('public');
});

function imageSize(string $bytes): array
{
    $info = getimagesizefromstring($bytes);

    return [$info[0], $info[1]];
}

test('an image is fitted inside the box and never enlarged', function () {
    $processor = app(ImageProcessor::class);
    $jpeg = UploadedFile::fake()->image('a.jpg', 800, 400)->getContent();

    expect(imageSize($processor->resize($jpeg, 'image/jpeg', 200, 200)))->toBe([200, 100])
        ->and(imageSize($processor->resize($jpeg, 'image/jpeg', 2000, null)))->toBe([800, 400])
        ->and(imageSize($processor->resize($jpeg, 'image/jpeg', 100, 100, 80, crop: true)))->toBe([100, 100]);
});

test('a format GD cannot read is left alone', function () {
    $processor = app(ImageProcessor::class);

    expect($processor->resize('<svg xmlns="http://www.w3.org/2000/svg"/>', 'image/svg+xml', 10, 10))->toBeNull()
        ->and($processor->resize('not an image', 'image/png', 10, 10))->toBeNull();
});

test('the image field stores a resized original and its thumbnails', function () {
    $field = new ImageField('photo');
    $field->configure([
        'resize' => ['width' => 400],
        'quality' => 80,
        'thumbnails' => [['name' => 'small', 'width' => 100, 'height' => 100, 'crop' => true], ['name' => '../bad', 'width' => 10]],
    ]);

    $path = $field->store(UploadedFile::fake()->image('photo.jpg', 1200, 600));
    $disk = Storage::disk('public');

    expect(imageSize($disk->get($path)))->toBe([400, 200])
        ->and($disk->exists(ImageField::thumbnailPath($path, 'small')))->toBeTrue()
        ->and(imageSize($disk->get(ImageField::thumbnailPath($path, 'small'))))->toBe([100, 100])
        ->and(collect($disk->allFiles())->filter(fn ($f) => str_contains($f, 'bad')))->toBeEmpty()
        ->and($field->thumbnailUrl($path, 'small'))->toEndWith(ImageField::thumbnailPath($path, 'small'))
        ->and($field->thumbnailUrl($path, 'missing'))->toEndWith($path);
});

test('the thumbnail path sits next to the original', function () {
    expect(ImageField::thumbnailPath('uploads/a/photo.jpg', 'small'))->toBe('uploads/a/photo-small.jpg')
        ->and(ImageField::thumbnailPath('photo', 'small'))->toBe('photo-small');
});

describe('media screen thumbnails', function () {
    beforeEach(function () {
        $this->artisan('migrate');
        config()->set('tardis.media.disk', 'public');
        config()->set('tardis.media.path', 'media');
        config()->set('tardis.media.thumbnail_size', ['width' => 120, 'height' => 120]);
        app()->forgetInstance(MediaManager::class);
    });

    test('an image upload gets a thumbnail outside the browsable folder', function () {
        $media = app(MediaManager::class)->upload(UploadedFile::fake()->image('cat.png', 600, 300));
        $disk = Storage::disk('public');

        expect($media->thumbnail_path)->toBe(MediaManager::THUMBNAIL_ROOT.'/'.$media->id.'.png')
            ->and(imageSize($disk->get($media->thumbnail_path)))->toBe([120, 60])
            ->and($media->thumbnail_url)->toEndWith($media->thumbnail_path)
            ->and(app(MediaManager::class)->listFiles()->pluck('name')->all())->toBe(['cat.png']);
    });

    test('deleting the file or the record removes its thumbnail', function () {
        $manager = app(MediaManager::class);
        $disk = Storage::disk('public');

        $first = $manager->upload(UploadedFile::fake()->image('a.jpg', 300, 300));
        $manager->deleteFile('a.jpg');
        expect($disk->exists($first->thumbnail_path))->toBeFalse();

        $second = $manager->upload(UploadedFile::fake()->image('b.jpg', 300, 300));
        Media::findOrFail($second->id)->delete();
        expect($disk->exists($second->thumbnail_path))->toBeFalse();
    });

    test('a non-image upload has no thumbnail', function () {
        $media = app(MediaManager::class)->upload(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'));

        expect($media->thumbnail_path)->toBeNull()
            ->and($media->thumbnail_url)->toBeNull();
    });

    test('uploads still work before the thumbnail migration has run', function () {
        Schema::table('tardis_media', fn (Blueprint $table) => $table->dropColumn('thumbnail_path'));
        app()->forgetInstance(MediaManager::class);

        $media = app(MediaManager::class)->upload(UploadedFile::fake()->image('c.jpg', 300, 300));

        expect($media->exists)->toBeTrue()
            ->and($media->thumbnail_url)->toEndWith('media/c.jpg');
    });
});

test('an uploaded image is validated as an image', function () {
    $rules = FieldValidationRules::field(['type' => 'image', 'mimes' => ['jpg']], UploadedFile::fake()->image('a.jpg'));

    expect($rules)->toContain('image', 'mimes:jpg')->not->toContain('file');
});

test('the edit form shows the current image', function () {
    Schema::create('image_probes', function (Blueprint $table) {
        $table->id();
        $table->string('photo')->nullable();
    });

    $path = sys_get_temp_dir().'/tardis-image-probe-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($path));
    app(JsonBreadSource::class)->save([
        'slug' => 'image-probes',
        'model' => ImageProbe::class,
        'name' => 'Probe',
        'name_plural' => 'Probes',
        'fields' => [['name' => 'photo', 'type' => 'image', 'add' => true, 'edit' => true, 'thumbnails' => [['name' => 'small', 'width' => 50]]]],
    ]);

    Storage::disk('public')->put('uploads/p.jpg', UploadedFile::fake()->image('p.jpg', 100, 100)->getContent());
    $probe = ImageProbe::create(['photo' => 'uploads/p.jpg']);

    try {
        Livewire\Livewire::test('tardis::pages.bread.edit', ['slug' => 'image-probes', 'id' => $probe->id])
            ->assertSee('uploads/p.jpg', false)
            ->assertSee('accept="image/*"', false);
    } finally {
        File::deleteDirectory($path);
    }
});

class ImageProbe extends Model
{
    protected $table = 'image_probes';

    public $timestamps = false;

    protected $guarded = [];
}
