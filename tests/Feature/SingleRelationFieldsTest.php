<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Builder\RelationFieldFactory;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Formfields\Types\BelongsToField;
use Tardis\Formfields\Types\HasOneField;

class SingleRelCategory extends Model
{
    protected $table = 'rel_categories';

    public $timestamps = false;

    protected $guarded = [];

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('hidden', false);
    }
}

class SingleRelProfile extends Model
{
    protected $table = 'rel_profiles';

    public $timestamps = false;

    protected $guarded = [];
}

class SingleRelPost extends Model
{
    protected $table = 'rel_posts';

    public $timestamps = false;

    protected $fillable = ['title', 'category_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SingleRelCategory::class, 'category_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(SingleRelProfile::class, 'post_id');
    }
}

beforeEach(function () {
    Schema::create('rel_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('hidden')->default(false);
    });
    Schema::create('rel_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->foreignId('category_id')->nullable();
    });
    Schema::create('rel_profiles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('post_id')->nullable();
    });

    $this->news = SingleRelCategory::create(['name' => 'News']);
    $this->hiddenCategory = SingleRelCategory::create(['name' => 'Internal', 'hidden' => true]);

    $this->path = sys_get_temp_dir().'/tardis-single-rel-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->path));
});

afterEach(fn () => File::deleteDirectory($this->path));

function singleRelPostsBread(): void
{
    app(JsonBreadSource::class)->save([
        'slug' => 'rel-posts',
        'model' => SingleRelPost::class,
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'browse' => true, 'read' => true, 'edit' => true, 'add' => true],
            ['name' => 'category_id', 'type' => 'belongs_to', 'relation' => 'category', 'model' => SingleRelCategory::class, 'label_column' => 'name', 'browse' => true, 'read' => true, 'edit' => true, 'add' => true],
            ['name' => 'profile', 'type' => 'has_one', 'relation' => 'profile', 'model' => SingleRelProfile::class, 'label_column' => 'name', 'browse' => true, 'read' => true, 'edit' => true, 'add' => true],
        ],
    ]);
}

function singleRelCategoriesBread(): void
{
    app(JsonBreadSource::class)->save([
        'slug' => 'rel-categories',
        'model' => SingleRelCategory::class,
        'name' => 'Category',
        'name_plural' => 'Categories',
        'scope' => 'visible',
        'fields' => [['name' => 'name', 'type' => 'text']],
    ]);
}

function belongsToCategory(): BelongsToField
{
    return (new BelongsToField('category_id'))->model(SingleRelCategory::class)->labelColumn('name');
}

test('a belongs_to picker searches the related records by their label', function () {
    expect(belongsToCategory()->searchOptions('ne'))->toBe([$this->news->id => 'News'])
        ->and(belongsToCategory()->browse($this->news->id))->toBe('News');
});

test('a belongs_to picker follows the related BREAD scope, on search and on save', function () {
    singleRelCategoriesBread();

    $field = belongsToCategory();

    expect($field->searchOptions())->toBe([$this->news->id => 'News'])
        ->and($field->store($this->hiddenCategory->id))->toBeNull()
        ->and($field->store($this->news->id))->toBe($this->news->id)
        ->and($field->store(''))->toBeNull();
});

test('the create page stores the picked foreign key and links the has_one record', function () {
    singleRelPostsBread();
    $profile = SingleRelProfile::create(['name' => 'Ada']);

    Livewire::test('tardis::pages.bread.create', ['slug' => 'rel-posts'])
        ->assertSet('relationResults.category_id', [$this->news->id => 'News', $this->hiddenCategory->id => 'Internal'])
        ->set('form.title', 'Hello')
        ->set('form.category_id', (string) $this->news->id)
        ->set('form.profile', (string) $profile->id)
        ->call('save')
        ->assertHasNoErrors();

    $post = SingleRelPost::firstOrFail();

    expect((int) $post->category_id)->toBe($this->news->id)
        ->and((int) $profile->fresh()->post_id)->toBe($post->id);
});

test('editing a has_one moves the link and picking none unlinks', function () {
    singleRelPostsBread();
    $post = SingleRelPost::create(['title' => 'Hello']);
    $first = SingleRelProfile::create(['name' => 'Ada', 'post_id' => $post->id]);
    $second = SingleRelProfile::create(['name' => 'Bea']);

    $page = Livewire::test('tardis::pages.bread.edit', ['slug' => 'rel-posts', 'id' => $post->id])
        ->assertSet('form.profile', $first->id)
        ->set('form.profile', (string) $second->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($first->fresh()->post_id)->toBeNull()
        ->and((int) $second->fresh()->post_id)->toBe($post->id);

    Livewire::test('tardis::pages.bread.edit', ['slug' => 'rel-posts', 'id' => $post->id])
        ->set('form.profile', '')
        ->call('save');

    expect($second->fresh()->post_id)->toBeNull();
});

test('the listing shows the related label, not the key', function () {
    singleRelPostsBread();
    $post = SingleRelPost::create(['title' => 'Hello', 'category_id' => $this->news->id]);
    SingleRelProfile::create(['name' => 'Ada', 'post_id' => $post->id]);

    Livewire::test('tardis::pages.bread.index', ['slug' => 'rel-posts'])
        ->assertSee('News')
        ->assertSee('Ada');
});

test('the builder puts a detected relationship on the form', function () {
    $fields = ['category_id' => ['name' => 'category_id', 'type' => 'number', 'label' => 'Category']];

    $belongsTo = RelationFieldFactory::fromDetected('category', ['type' => 'belongsTo', 'model' => SingleRelCategory::class, 'foreign_key' => 'category_id'], $fields);
    $hasOne = RelationFieldFactory::fromDetected('profile', ['type' => 'hasOne', 'model' => SingleRelProfile::class], $fields);

    expect($belongsTo)->toMatchArray(['name' => 'category_id', 'type' => 'belongs_to', 'relation' => 'category', 'label' => 'Category', 'label_column' => 'name'])
        ->and($hasOne)->toMatchArray(['name' => 'profile', 'type' => 'has_one', 'relation' => 'profile', 'browse' => false])
        ->and(RelationFieldFactory::fromDetected('owner', ['type' => 'morphTo', 'model' => SingleRelCategory::class], $fields))->toBeNull()
        ->and(RelationFieldFactory::isOnForm('category', ['type' => 'belongsTo'], ['category_id' => $belongsTo]))->toBeTrue();
});

test('the builder relationships tab adds the field', function () {
    Livewire::test('tardis::pages.bread-builder')
        ->set('model', SingleRelPost::class)
        ->call('detectFields')
        ->call('addRelationField', 'category')
        ->assertSet('fieldConfig.category_id.type', 'belongs_to')
        ->assertSet('fieldConfig.category_id.model', SingleRelCategory::class)
        ->call('addRelationField', 'profile')
        ->assertSet('fieldConfig.profile.type', 'has_one');
});

test('a definition no longer carries a separate relationships list', function () {
    singleRelPostsBread();

    expect(app(BreadManager::class)->find('rel-posts')->toArray())->not->toHaveKey('relationships');
});

test('has_one shows the linked record on the detail page', function () {
    expect((new HasOneField('profile'))->labelColumn('name')->read(new SingleRelProfile(['name' => 'Ada'])))->toBe('Ada');
});
