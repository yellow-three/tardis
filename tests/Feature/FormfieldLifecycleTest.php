<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Classes\Translation;

class BreadPageRelationPostModel extends Model
{
    protected $table = 'bread_page_relation_posts';

    protected $guarded = [];

    public $timestamps = true;

    public function tags()
    {
        return $this->belongsToMany(
            BreadPageRelationTagModel::class,
            'bread_page_relation_post_tag',
            'post_id',
            'tag_id',
        );
    }

    public function comments()
    {
        return $this->hasMany(BreadPageRelationCommentModel::class, 'bread_page_relation_post_id');
    }
}

class BreadPageRelationTagModel extends Model
{
    protected $table = 'bread_page_relation_tags';

    protected $guarded = [];

    public $timestamps = true;
}

class BreadPageRelationCommentModel extends Model
{
    protected $table = 'bread_page_relation_comments';

    protected $guarded = [];

    public $timestamps = true;
}

class BreadPageUploadPostModel extends Model
{
    protected $table = 'bread_page_upload_posts';

    protected $guarded = [];

    public $timestamps = true;
}

class BreadPageTranslatablePostModel extends Model
{
    protected $table = 'bread_page_translatable_posts';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = [
        'body' => 'array',
    ];
}

function createRelationSchema(): void
{
    Schema::create('bread_page_relation_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('bread_page_relation_tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('bread_page_relation_post_tag', function (Blueprint $table) {
        $table->unsignedBigInteger('post_id');
        $table->unsignedBigInteger('tag_id');
        $table->primary(['post_id', 'tag_id']);
    });

    Schema::create('bread_page_relation_comments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('bread_page_relation_post_id');
        $table->text('body');
        $table->timestamps();
    });
}

function createUploadSchema(): void
{
    Schema::create('bread_page_upload_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('avatar')->nullable();
        $table->string('secret')->nullable();
        $table->timestamps();
    });
}

function createTranslatableSchema(): void
{
    Schema::create('bread_page_translatable_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->json('body')->nullable();
        $table->timestamps();
    });
}

function bindRelationSource(): void
{
    app()->instance(JsonBreadSource::class, new JsonBreadSource(__DIR__.'/../Fixtures/bread'));
}

test('create page syncs belongs_to_many relations after creating the record', function () {
    createRelationSchema();

    $tagA = BreadPageRelationTagModel::create(['name' => 'laravel']);
    $tagB = BreadPageRelationTagModel::create(['name' => 'testing']);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-relations'])
        ->set('form.title', 'Relation post')
        ->set('form.tags', [$tagA->id, $tagB->id])
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadPageRelationPostModel::where('title', 'Relation post')->first();

    expect($post)->not->toBeNull()
        ->and($post->tags()->get()->modelKeys())->toBe([$tagA->id, $tagB->id]);
});

