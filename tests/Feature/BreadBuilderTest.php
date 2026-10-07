<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Manager\FormfieldManager;

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

    // Keep every test hermetic: without this, a test that never saves would
    // still read whatever happens to sit in the real storage_path('tardis/bread').
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));
});

/** The field keys in the order the step 2 table actually renders them. */
function renderedFieldKeys(string $html): array
{
    preg_match_all('/wire:key="field-([^"]+)"/', $html, $matches);

    return $matches[1];
}

afterEach(function () {
    Schema::dropIfExists('bread_builder_test');
    File::deleteDirectory($this->breadPath);
});

test('detectFields normalizes detected types and pluralizes the model basename', function () {
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->assertHasNoErrors()
        ->assertSet('namePlural', 'Bread Builder Test Models')
        ->assertSet('fieldConfig.email.type', 'text')
        ->assertSet('fieldConfig.avatar.type', 'file')
        ->assertSet('fieldConfig.tags.type', 'simple_array')
        ->assertSet('step', 2);
});

test('detectFields produces only valid FieldType values', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields');

    foreach ($component->get('fieldConfig') as $field) {
        expect(app(FormfieldManager::class)->has($field['type']))->toBeTrue();
    }
});

test('builder saves a BREAD definition to the json source', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

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
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

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

test('goToStep owns the active tab of the fields and configure steps', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->call('goToStep', 3);

    expect($component->get('step'))->toBe(3)
        ->and($component->get('activeTab'))->toBe('general');

    $component->set('activeTab', 'read')->call('goToStep', 2);

    expect($component->get('step'))->toBe(2)
        ->and($component->get('activeTab'))->toBe('fields');
});

test('save persists the read layout and the field order', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'bread-builder-test')
        ->set('readLayout', ['name', 'email'])
        ->set('fieldOrder', ['body', 'tags', 'avatar', 'email', 'name'])
        ->call('save')
        ->assertHasNoErrors();

    $bread = app(BreadManager::class)->find('bread-builder-test');

    expect($bread->layout['read'])->toBe(['name', 'email'])
        ->and($bread->layout['field_order'])->toBe(['body', 'tags', 'avatar', 'email', 'name']);
});

test('save fills the read layout and field order that were never configured', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'bread-builder-test')
        ->set('readLayout', [])
        ->set('fieldOrder', [])
        ->call('save')
        ->assertHasNoErrors();

    $bread = app(BreadManager::class)->find('bread-builder-test');
    $expected = ['name', 'email', 'avatar', 'tags', 'body'];

    expect($bread->layout['read'])->toBe($expected)
        ->and($bread->layout['field_order'])->toBe($expected);
});

test('toggleReadField adds a field to the read layout and removes it again', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('readLayout', [])
        ->call('toggleReadField', 'name')
        ->call('toggleReadField', 'email');

    expect($component->get('readLayout'))->toBe(['name', 'email']);

    $component->call('toggleReadField', 'name');

    expect($component->get('readLayout'))->toBe(['email']);
});

test('moveReadField reorders the read layout and ignores out of range moves', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('readLayout', ['name', 'email', 'body']);

    $component->call('moveReadField', 'name', 1);

    expect($component->get('readLayout'))->toBe(['email', 'name', 'body']);

    $component->call('moveReadField', 'body', 99);

    expect($component->get('readLayout'))->toBe(['email', 'name', 'body']);
});

test('moveField reorders the field table without dropping fields', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->call('moveField', 'name', 1);

    $expected = ['email', 'name', 'avatar', 'tags', 'body'];

    expect($component->get('fieldOrder'))->toBe($expected);

    // A fresh component is what the browser receives on the next request, so
    // this asserts the order the table is genuinely rendered in.
    $next = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('fieldOrder', $expected);

    expect(renderedFieldKeys($next->html()))->toBe($expected);
});

test('orderedFieldKeys appends fields missing from a stale field order', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('fieldOrder', ['body', 'name']);

    expect($component->instance()->orderedFieldKeys)
        ->toBe(['body', 'name', 'email', 'avatar', 'tags']);
});

test('fieldSearch filters the field table by key, label and type', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields');

    $component->set('fieldSearch', 'email');
    expect($component->instance()->visibleFieldKeys)->toBe(['email']);

    $component->set('fieldSearch', 'file');
    expect($component->instance()->visibleFieldKeys)->toBe(['avatar']);

    $component->set('fieldSearch', 'no-such-field');
    expect($component->instance()->visibleFieldKeys)->toBe([]);

    $component->set('fieldSearch', '');
    expect($component->instance()->visibleFieldKeys)->toHaveCount(5);
});

