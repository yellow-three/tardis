<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Tardis\Bread\ModelReflector;

class ReflectorTestPost extends Model
{
    protected $table = 'reflector_test_posts';

    protected $fillable = ['title'];

    public function comments()
    {
        return $this->hasMany(ReflectorTestComment::class, 'post_id');
    }

    public function unrelatedHelper(): string
    {
        return 'nope';
    }

    public static function staticBomb(): void
    {
        throw new RuntimeException('static method must never be invoked');
    }
}

class ReflectorTestComment extends Model
{
    protected $table = 'reflector_test_comments';

    protected $fillable = ['body'];
}

class ReflectorTestTrackedModel extends Model
{
    public bool $deleteInvoked = false;

    public function delete(): ?bool
    {
        $this->deleteInvoked = true;

        return null;
    }
}

beforeEach(function () {
    Model::setConnectionResolver(app('db'));
});

test('getRelationships still detects real relationship methods', function () {
    $relationships = ModelReflector::getRelationships(new ReflectorTestPost);

    expect(array_keys($relationships))->toBe(['comments'])
        ->and($relationships['comments']['type'])->toBe('hasMany');
});

test('getRelationships does not null the global Eloquent connection resolver', function () {
    $resolver = Model::getConnectionResolver();

    ModelReflector::getRelationships(new ReflectorTestPost);

    expect(Model::getConnectionResolver())->toBe($resolver)->not->toBeNull();
});

test('getRelationships never invokes Eloquent mutator methods overridden on the model', function () {
    $model = new ReflectorTestTrackedModel;

    ModelReflector::getRelationships($model);

    expect($model->deleteInvoked)->toBeFalse();
});

test('getRelationships never invokes static helper methods', function () {
    $relationships = ModelReflector::getRelationships(new ReflectorTestPost);

    expect($relationships)->not->toHaveKey('staticBomb');
});
