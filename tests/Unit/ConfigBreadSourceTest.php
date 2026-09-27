<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\Sources\ConfigBreadSource;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/tardis-bread-'.uniqid();
});

afterEach(function () {
    File::deleteDirectory($this->path);
});

test('find returns null when the config file is missing', function () {
    $source = new ConfigBreadSource($this->path);

    expect($source->find('missing'))->toBeNull();
});

test('find loads a definition from a php config file', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/posts.php', <<<'PHP'
<?php

return [
    'slug' => 'posts',
    'model' => 'App\Models\Post',
    'name' => 'Post',
    'name_plural' => 'Posts',
    'fields' => [
        ['name' => 'title', 'type' => 'text'],
    ],
];
PHP);

    $bread = (new ConfigBreadSource($this->path))->find('posts');

    expect($bread)->toBeInstanceOf(BreadDefinition::class)
        ->and($bread->slug)->toBe('posts')
        ->and($bread->namePlural)->toBe('Posts');
});

test('find falls back to the file name when the slug key is missing', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/pages.php', <<<'PHP'
<?php

return [
    'model' => 'App\Models\Page',
    'name' => 'Page',
    'name_plural' => 'Pages',
    'fields' => [],
];
PHP);

    $bread = (new ConfigBreadSource($this->path))->find('pages');

    expect($bread->slug)->toBe('pages');
});

test('find throws when a config file does not return an array', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/broken.php', "<?php\n\nreturn 'nope';\n");

    (new ConfigBreadSource($this->path))->find('broken');
})->throws(UnexpectedValueException::class, 'must return an array');

test('find throws when a config file declares an unknown field type', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/broken.php', <<<'PHP'
<?php

return [
    'model' => 'App\Models\Broken',
    'name' => 'Broken',
    'name_plural' => 'Brokens',
    'fields' => [
        ['name' => 'body', 'type' => 'wysiwyg'],
    ],
];
PHP);

    (new ConfigBreadSource($this->path))->find('broken');
})->throws(InvalidArgumentException::class, 'Unsupported BREAD field type [wysiwyg].');

test('all returns definitions sorted by slug key', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/zebras.php', <<<'PHP'
<?php

return [
    'slug' => 'zebras',
    'model' => 'App\Models\Zebra',
    'name' => 'Zebra',
    'name_plural' => 'Zebras',
    'fields' => [],
];
PHP);
    File::put($this->path.'/apples.php', <<<'PHP'
<?php

return [
    'slug' => 'apples',
    'model' => 'App\Models\Apple',
    'name' => 'Apple',
    'name_plural' => 'Apples',
    'fields' => [],
];
PHP);

    $breads = (new ConfigBreadSource($this->path))->all();

    expect($breads->keys()->all())->toBe(['apples', 'zebras'])
        ->and($breads)->toHaveCount(2);
});

test('all returns an empty collection when the directory is missing', function () {
    expect((new ConfigBreadSource($this->path))->all())->toHaveCount(0);
});

test('save writes a php config file that can be read back', function () {
    $source = new ConfigBreadSource($this->path);

    $source->save([
        'slug' => 'posts',
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
        ],
    ]);

    expect(File::exists($this->path.'/posts.php'))->toBeTrue();

    $bread = $source->find('posts');

    expect($bread)->toBeInstanceOf(BreadDefinition::class)
        ->and($bread->namePlural)->toBe('Posts');
});

test('save throws when the slug is missing', function () {
    (new ConfigBreadSource($this->path))->save([
        'model' => 'App\Models\Post',
        'name' => 'Post',
        'name_plural' => 'Posts',
        'fields' => [],
    ]);
})->throws(InvalidArgumentException::class, 'requires a slug');

test('save validates field types', function () {
    (new ConfigBreadSource($this->path))->save([
        'slug' => 'broken',
        'model' => 'App\Models\Broken',
        'name' => 'Broken',
        'name_plural' => 'Brokens',
        'fields' => [
            ['name' => 'body', 'type' => 'wysiwyg'],
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Unsupported BREAD field type [wysiwyg].');
