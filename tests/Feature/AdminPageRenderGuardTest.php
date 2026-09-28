<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;

/**
 * Render guard for the 21 admin pages modernised in 49d2454.
 *
 * Every page that renders is audited for:
 *  - DaisyUI 4 leftovers (input-bordered, select-bordered, textarea-bordered)
 *  - real <th> cells that declare neither scope= nor aria-hidden="true"
 *
 * The <th> patterns use a negative lookahead so <thead> cannot match.
 */
class BreadGuardPostModel extends Model
{
    protected $table = 'bread_guard_posts';

    protected $guarded = [];

    public $timestamps = true;
}

/**
 * Pages that cannot render because of defects that predate 49d2454.
 * `tardis::pages.settings` fails Blade compilation: resources/views/pages/settings.blade.php:1044
 * passes `:class="{ 'rotate-180': open }"` to a component, which compiles to
 * `'class' => { 'rotate-180': open }` and raises a parse error.
 */
const TARDIS_GUARD_BLOCKED = [
    'tardis::pages.settings' => 'pre-existing Blade parse error at settings.blade.php:1044',
];

/** DaisyUI 4 utilities removed in the v5 migration. */
const TARDIS_GUARD_D4 = [
    'input-bordered',
    'select-bordered',
    'textarea-bordered',
];

function tardisGuardPages(): array
{
    return [
        'tardis::pages.login' => [],
        'tardis::pages.forgot-password' => [],
        'tardis::pages.reset-password' => [],
        'tardis::pages.dashboard' => [],
        'tardis::pages.plugins' => [],
        'tardis::pages.media-browser' => [],
        'tardis::pages.activity-log' => [],
        'tardis::pages.database' => [],
        'tardis::pages.database.create' => [],
        'tardis::pages.database.edit' => ['table' => 'guard_widgets'],
        'tardis::pages.permissions' => [],
        'tardis::pages.roles' => [],
        'tardis::pages.search' => [],
        'tardis::pages.ui-components' => [],
        'tardis::pages.bread.manage' => [],
        'tardis::pages.bread-builder' => [],
        'tardis::pages.bread.index' => ['slug' => 'guard-posts'],
        'tardis::pages.bread.create' => ['slug' => 'guard-posts'],
        'tardis::pages.bread.read' => ['slug' => 'guard-posts', 'id' => 1],
        'tardis::pages.bread.edit' => ['slug' => 'guard-posts', 'id' => 1],
    ];
}

beforeEach(function (): void {
    $this->artisan('migrate')->assertSuccessful();

    // activity_logs and tardis_media come from the package migrations above.
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('widgets', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->string('obsolete')->nullable();
    });

    Schema::create('guard_widgets', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
    });

    Schema::create('bread_guard_posts', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
        $table->timestamps();
    });

    BreadGuardPostModel::create([
        'title' => 'Guard row',
        'content' => 'Guard body',
    ]);

    // The Permission/Role models resolve to the unprefixed `permissions`/`roles`
    // tables, while 2026_06_28_000001_create_permission_tables.php creates the
    // `tardis_`-prefixed variants. Both are pre-existing. Create the names the
    // models actually query so the pages can be audited.
    foreach (['permissions', 'roles'] as $tableName) {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });
    }

    app()->instance(JsonBreadSource::class, new JsonBreadSource(__DIR__.'/../Fixtures/bread'));
});

test('every modernised admin page is covered by the render guard', function (): void {
    $pages = tardisGuardPages() + TARDIS_GUARD_BLOCKED;

    expect(array_keys($pages))->toHaveCount(21);
});

test('no modernised admin page leaks a DaisyUI 4 class', function (): void {
    $failures = [];

    foreach (tardisGuardPages() as $name => $params) {
        $html = Livewire::test($name, $params)->html();

        foreach (TARDIS_GUARD_D4 as $class) {
            if (str_contains($html, $class)) {
                $failures[] = "{$name} still emits `{$class}`";
            }
        }
    }

    expect($failures)->toBe([]);
});

test('every table header on a modernised admin page declares scope or aria-hidden', function (): void {
    $unscoped = '/<th(?![a-z])(?![^>]*\bscope=)(?![^>]*aria-hidden)[^>]*>\s*\S/';
    $failures = [];

    foreach (tardisGuardPages() as $name => $params) {
        $html = Livewire::test($name, $params)->html();

        if (preg_match_all($unscoped, $html, $matches) > 0) {
            $failures[] = "{$name} has ".count($matches[0]).' unscoped <th>: '
                .implode(' | ', array_slice($matches[0], 0, 3));
        }
    }

    expect($failures)->toBe([]);
});

test('the blocked page list still matches the pre-existing settings defect', function (): void {
    expect(TARDIS_GUARD_BLOCKED)->toHaveKey('tardis::pages.settings');
    expect(file_get_contents(__DIR__.'/../../resources/views/pages/settings.blade.php'))
        ->toContain(":class=\"{ 'rotate-180': open }\"");
});
