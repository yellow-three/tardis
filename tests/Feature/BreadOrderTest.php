<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Formfields\FormfieldManager;
use Tardis\Formfields\Types\DateTimeField;

class OrderedSlide extends Model
{
    protected $table = 'ordered_slides';

    public $timestamps = false;

    protected $guarded = [];

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('live', true);
    }
}

beforeEach(function () {
    Schema::create('ordered_slides', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->unsignedInteger('position')->default(0);
        $table->boolean('live')->default(true);
    });

    foreach (['Alpha' => 3, 'Beta' => 1, 'Gamma' => 2] as $title => $position) {
        OrderedSlide::create(['title' => $title, 'position' => $position]);
    }

    $this->hidden = OrderedSlide::create(['title' => 'Hidden', 'position' => 9, 'live' => false]);

    // Through config, not a bound instance: the source is bound per request,
    // so a Livewire update would otherwise read the default directory.
    $this->path = sys_get_temp_dir().'/tardis-order-'.uniqid();
    config()->set('tardis.bread.path', $this->path);
});

afterEach(fn () => File::deleteDirectory($this->path));

function orderedSlidesBread(array $extra = []): void
{
    app(JsonBreadSource::class)->save($extra + [
        'slug' => 'slides',
        'model' => OrderedSlide::class,
        'name' => 'Slide',
        'name_plural' => 'Slides',
        'order_column' => 'position',
        'order_display_column' => 'title',
        'scope' => 'live',
        'fields' => [['name' => 'title', 'type' => 'text']],
    ]);
}

function slideTitles(): array
{
    return OrderedSlide::query()->where('live', true)->orderBy('position')->pluck('title')->all();
}

test('the order page lists the records by their order column', function () {
    orderedSlidesBread();

    $rows = Livewire::test('tardis::pages.bread.order', ['slug' => 'slides'])->instance()->rows();

    expect(array_column($rows, 'label'))->toBe(['Beta', 'Gamma', 'Alpha']);
});

test('reordering writes positions 1..n, ignoring ids outside the scope', function () {
    orderedSlidesBread();
    $ids = OrderedSlide::where('live', true)->orderBy('title')->pluck('id')->all(); // Alpha, Beta, Gamma

    Livewire::test('tardis::pages.bread.order', ['slug' => 'slides'])
        ->call('saveOrder', [...array_reverse($ids), $this->hidden->id]);

    expect(slideTitles())->toBe(['Gamma', 'Beta', 'Alpha'])
        ->and(OrderedSlide::where('live', true)->orderBy('position')->pluck('position')->all())->toBe([1, 2, 3])
        ->and($this->hidden->fresh()->position)->toBe(9);
});

test('a record moves up and down', function () {
    orderedSlidesBread();
    $alpha = OrderedSlide::where('title', 'Alpha')->value('id');

    Livewire::test('tardis::pages.bread.order', ['slug' => 'slides'])->call('move', $alpha, -1);

    expect(slideTitles())->toBe(['Beta', 'Alpha', 'Gamma']);
});

test('a BREAD without an order column has no order page', function () {
    orderedSlidesBread(['order_column' => null]);

    Livewire::test('tardis::pages.bread.order', ['slug' => 'slides'])->assertNotFound();
});

test('the order route does not swallow a record called order, and is linked from the list', function () {
    orderedSlidesBread();
    // Rebuilds the route table from routes/admin.php, which drops Livewire's
    // own update route, so it stays out of the tests that call actions.
    reloadAdminRoutes();

    expect(route('tardis.bread.order', ['slug' => 'slides']))->toEndWith('/admin/slides/order')
        ->and(app('router')->getRoutes()->match(request()->create('/admin/slides/order'))->getName())->toBe('tardis.bread.order')
        ->and(app('router')->getRoutes()->match(request()->create('/admin/slides/5'))->getName())->toBe('tardis.bread.show');

    Livewire::test('tardis::pages.bread.index', ['slug' => 'slides'])
        ->assertSee(route('tardis.bread.order', ['slug' => 'slides']), false);
});

test('field definitions accept Voyager style options', function () {
    $fields = app(FormfieldManager::class)->fields([
        ['name' => 'published_at', 'type' => app(FormfieldManager::class)->normalize('timestamp'), 'description' => 'When it goes live', 'default' => ''],
        ['name' => 'title', 'type' => 'text', 'help' => 'Short', 'description' => 'ignored', 'default' => 'Untitled', 'placeholder' => ''],
    ]);

    expect($fields[0])->toBeInstanceOf(DateTimeField::class)
        ->and($fields[0]->helpText)->toBe('When it goes live')
        ->and($fields[0]->default)->toBeNull()
        ->and($fields[1]->helpText)->toBe('Short')
        ->and($fields[1]->default)->toBe('Untitled')
        ->and($fields[1]->placeholder)->toBeNull();
});
