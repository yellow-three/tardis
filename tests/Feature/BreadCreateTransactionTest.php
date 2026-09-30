<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;

class BreadCreateTransactionPostModel extends Model
{
    protected $table = 'bread_tx_posts';

    protected $guarded = [];

    public $timestamps = true;

    public function tags()
    {
        return $this->belongsToMany(
            BreadCreateTransactionTagModel::class,
            'bread_tx_post_tag',
            'post_id',
            'tag_id',
        );
    }

    /**
     * Points at a pivot whose schema Eloquent cannot satisfy: the extra NOT NULL
     * column has no default, so syncing it raises a constraint violation. That
     * is the shape of a real misconfiguration — a relation wired to a pivot that
     * was never migrated the way the field expects.
     */
    public function labels()
    {
        return $this->belongsToMany(
            BreadCreateTransactionTagModel::class,
            'bread_tx_post_label',
            'post_id',
            'tag_id',
        );
    }
}

class BreadCreateTransactionTagModel extends Model
{
    protected $table = 'bread_tx_tags';

    protected $guarded = [];

    public $timestamps = true;
}

beforeEach(function () {
    Schema::create('bread_tx_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('bread_tx_tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('bread_tx_post_tag', function (Blueprint $table) {
        $table->unsignedBigInteger('post_id');
        $table->unsignedBigInteger('tag_id');
        $table->primary(['post_id', 'tag_id']);
    });

    Schema::create('bread_tx_post_label', function (Blueprint $table) {
        $table->unsignedBigInteger('post_id');
        $table->unsignedBigInteger('tag_id');
        $table->string('context');
        $table->primary(['post_id', 'tag_id']);
    });

    app()->instance(JsonBreadSource::class, new JsonBreadSource(__DIR__.'/../Fixtures/bread'));
});

test('a relation that fails mid-create leaves no half-written record behind', function () {
    $tag = BreadCreateTransactionTagModel::create(['name' => 'laravel']);

    // Livewire rewraps the failure in a ViewException, so the exception type is
    // not the subject: what matters is that the create did not half-succeed.
    expect(fn () => Livewire::test('tardis::pages.bread.create', ['slug' => 'bread-page-create-transaction'])
        ->set('form.title', 'Doomed post')
        ->set('form.tags', [$tag->id])
        ->set('form.labels', [$tag->id])
        ->call('save'))->toThrow('bread_tx_post_label');

    // The parent row is inserted before any relation is written, and the first
    // relation syncs cleanly before the second one dies. Without a transaction
    // that leaves a committed post carrying a half-applied relation set.
    expect(BreadCreateTransactionPostModel::where('title', 'Doomed post')->exists())->toBeFalse()
        ->and(DB::table('bread_tx_post_tag')->count())->toBe(0);
});

test('a relation that fails mid-update leaves the record as it was', function () {
    $tag = BreadCreateTransactionTagModel::create(['name' => 'laravel']);

    $post = BreadCreateTransactionPostModel::create(['title' => 'Original title']);

    expect(fn () => Livewire::test('tardis::pages.bread.edit', ['slug' => 'bread-page-create-transaction', 'id' => $post->id])
        ->set('form.title', 'Corrupted title')
        ->set('form.tags', [$tag->id])
        ->set('form.labels', [$tag->id])
        ->call('save'))->toThrow('bread_tx_post_label');

    // The columns are written before the relations, so a relation dying
    // afterwards must not leave them committed.
    expect($post->fresh()->title)->toBe('Original title')
        ->and(DB::table('bread_tx_post_tag')->count())->toBe(0);
});
