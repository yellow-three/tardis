<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\Legacy\LegacyConfigReader;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/tardis-bread-'.uniqid();
});

afterEach(function () {
    File::deleteDirectory($this->path);
});

test('find returns null when the config file is missing', function () {
    $source = new LegacyConfigReader($this->path);

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

    $bread = (new LegacyConfigReader($this->path))->find('posts');

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

    $bread = (new LegacyConfigReader($this->path))->find('pages');

    expect($bread->slug)->toBe('pages');
});

test('find throws when a config file does not return an array', function () {
    File::ensureDirectoryExists($this->path);
    File::put($this->path.'/broken.php', "<?php\n\nreturn 'nope';\n");

    (new LegacyConfigReader($this->path))->find('broken');
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

    (new LegacyConfigReader($this->path))->find('broken');
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

    $breads = (new LegacyConfigReader($this->path))->all();

    expect($breads->keys()->all())->toBe(['apples', 'zebras'])
        ->and($breads)->toHaveCount(2);
});

test('all returns an empty collection when the directory is missing', function () {
    expect((new LegacyConfigReader($this->path))->all())->toHaveCount(0);
});