test('toggleAllFields flips one flag across every field and ignores unknown flags', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->call('toggleAllFields', 'browse', false);

    $browse = array_values(array_unique(array_column($component->get('fieldConfig'), 'browse')));
    expect($browse)->toBe([false]);

    $component->call('toggleAllFields', 'not-a-flag', true);

    expect(array_values(array_unique(array_column($component->get('fieldConfig'), 'browse'))))->toBe([false]);
});

test('setFieldType ignores unknown types and unknown fields', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields');

    $before = $component->get('fieldConfig.name.type');

    $component->call('setFieldType', 'name', 'not-a-type');
    expect($component->get('fieldConfig.name.type'))->toBe($before);

    $component->call('setFieldType', 'ghost_field', 'text');
    expect($component->get('fieldConfig'))->not->toHaveKey('ghost_field');

    $component->call('setFieldType', 'name', 'file');
    expect($component->get('fieldConfig.name.type'))->toBe('file');
});

test('selectIcon sets the icon and closes the picker', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('showIconPicker', true)
        ->set('iconSearch', 'ta');

    $component->call('selectIcon', 'photo');

    expect($component->get('icon'))->toBe('photo')
        ->and($component->get('showIconPicker'))->toBeFalse()
        ->and($component->get('iconSearch'))->toBe('');
});

test('review step warns about an incomplete definition', function () {
    $component = Livewire::test('tardis::pages.bread-builder');

    expect(implode("\n", array_column($component->instance()->reviewWarnings, 'message')))
        ->toContain('Slug is empty');

    $component->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'bread-builder-test')
        ->set('readLayout', []);

    expect(implode("\n", array_column($component->instance()->reviewWarnings, 'message')))
        ->toContain('Read layout is empty');
});

test('review step raises no warnings for a complete definition', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'bread-builder-test');

    expect($component->instance()->reviewWarnings)->toBe([]);
});

test('review summary counts the fields that reach the generated views', function () {
    $component = Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'bread-builder-test')
        ->set('readLayout', ['name', 'email']);

    $summary = $component->instance()->reviewSummary;

    expect($summary['total_fields'])->toBe(5)
        ->and($summary['read_fields'])->toBe(['name', 'email'])
        ->and($summary['browse_fields'])->toHaveCount(5)
        ->and($summary['add_fields'])->toBe(5)
        ->and($summary['edit_fields'])->toBe(5);
});

test('the review step renders the summary of the staged definition', function () {
    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'bread-builder-test')
        ->call('goToStep', 4)
        ->assertSee('Summary')
        ->assertSee('bread-builder-test')
        ->assertDontSee('Before you save');
});

test('the review step renders advisories when the definition is incomplete', function () {
    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('readLayout', [])
        ->call('goToStep', 4)
        ->assertSee('Before you save')
        ->assertSee('Read layout is empty');
});

test('slug status distinguishes empty, invalid, available and taken slugs', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

    $component = Livewire::test('tardis::pages.bread-builder');
    expect($component->instance()->slugStatus)->toBe('empty');

    $component->set('slug', 'Not A Slug');
    expect($component->instance()->slugStatus)->toBe('invalid');

    $component->set('slug', 'brand-new-slug');
    expect($component->instance()->slugStatus)->toBe('available');

    $source->save([
        'slug' => 'brand-new-slug',
        'model' => BreadBuilderTestModel::class,
        'name' => 'Brand New',
        'name_plural' => 'Brand New Slugs',
        'fields' => [
            'name' => ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => false, 'browse' => true, 'read' => true, 'edit' => true, 'add' => true, 'validation' => []],
        ],
        'relationships' => [],
    ]);

    $component->set('slug', 'brand-new-slug');
    expect($component->instance()->slugStatus)->toBe('taken');
});

test('save coerces a field type that is not registered', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'bread-builder-test')
        // The type <select> binds straight to the config array, so this is the
        // exact path a hand-crafted Livewire payload would take.
        ->set('fieldConfig.avatar.type', 'not-a-real-type')
        ->call('save')
        ->assertHasNoErrors();

    $bread = app(BreadManager::class)->find('bread-builder-test');

    // Falls back to the type the reflector detected for that column (file) --
    // not to a blanket text -- and every persisted type stays on the enum.
    expect($bread->fields['avatar']['type'])->toBe('file');

    foreach ($bread->fields as $field) {
        expect(array_keys(app(FormfieldManager::class)->types()))->toContain($field['type']);
    }
});

test('save maps a legacy field type name onto the enum', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'bread-builder-test')
        // 'image' is not a registered type, but normalize() reads it as
        // file, so the staged intent survives instead of losing to the type the
        // reflector detected for body.
        ->set('fieldConfig.body.type', 'image')
        ->call('save')
        ->assertHasNoErrors();

    $bread = app(BreadManager::class)->find('bread-builder-test');

    expect($bread->fields['body']['type'])->toBe('file');
});

