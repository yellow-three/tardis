<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Formfields\Types\BelongsToManyField;
use Tardis\Manager\PluginManager;

class RelationScopeTag extends Model
{
    protected $table = 'picker_tags';

    public $timestamps = false;

    protected $guarded = [];

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('hidden', false);
    }
}

class RelationScopeGatePlugin implements AuthorizationPlugin
{
    /** @var array<int, string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'picker-gate';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return in_array($ability, static::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        $this->can($ability, $model) || abort(403);
    }
}

function relationScopeField(): BelongsToManyField
{
    $field = new BelongsToManyField('tags');
    $field->model(RelationScopeTag::class)->labelColumn('name');

    return $field;
}

beforeEach(function () {
    Schema::create('picker_tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('hidden')->default(false);
    });

    RelationScopeTag::create(['name' => 'Public']);
    RelationScopeTag::create(['name' => 'Restricted', 'hidden' => true]);

    $this->path = sys_get_temp_dir().'/tardis-picker-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->path));
});

afterEach(fn () => File::deleteDirectory($this->path));

function relationScopeBread(array $extra = []): void
{
    (new JsonBreadSource(app(JsonBreadSource::class)->path()))->save($extra + [
        'slug' => 'tags',
        'model' => RelationScopeTag::class,
        'name' => 'Tag',
        'name_plural' => 'Tags',
        'fields' => [],
        'relationships' => [],
    ]);
}

function relationScopeAuth(array $abilities): void
{
    RelationScopeGatePlugin::$allowed = $abilities;
    $plugins = app(PluginManager::class);
    $plugins->register('picker-gate', RelationScopeGatePlugin::class);
    $plugins->enableByDefault('picker-gate');
}

test('a model with no BREAD of its own is listed as before', function () {
    expect(array_values(relationScopeField()->searchOptions()))->toEqualCanonicalizing(['Public', 'Restricted']);
});

test('the scope of the related BREAD limits the options', function () {
    relationScopeBread(['scope' => 'visible']);

    expect(array_values(relationScopeField()->searchOptions()))->toBe(['Public']);
});

test('a user who may not browse the related BREAD gets no options at all', function () {
    relationScopeBread();
    relationScopeAuth([]);

    expect(relationScopeField()->searchOptions())->toBe([]);
});

test('a user who may browse it sees the options', function () {
    relationScopeBread();
    relationScopeAuth(['browse tags']);

    expect(array_values(relationScopeField()->searchOptions()))->toEqualCanonicalizing(['Public', 'Restricted']);
});

test('an already selected id does not bypass the scope', function () {
    relationScopeBread(['scope' => 'visible']);
    $restricted = RelationScopeTag::where('name', 'Restricted')->value('id');

    expect(relationScopeField()->searchOptions('', [$restricted]))->not->toContain('Restricted');
});
