<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadManager;
use Tardis\Bread\DefinitionHealth;
use Tardis\Diagnostics\DoctorReport;

class HealthArticle extends Model
{
    protected $table = 'health_articles';

    protected $fillable = ['title', 'body'];
}

beforeEach(function () {
    Schema::create('health_articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('body')->nullable();
    });

    $this->path = sys_get_temp_dir().'/tardis-health-'.uniqid();
    config()->set('tardis.bread.path', $this->path);
});

afterEach(fn () => File::deleteDirectory($this->path));

function healthDefinition(array $extra = []): array
{
    return $extra + [
        'slug' => 'articles',
        'model' => HealthArticle::class,
        'name' => 'Article',
        'name_plural' => 'Articles',
        'search_key' => 'title',
        'order_column' => 'position',
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
            ['name' => 'subtitle', 'type' => 'text'],
            ['name' => 'tags', 'type' => 'belongs_to_many', 'relation' => 'tags'],
            ['name' => 'author', 'type' => 'text', 'accessor' => 'author.name'],
            ['name' => 'excerpt', 'type' => 'text', 'add' => false, 'edit' => false],
        ],
    ];
}

test('fields and keys naming a missing column are found, relation and accessor fields are not', function () {
    expect(DefinitionHealth::missingColumns(healthDefinition()))->toBe(['subtitle', 'position'])
        ->and(DefinitionHealth::missingColumns(healthDefinition(['model' => 'App\\Models\\Missing'])))->toBe([]);
});

test('tardis:doctor warns about a field whose column is gone', function () {
    app(BreadManager::class)->save(healthDefinition());

    $check = collect(DoctorReport::run()->checks)->firstWhere('name', 'BREAD definitions');

    expect($check->isFailure())->toBeFalse()
        ->and($check->toArray()['status'])->toBe('warn')
        ->and($check->toArray()['message'])->toContain('articles: no column for subtitle, position');
});

test('a definition that matches its table is healthy', function () {
    app(BreadManager::class)->save(healthDefinition(['order_column' => null, 'fields' => [['name' => 'title', 'type' => 'text']]]));

    $check = collect(DoctorReport::run()->checks)->firstWhere('name', 'BREAD definitions');

    expect($check->toArray()['status'])->toBe('ok');
});

test('the builder review step warns before saving', function () {
    $warnings = Livewire::test('tardis::pages.bread-builder')
        ->set('model', HealthArticle::class)
        ->call('detectFields')
        ->set('fieldConfig.ghost', ['name' => 'ghost', 'type' => 'text', 'label' => 'Ghost', 'add' => true, 'edit' => true])
        ->instance()
        ->reviewWarnings();

    expect(implode("\n", array_column($warnings, 'message')))->toContain('ghost');
});
