<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadManager;
use Tardis\Bread\FieldType;
use Tardis\Bread\Sources\ConfigBreadSource;

class BreadBuilderTestModel extends Model
{
    protected $table = 'bread_builder_test';

    protected $fillable = ['name', 'email', 'avatar', 'tags', 'body'];

    public $timestamps = true;

    protected $casts = [
        'tags' => 'array',
    ];
}

beforeEach(function () {
    Schema::create('bread_builder_test', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('avatar')->nullable();
        $table->json('tags')->nullable();
        $table->text('body')->nullable();
        $table->timestamps();
    });

    $this->breadPath = sys_get_temp_dir().'/tardis-bread-builder-'.uniqid();
});

afterEach(function () {
    Schema::dropIfExists('bread_builder_test');
    File::deleteDirectory($this->breadPath);
});

test('detectFields normalizes detected types and pluralizes the model basename', function () {
    app()->instance(ConfigBreadSource::class, new ConfigBreadSource($this->breadPath));

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->assertHasNoErrors()
        ->assertSet('namePlural', 'Bread Builder Test Models')
        ->assertSet('fieldConfig.email.type', 'text')
        ->assertSet('fieldConfig.avatar.type', 'file')
        ->assertSet('fieldConfig.tags.type', 'tags')
        ->assertSet('step', 2);
});

test('detectFields produces only valid FieldType values', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields');

    foreach ($component->get('fieldConfig') as $field) {
        expect(FieldType::tryFrom($field['type']))->not->toBeNull();
    }
});

test('builder saves a BREAD definition to the config source', function () {
    $source = new ConfigBreadSource($this->breadPath);
    app()->instance(ConfigBreadSource::class, $source);

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'bread-builder-test')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSessionHas('message');

    $bread = app(BreadManager::class)->find('bread-builder-test');

    expect($bread)->not->toBeNull()
        ->and($bread->model)->toBe(BreadBuilderTestModel::class)
        ->and($bread->namePlural)->toBe('Bread Builder Test Models')
        ->and($bread->fields['avatar']['type'])->toBe('file');
});

test('builder edit mode hydrates the full definition', function () {
    $source = new ConfigBreadSource($this->breadPath);
    app()->instance(ConfigBreadSource::class, $source);

    $source->save([
        'slug' => 'bread-builder-test',
        'model' => BreadBuilderTestModel::class,
        'name' => 'Bread Builder Test',
        'name_plural' => 'Bread Builder Test Models',
        'fields' => [
            'name' => ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => false, 'browse' => true, 'read' => true, 'edit' => true, 'add' => true, 'validation' => []],
        ],
        'relationships' => [],
        'soft_delete' => true,
        'layout' => [
            'browse' => ['name' => ['visible' => true, 'sortable' => true, 'searchable' => false]],
            'edit' => [['name' => 'Main', 'fields' => ['name']]],
        ],
    ]);

    Livewire::test('tardis::pages.bread-builder', ['slug' => 'bread-builder-test'])
        ->assertSet('editMode', true)
        ->assertSet('softDelete', true)
        ->assertSet('step', 3)
        ->assertSet('activeTab', 'general')
        ->assertSet('browseColumns.name.visible', true)
        ->assertSet('editTabs.0.name', 'Main');
});

test('goToStep resets the active tab when entering the configure step', function () {
    Livewire::test('tardis::pages.bread-builder')
        ->call('goToStep', 3)
        ->assertSet('activeTab', 'general');
});

test('step 1 model select uses a live change binding so the next button enables on selection', function () {
    // Livewire v4 defaults wire:model to a deferred update. Without .live the
    // model is only sent to the server on the next action, so the server-side
    // `empty($model)` check would keep the Next button disabled forever.
    Livewire::test('tardis::pages.bread-builder')
        ->assertSee('Step 1: Select Model')
        ->assertSeeHtml('wire:model.change.live="model"');
});

test('step 1 next button is disabled until a model is selected', function () {
    $button = '<button wire:click="detectFields" class="btn btn-primary" disabled';

    Livewire::test('tardis::pages.bread-builder')
        ->assertSeeHtml($button)
        ->set('model', BreadBuilderTestModel::class)
        ->assertDontSeeHtml($button);
});