test('save refuses to silently overwrite another BREAD definition', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

    $source->save([
        'slug' => 'posts',
        'model' => BreadBuilderTestModel::class,
        'name' => 'Posts',
        'name_plural' => 'Posts',
        'fields' => [
            'name' => ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => false, 'browse' => true, 'read' => true, 'edit' => true, 'add' => true, 'validation' => []],
        ],
        'relationships' => [],
    ]);

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'posts')
        ->call('save')
        ->assertHasErrors('slug');

    expect(app(BreadManager::class)->find('posts')->name)->toBe('Posts');
});

test('edit mode may save over its own definition', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

    $source->save([
        'slug' => 'bread-builder-test',
        'model' => BreadBuilderTestModel::class,
        'name' => 'Bread Builder Test',
        'name_plural' => 'Bread Builder Test Models',
        'fields' => [
            'name' => ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => false, 'browse' => true, 'read' => true, 'edit' => true, 'add' => true, 'validation' => []],
        ],
        'relationships' => [],
        'layout' => [
            'read' => ['name'],
            'field_order' => ['name'],
        ],
    ]);

    Livewire::test('tardis::pages.bread-builder', ['slug' => 'bread-builder-test'])
        ->set('name', 'Renamed Test')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSessionHas('message');

    expect(app(BreadManager::class)->find('bread-builder-test')->name)->toBe('Renamed Test');
});

/**
 * Point app_path() at a throwaway tree holding real App\Models classes.
 *
 * The classes are require()d rather than autoloaded so the fixture does not
 * depend on composer's class map, and they must extend Model because
 * ModelReflector calls getTable()/getFillable() on them in edit mode.
 *
 * Returns a cleanup callback — call it in a finally block.
 */
function withModelFixtures(array $classes): Closure
{
    $original = app()->path();
    $appPath = sys_get_temp_dir().'/tardis-models-'.uniqid();

    File::makeDirectory($appPath.'/Models', 0755, true);
    app()->useAppPath($appPath);

    foreach ($classes as $class) {
        $file = $appPath."/Models/{$class}.php";
        File::put($file, "<?php\n\nnamespace App\\Models;\n\nclass {$class} extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
        require $file;
    }

    return function () use ($original, $appPath): void {
        app()->useAppPath($original);
        File::deleteDirectory($appPath);
    };
}

test('the model picker hides models that already have a BREAD', function () {
    $cleanup = withModelFixtures(['FreeModel', 'ClaimedModel']);

    try {
        app(BreadManager::class)->save([
            'slug' => 'claimed-bread',
            'model' => 'App\\Models\\ClaimedModel',
            'name' => 'Claimed',
            'name_plural' => 'Claimeds',
            'fields' => [],
            'relationships' => [],
        ]);

        $options = Livewire::test('tardis::pages.bread-builder')->instance()->getModelOptions();

        // A model that already drives a BREAD is edited through that BREAD;
        // offering it here would only create duplicates.
        expect($options)->toHaveKey('App\\Models\\FreeModel')
            ->and($options)->not->toHaveKey('App\\Models\\ClaimedModel');
    } finally {
        $cleanup();
    }
});

test('a BREAD stored without a namespace still claims its model', function () {
    $cleanup = withModelFixtures(['BareModel', 'OtherFreeModel']);

    try {
        app(BreadManager::class)->save([
            'slug' => 'bare-bread',
            'model' => 'BareModel',
            'name' => 'Bare',
            'name_plural' => 'Bares',
            'fields' => [],
            'relationships' => [],
        ]);

        $options = Livewire::test('tardis::pages.bread-builder')->instance()->getModelOptions();

        // Definitions persist a bare class name, so the matcher has to compare
        // basenames as well as fully qualified names.
        expect($options)->not->toHaveKey('App\\Models\\BareModel')
            ->and($options)->toHaveKey('App\\Models\\OtherFreeModel');
    } finally {
        $cleanup();
    }
});

test('editing a BREAD keeps its own model selectable', function () {
    $cleanup = withModelFixtures(['EditableModel']);

    try {
        app(BreadManager::class)->save([
            'slug' => 'editable-bread',
            'model' => 'App\\Models\\EditableModel',
            'name' => 'Editable',
            'name_plural' => 'Editables',
            'fields' => [],
            'relationships' => [],
        ]);

        $options = Livewire::test('tardis::pages.bread-builder', ['slug' => 'editable-bread'])
            ->instance()
            ->getModelOptions();

        // Without the edit-mode exception the model behind the open BREAD
        // disappears from the picker and the definition can never be re-saved.
        expect($options)->toHaveKey('App\\Models\\EditableModel');
    } finally {
        $cleanup();
    }
});

test('editing a definition keeps its components, policy and scope', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

    $source->save([
        'slug' => 'kept', 'model' => BreadBuilderTestModel::class, 'name' => 'Kept', 'name_plural' => 'Kepts',
        'components' => ['browse' => 'acme::pages.list'], 'policy' => 'articles', 'scope' => 'published',
        'fields' => [['name' => 'name', 'type' => 'text', 'browse' => true, 'read' => true, 'edit' => true, 'add' => true]],
    ]);

    Livewire::test('tardis::pages.bread-builder', ['slug' => 'kept'])
        ->set('name', 'Kept renamed')
        ->call('save')
        ->assertHasNoErrors();

    $bread = app(BreadManager::class)->find('kept');

    expect($bread->name)->toBe('Kept renamed')
        ->and($bread->components)->toBe(['browse' => 'acme::pages.list'])
        ->and($bread->policy)->toBe('articles')
        ->and($bread->scope)->toBe('published');
});

