<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;

class BreadBelongsToManyPostModel extends Model
{
    protected $table = 'bread_btm_posts';

    protected $guarded = [];

    public $timestamps = true;

    public function tags()
    {
        return $this->belongsToMany(
            BreadBelongsToManyTagModel::class,
            'bread_btm_post_tag',
            'post_id',
            'tag_id',
        );
    }
}

class BreadBelongsToManyTagModel extends Model
{
    use SoftDeletes;

    protected $table = 'bread_btm_tags';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::create('bread_btm_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('bread_btm_tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('bread_btm_post_tag', function (Blueprint $table) {
        $table->unsignedBigInteger('post_id');
        $table->unsignedBigInteger('tag_id');
        $table->primary(['post_id', 'tag_id']);
    });

    app()->instance(JsonBreadSource::class, new JsonBreadSource(__DIR__.'/../Fixtures/bread'));
});

/**
 * The pivot table is the source of truth for what was actually attached: the
 * read side applies the related model's own scope, so a row attached for a
 * soft-deleted or missing record stays invisible through tags() alone.
 */
function btmAttachedTagIds(int $postId): array
{
    return DB::table('bread_btm_post_tag')
        ->where('post_id', $postId)
        ->pluck('tag_id')
        ->all();
}

test('the create page does not attach an id that no related record has', function () {
    $tag = BreadBelongsToManyTagModel::create(['name' => 'laravel']);

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-belongs-to-many'])
        ->set('form.title', 'Btm post')
        ->set('form.tags', [$tag->id, 999999])
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadBelongsToManyPostModel::where('title', 'Btm post')->first();

    expect(btmAttachedTagIds($post->id))->toBe([$tag->id])
        ->and($post->tags()->pluck('bread_btm_tags.id')->all())->toBe([$tag->id]);
});

test('the create page does not attach a related record excluded by its own scope', function () {
    $kept = BreadBelongsToManyTagModel::create(['name' => 'kept']);
    $removed = BreadBelongsToManyTagModel::create(['name' => 'removed']);
    $removed->delete();

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-belongs-to-many'])
        ->set('form.title', 'Scoped post')
        ->set('form.tags', [$kept->id, $removed->id])
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadBelongsToManyPostModel::where('title', 'Scoped post')->first();

    expect(btmAttachedTagIds($post->id))->toBe([$kept->id]);
});

test('the edit page drops a submitted id that no related record has', function () {
    $kept = BreadBelongsToManyTagModel::create(['name' => 'kept']);

    $post = BreadBelongsToManyPostModel::create(['title' => 'Editable']);
    $post->tags()->attach([$kept->id]);

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-belongs-to-many', 'id' => $post->id])
        ->set('form.tags', [$kept->id, 999999])
        ->call('save')
        ->assertHasNoErrors();

    expect(btmAttachedTagIds($post->id))->toBe([$kept->id]);
});

test('the edit page drops a submitted id excluded by the related model scope', function () {
    $kept = BreadBelongsToManyTagModel::create(['name' => 'kept']);
    $removed = BreadBelongsToManyTagModel::create(['name' => 'removed']);
    $removed->delete();

    $post = BreadBelongsToManyPostModel::create(['title' => 'Editable scoped']);
    $post->tags()->attach([$kept->id]);

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-belongs-to-many', 'id' => $post->id])
        ->set('form.tags', [$kept->id, $removed->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(btmAttachedTagIds($post->id))->toBe([$kept->id]);
});

test('every submitted id that resolves is still attached', function () {
    $tagA = BreadBelongsToManyTagModel::create(['name' => 'a']);
    $tagB = BreadBelongsToManyTagModel::create(['name' => 'b']);
    $tagC = BreadBelongsToManyTagModel::create(['name' => 'c']);

    Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-belongs-to-many'])
        ->set('form.title', 'All valid')
        ->set('form.tags', [$tagA->id, $tagB->id, $tagC->id])
        ->call('save')
        ->assertHasNoErrors();

    $post = BreadBelongsToManyPostModel::where('title', 'All valid')->first();

    expect(btmAttachedTagIds($post->id))->toEqualCanonicalizing([$tagA->id, $tagB->id, $tagC->id]);
});

test('clearing the selection detaches every related record', function () {
    $tag = BreadBelongsToManyTagModel::create(['name' => 'a']);

    $post = BreadBelongsToManyPostModel::create(['title' => 'Clearable']);
    $post->tags()->attach([$tag->id]);

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-belongs-to-many', 'id' => $post->id])
        ->set('form.tags', [])
        ->call('save')
        ->assertHasNoErrors();

    expect(btmAttachedTagIds($post->id))->toBe([]);
});
