<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\Sources\JsonBreadSource;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/tardis-bread-'.uniqid();
});

afterEach(function () {
    File::deleteDirectory($this->path);
});

test('find returns null when the json file is missing', function () {
    $source = new JsonBreadSource($this->path);

    expect($source->find('missing'))->toBeNull();
});

test('find loads a definition from a json file', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/posts.json', <<<'JSON'
{
    "slug": "posts",
    "model": "App\\Models\\Post",
    "name": "Post",
    "name_plural": "Posts",
    "fields": [
        {
            "name": "title",
            "type": "text"
        }
    ]
}
JSON);

    $bread = (new JsonBreadSource($this->path))->find('posts');

    expect($bread)->toBeInstanceOf(BreadDefinition::class)
        ->and($bread->slug)->toBe('posts')
        ->and($bread->namePlural)->toBe('Posts');
});

test('find falls back to the file name when the slug key is missing', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/pages.json', <<<'JSON'
{
    "model": "App\\Models\\Page",
    "name": "Page",
    "name_plural": "Pages",
    "fields": []
}
JSON);

    $bread = (new JsonBreadSource($this->path))->find('pages');

    expect($bread->slug)->toBe('pages');
});

test('find throws when a json file is not valid JSON', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/broken.json', 'this is not json');

    (new JsonBreadSource($this->path))->find('broken');
})->throws(UnexpectedValueException::class, 'does not contain valid JSON');

test('find throws when a json file declares an unknown field type', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/broken.json', <<<'JSON'
{
    "model": "App\\Models\\Broken",
    "name": "Broken",
    "name_plural": "Brokens",
    "fields": [
        {
            "name": "body",
            "type": "wysiwyg"
        }
    ]
}
JSON);

    (new JsonBreadSource($this->path))->find('broken');
})->throws(InvalidArgumentException::class, 'Unsupported BREAD field type [wysiwyg].');

test('all returns definitions sorted by slug key', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/zebras.json', <<<'JSON'
{
    "slug": "zebras",
    "model": "App\\Models\\Zebra",
    "name": "Zebra",
    "name_plural": "Zebras",
    "fields": []
}
JSON);
    File::put($this->path.'/apples.json', <<<'JSON'
{
    "slug": "apples",
    "model": "App\\Models\\Apple",
    "name": "Apple",
    "name_plural": "Apples",
    "fields": []
}
JSON);

    $breads = (new JsonBreadSource($this->path))->all();

    expect($breads->keys()->all())->toBe(['apples', 'zebras'])
        ->and($breads)->toHaveCount(2);
});

test('all ignores backup snapshot files', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/posts.json', <<<'JSON'
{
    "slug": "posts",
    "model": "App\\Models\\Post",
    "name": "Post",
    "name_plural": "Posts",
    "fields": []
}
JSON);
    File::put($this->path.'/posts.backup.2024-01-01@00-00-00.000000.json', '{}');

    $breads = (new JsonBreadSource($this->path))->all();

    expect($breads->keys()->all())->toBe(['posts'])
        ->and($breads)->toHaveCount(1);
});

test('all skips broken files and logs a warning', function () {
    Log::spy();

    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/posts.json', <<<'JSON'
{
    "slug": "posts",
    "model": "App\\Models\\Post",
    "name": "Post",
    "name_plural": "Posts",
    "fields": []
}
JSON);
    File::put($this->path.'/broken.json', 'not json');

    $breads = (new JsonBreadSource($this->path))->all();

    expect($breads->keys()->all())->toBe(['posts']);

    Log::shouldHaveReceived('warning')->once();
});

test('all returns an empty collection when the directory is missing', function () {
    expect((new JsonBreadSource($this->path))->all())->toHaveCount(0);
});

test('has returns true only when the definition exists', function () {
    $source = new JsonBreadSource($this->path);

    expect($source->has('posts'))->toBeFalse();

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [],
    ]);

    expect($source->has('posts'))->toBeTrue();
});

test('save writes a json file that can be read back', function () {
    $source = new JsonBreadSource($this->path);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
        ],
    ]);

    expect(File::exists($this->path.'/posts.json'))->toBeTrue();

    $bread = $source->find('posts');

    expect($bread)->toBeInstanceOf(BreadDefinition::class)
        ->and($bread->namePlural)->toBe('Posts');
});

test('save writes the file atomically', function () {
    $source = new JsonBreadSource($this->path);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [],
    ]);

    $leftovers = collect(File::files($this->path))
        ->filter(fn ($file) => str_starts_with($file->getFilename(), '.bread-'));

    expect($leftovers)->toHaveCount(0);
});