test('edit page prefills belongs_to_many ids and syncs the changed selection', function () {
    createRelationSchema();

    $tagA = BreadPageRelationTagModel::create(['name' => 'laravel']);
    $tagB = BreadPageRelationTagModel::create(['name' => 'testing']);
    $tagC = BreadPageRelationTagModel::create(['name' => 'livewire']);

    $post = BreadPageRelationPostModel::create(['title' => 'Editable']);
    $post->tags()->attach([$tagA->id, $tagB->id]);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-posts-relations', 'id' => $post->id])
        ->assertSet('form.tags', [$tagA->id, $tagB->id])
        ->set('form.tags', [$tagB->id, $tagC->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($post->fresh()->tags()->get()->modelKeys())->toBe([$tagB->id, $tagC->id]);
});

test('create page loads belongs_to_many search results on mount', function () {
    createRelationSchema();

    $tagA = BreadPageRelationTagModel::create(['name' => 'laravel']);
    $tagB = BreadPageRelationTagModel::create(['name' => 'testing']);
    $tagC = BreadPageRelationTagModel::create(['name' => 'livewire']);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-relations'])
        ->assertSet('relationSearch.tags', '')
        ->assertSet('relationResults.tags', [
            $tagA->id => 'laravel',
            $tagB->id => 'testing',
            $tagC->id => 'livewire',
        ]);
});

test('belongs_to_many search filters remote options by label', function () {
    createRelationSchema();

    $tagA = BreadPageRelationTagModel::create(['name' => 'laravel']);
    $tagB = BreadPageRelationTagModel::create(['name' => 'testing']);
    $tagC = BreadPageRelationTagModel::create(['name' => 'livewire']);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-relations'])
        ->set('relationSearch.tags', 'lar')
        ->assertSet('relationResults.tags', [$tagA->id => 'laravel']);
});

test('belongs_to_many search keeps selected options even when they do not match', function () {
    createRelationSchema();

    $tagA = BreadPageRelationTagModel::create(['name' => 'laravel']);
    $tagB = BreadPageRelationTagModel::create(['name' => 'testing']);
    $tagC = BreadPageRelationTagModel::create(['name' => 'livewire']);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-relations'])
        ->set('form.tags', [$tagC->id])
        ->set('relationSearch.tags', 'lar')
        ->assertSet('relationResults.tags', [
            $tagC->id => 'livewire',
            $tagA->id => 'laravel',
        ]);
});

test('edit page loads belongs_to_many search results with the current selection', function () {
    createRelationSchema();

    $tagA = BreadPageRelationTagModel::create(['name' => 'laravel']);
    $tagB = BreadPageRelationTagModel::create(['name' => 'testing']);
    $tagC = BreadPageRelationTagModel::create(['name' => 'livewire']);

    $post = BreadPageRelationPostModel::create(['title' => 'Editable']);
    $post->tags()->attach([$tagA->id, $tagC->id]);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-posts-relations', 'id' => $post->id])
        ->assertSet('form.tags', [$tagA->id, $tagC->id])
        ->assertSet('relationResults.tags', [
            $tagA->id => 'laravel',
            $tagC->id => 'livewire',
            $tagB->id => 'testing',
        ]);
});

test('create page persists has_many children via createMany', function () {
    createRelationSchema();

    bindRelationSource();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-relations'])
        ->set('form.title', 'With comments')
        ->set('form.comments', [
            ['body' => 'First'],
            ['body' => 'Second'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadPageRelationPostModel::where('title', 'With comments')->first();

    expect($post)->not->toBeNull()
        ->and($post->comments()->pluck('body')->all())->toBe(['First', 'Second']);
});

test('edit page updates, creates and deletes has_many children by id', function () {
    createRelationSchema();

    $post = BreadPageRelationPostModel::create(['title' => 'Editable comments']);
    $first = $post->comments()->create(['body' => 'First']);
    $second = $post->comments()->create(['body' => 'Second']);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-posts-relations', 'id' => $post->id])
        ->set('form.comments', [
            ['id' => $first->id, 'body' => 'First updated'],
            ['body' => 'Third'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($post->fresh()->comments()->pluck('body')->all())->toBe(['First updated', 'Third'])
        ->and(BreadPageRelationCommentModel::whereKey($second->id)->exists())->toBeFalse();
});

test('create page stores uploaded files to the configured disk and directory', function () {
    Storage::fake('public');
    createUploadSchema();

    bindRelationSource();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-uploads'])
        ->set('form.title', 'Upload post')
        ->set('form.avatar', UploadedFile::fake()->image('avatar.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadPageUploadPostModel::where('title', 'Upload post')->first();

    expect($post->avatar)->toStartWith('avatars/')
        ->and(Storage::disk('public')->exists($post->avatar))->toBeTrue();
});

test('create page hashes password values', function () {
    createUploadSchema();

    bindRelationSource();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-uploads'])
        ->set('form.title', 'Secret post')
        ->set('form.secret', 'plain-secret')
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadPageUploadPostModel::where('title', 'Secret post')->first();

    expect($post->secret)->not->toBe('plain-secret')
        ->and(Hash::check('plain-secret', $post->secret))->toBeTrue();
});

test('edit page keeps existing password hash when the field is left blank', function () {
    createUploadSchema();

    $post = BreadPageUploadPostModel::create([
        'title' => 'Secret',
        'secret' => Hash::make('original'),
    ]);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-posts-uploads', 'id' => $post->id])
        ->assertSet('form.secret', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('original', $post->fresh()->secret))->toBeTrue();
});

test('edit page accepts an existing stored path without file validation rules', function () {
    Storage::fake('public');
    createUploadSchema();

    Storage::disk('public')->put('avatars/existing.jpg', 'existing bytes');

    $post = BreadPageUploadPostModel::create([
        'title' => 'Edit avatar',
        'avatar' => 'avatars/existing.jpg',
    ]);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-posts-uploads', 'id' => $post->id])
        ->set('form.title', 'Edited avatar')
        ->call('save')
        ->assertHasNoErrors();

    expect($post->fresh()->avatar)->toBe('avatars/existing.jpg');
});

test('edit page replaces the stored file when a new file is uploaded', function () {
    Storage::fake('public');
    createUploadSchema();

    Storage::disk('public')->put('avatars/old.jpg', 'old bytes');

    $post = BreadPageUploadPostModel::create([
        'title' => 'Has avatar',
        'avatar' => 'avatars/old.jpg',
    ]);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-posts-uploads', 'id' => $post->id])
        ->set('form.avatar', UploadedFile::fake()->image('new-avatar.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $post->fresh();

    expect($fresh->avatar)->not->toBe('avatars/old.jpg')
        ->toStartWith('avatars/')
        ->and(Storage::disk('public')->exists($fresh->avatar))->toBeTrue();
});

test('bread definition keeps translatable flags and locales through the sources', function () {
    bindRelationSource();

    $definition = app(BreadManager::class)->find('bread-page-posts-translatable');

    expect($definition)->not->toBeNull();

    $body = collect($definition->toArray()['fields'])->firstWhere('name', 'body');

    expect($body['translatable'])->toBeTrue()
        ->and($body['locales'])->toBe(['en', 'tr']);
});

test('create page stores translatable values as a locale map', function () {
    createTranslatableSchema();
    bindRelationSource();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-translatable'])
        ->assertSet('form.body', ['en' => '', 'tr' => ''])
        ->set('form.title', 'Translated post')
        ->set('form.body.en', 'Hello')
        ->set('form.body.tr', 'Merhaba')
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadPageTranslatablePostModel::where('title', 'Translated post')->first();

    expect($post)->not->toBeNull()
        ->and($post->body)->toBe(['en' => 'Hello', 'tr' => 'Merhaba']);
});

test('create page stores null when every translatable value is empty', function () {
    createTranslatableSchema();
    bindRelationSource();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-posts-translatable'])
        ->set('form.title', 'Empty body')
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadPageTranslatablePostModel::where('title', 'Empty body')->first();

    expect($post->body)->toBeNull();
});

test('edit page prefills and persists translatable values', function () {
    createTranslatableSchema();

    $post = BreadPageTranslatablePostModel::create([
        'title' => 'Editable',
        'body' => ['en' => 'Hello', 'tr' => 'Merhaba'],
    ]);

    bindRelationSource();

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-posts-translatable', 'id' => $post->id])
        ->assertSet('form.body', ['en' => 'Hello', 'tr' => 'Merhaba'])
        ->set('form.body.en', 'Good morning')
        ->call('save')
        ->assertHasNoErrors();

    expect($post->fresh()->body)->toBe(['en' => 'Good morning', 'tr' => 'Merhaba']);
});

test('Translation helper normalizes raw values and falls back to the first non-empty locale', function () {
    expect(Translation::normalize(null, ['en', 'tr']))->toBe(['en' => '', 'tr' => ''])
        ->and(Translation::normalize(['en' => 'Hi'], ['en', 'tr']))->toBe(['en' => 'Hi', 'tr' => ''])
        ->and(Translation::normalize('{"en":"Hi","tr":"Selam"}', ['en', 'tr']))->toBe(['en' => 'Hi', 'tr' => 'Selam'])
        ->and(Translation::value(['en' => '', 'tr' => 'Selam'], ['en', 'tr'], 'en'))->toBe('Selam')
        ->and(Translation::value(['en' => 'Hi', 'tr' => 'Selam'], ['en', 'tr'], 'en'))->toBe('Hi');
});