test('the builder refuses a slug reserved for a built-in screen', function () {
    $source = new JsonBreadSource($this->breadPath);
    app()->instance(JsonBreadSource::class, $source);

    Livewire::test('tardis::pages.bread-builder')
        ->set('model', BreadBuilderTestModel::class)
        ->call('detectFields')
        ->set('slug', 'settings')
        ->call('save')
        ->assertHasErrors('slug');

    expect($source->has('settings'))->toBeFalse();
});

/** A definition whose three translatable labels are locale maps. */
function saveLocaleMappedBread(string $path): void
{
    (new JsonBreadSource($path))->save([
        'slug' => 'bread-builder-test',
        'model' => BreadBuilderTestModel::class,
        'name' => ['en' => 'Post', 'tr' => 'Yazi'],
        'name_plural' => ['en' => 'Posts', 'tr' => 'Yazilar'],
        'description' => ['en' => 'About posts', 'tr' => 'Yazilar hakkinda'],
        'fields' => [
            'name' => ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => false, 'browse' => true, 'read' => true, 'edit' => true, 'add' => true, 'validation' => []],
        ],
        'relationships' => [],
    ]);
}

test('builder edit mode loads a locale map label and shows only the active locale', function () {
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));
    saveLocaleMappedBread($this->breadPath);

    // The inputs edit one locale at a time, so the map is what must survive
    // behind them — the form has no room to show all of it.
    Livewire::test('tardis::pages.bread-builder', ['slug' => 'bread-builder-test'])
        ->assertSet('nameTranslations', ['en' => 'Post', 'tr' => 'Yazi'])
        ->assertSet('name', 'Post')
        ->assertSet('namePluralTranslations', ['en' => 'Posts', 'tr' => 'Yazilar'])
        ->assertSet('namePlural', 'Posts')
        ->assertSet('descriptionTranslations', ['en' => 'About posts', 'tr' => 'Yazilar hakkinda'])
        ->assertSet('description', 'About posts');
});

test('saving an untouched locale map keeps every locale instead of flattening it', function () {
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));
    saveLocaleMappedBread($this->breadPath);

    Livewire::test('tardis::pages.bread-builder', ['slug' => 'bread-builder-test'])
        ->call('save')
        ->assertHasNoErrors();

    // Opening the builder and saving it again must not quietly turn every
    // locale into the single string the English tab happened to show.
    $bread = app(BreadManager::class)->find('bread-builder-test');

    expect($bread->name)->toBe(['en' => 'Post', 'tr' => 'Yazi'])
        ->and($bread->namePlural)->toBe(['en' => 'Posts', 'tr' => 'Yazilar'])
        ->and($bread->description)->toBe(['en' => 'About posts', 'tr' => 'Yazilar hakkinda']);
});

test('retyping a locale mapped label replaces the map with that one string', function () {
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadPath));
    saveLocaleMappedBread($this->breadPath);

    Livewire::test('tardis::pages.bread-builder', ['slug' => 'bread-builder-test'])
        ->set('name', 'Article')
        ->call('save')
        ->assertHasNoErrors();

    // A deliberate edit means the user wants exactly what the input showed, so
    // the untouched locales are dropped rather than kept behind a stale input.
    $bread = app(BreadManager::class)->find('bread-builder-test');

    expect($bread->name)->toBe('Article')
        ->and($bread->namePlural)->toBe(['en' => 'Posts', 'tr' => 'Yazilar'])
        ->and($bread->resolvedName())->toBe('Article');
});