test('save throws when the slug is missing', function () {
    (new JsonBreadSource($this->path))->save([
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [],
    ]);
})->throws(InvalidArgumentException::class, 'requires a slug');

test('save validates field types', function () {
    (new JsonBreadSource($this->path))->save([
        'slug' => 'broken',
        'model' => 'App\Models\Broken',
        'name' => 'Broken',
        'name_plural' => 'Brokens',
        'fields' => [
            ['name' => 'body', 'type' => 'wysiwyg'],
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Unsupported BREAD field type [wysiwyg].');

test('save snapshots a backup before overwriting an existing definition', function () {
    $source = new JsonBreadSource($this->path);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [],
    ]);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
        ],
    ]);

    expect($source->backups('posts'))->toHaveCount(1);

    $bread = $source->find('posts');

    expect($bread->fields)->toHaveCount(1);
});

test('delete removes the definition and snapshots it first', function () {
    $source = new JsonBreadSource($this->path);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [],
    ]);

    expect($source->delete('posts'))->toBeTrue()
        ->and($source->has('posts'))->toBeFalse()
        ->and($source->backups('posts'))->toHaveCount(1);
});

test('delete returns false when the definition is missing', function () {
    expect((new JsonBreadSource($this->path))->delete('posts'))->toBeFalse();
});

test('backup returns null when there is no live file', function () {
    expect((new JsonBreadSource($this->path))->backup('posts'))->toBeNull();
});

test('backups lists snapshots newest first', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/posts.backup.2024-01-01@00-00-00.000000.json', '{}');
    File::put($this->path.'/posts.backup.2024-02-01@00-00-00.000000.json', '{}');

    $backups = (new JsonBreadSource($this->path))->backups('posts');

    expect($backups)->toHaveCount(2)
        ->and($backups->first()['name'])->toBe('posts.backup.2024-02-01@00-00-00.000000.json')
        ->and($backups->last()['date'])->toBe('2024-01-01@00-00-00.000000');
});

test('backups returns an empty collection when the directory is missing', function () {
    expect((new JsonBreadSource($this->path))->backups('posts'))->toHaveCount(0);
});

test('rollback restores a definition from a backup', function () {
    $source = new JsonBreadSource($this->path);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [],
    ]);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post V2',
        'name_plural' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
        ],
    ]);

    $backupName = $source->backups('posts')->first()['name'];

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post V3',
        'name_plural' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
            ['name' => 'body', 'type' => 'textarea'],
        ],
    ]);

    expect($source->rollback('posts', $backupName))->toBeTrue();

    $bread = $source->find('posts');

    expect($bread->name)->toBe('Post')
        ->and($bread->fields)->toHaveCount(0);
});

test('rollback snapshots the current state so the rollback itself is undoable', function () {
    $source = new JsonBreadSource($this->path);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [],
    ]);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post V2',
        'name_plural' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
        ],
    ]);

    $backupName = $source->backups('posts')->first()['name'];

    $source->rollback('posts', $backupName);

    expect($source->backups('posts'))->toHaveCount(2);
});

test('rollback throws when the backup name is not a valid backup of the slug', function () {
    (new JsonBreadSource($this->path))->rollback('posts', 'not-a-backup.json');
})->throws(InvalidArgumentException::class, 'Invalid backup name');

test('rollback returns false when the backup file is missing', function () {
    $source = new JsonBreadSource($this->path);

    expect($source->rollback('posts', 'posts.backup.2024-01-01@00-00-00.000000.json'))->toBeFalse();
});

test('prune removes the oldest backups keeping the configured newest count', function () {
    $source = new JsonBreadSource($this->path);

    foreach (['v1', 'v2', 'v3', 'v4'] as $name) {
        $source->save([
            'slug' => 'posts',
            'model' => 'App\Models\Post',
            'name' => $name,
            'name_plural' => 'Posts',
            'fields' => [],
        ]);
    }

    expect($source->backups('posts'))->toHaveCount(3);

    expect($source->prune('posts', 2))->toBe(1)
        ->and($source->backups('posts'))->toHaveCount(2);
});

test('save prunes backups to the configured retention', function () {
    config(['tardis.bread.backup_keep' => 2]);

    $source = new JsonBreadSource($this->path);

    foreach (['v1', 'v2', 'v3', 'v4'] as $name) {
        $source->save([
            'slug' => 'posts',
            'model' => 'App\Models\Post',
            'name' => $name,
            'name_plural' => 'Posts',
            'fields' => [],
        ]);
    }

    expect($source->backups('posts'))->toHaveCount(2);
});
