<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Dashboard\ChartSvg;
use Tardis\Dashboard\ChartWidget;
use Tardis\Facades\Tardis;
use Tardis\Models\PanelNotification;
use Tardis\Plugins\PluginManager;
use Tardis\Support\CommandPalette;
use Tardis\Support\Locales;

class ExperienceUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

class ImportFinished extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['tardis'];
    }

    public function toTardis(mixed $notifiable): array
    {
        return ['title' => 'Import finished', 'body' => '12 rows', 'url' => '/admin/posts', 'level' => 'success'];
    }
}

/** Lets the user into the panel and nothing else. */
class PaletteOnlyAccess implements AuthorizationPlugin
{
    public function name(): string
    {
        return 'palette-authz';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return $ability === 'access admin';
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        abort_unless($this->can($ability, $model), 403);
    }
}

class SalesChart extends ChartWidget
{
    public function __construct(private string $kind = 'bar')
    {
        parent::__construct('Sales <b>');
    }

    public function type(): string
    {
        return $this->kind;
    }

    public function labels(): array
    {
        return ['Mon', '<script>x</script>', 'Wed'];
    }

    public function datasets(): array
    {
        return [['label' => 'Orders', 'data' => [3, 7, 4]], ['label' => 'Returns', 'data' => [1, 0, 2]]];
    }
}

beforeEach(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
    $this->artisan('migrate');
    config()->set('auth.providers.users.model', ExperienceUser::class);
    $this->ada = ExperienceUser::create(['name' => 'Ada']);
    $this->bob = ExperienceUser::create(['name' => 'Bob']);
});

test('the command palette lists the pages the user may open', function () {
    $this->actingAs($this->ada);

    $entries = app(CommandPalette::class)->entries();
    $urls = array_column($entries, 'url');

    expect($urls)->toContain(route('tardis.dashboard'))
        ->and(count($urls))->toBe(count(array_unique($urls)));

    $this->get(route('tardis.dashboard'))->assertOk()
        ->assertSee('data-palette-entries', false)
        ->assertSee(__('tardis::shell.palette.title'));
});

test('the command palette leaves out screens the user may not open', function () {
    $this->actingAs($this->ada);
    $open = array_column(app(CommandPalette::class)->entries(), 'url');

    $plugins = app(PluginManager::class);
    $plugins->register('palette-authz', PaletteOnlyAccess::class);
    $plugins->enable('palette-authz');
    $urls = array_column(app(CommandPalette::class)->entries(), 'url');

    expect($open)->toContain(route('tardis.users.index'))
        ->and($urls)->not->toContain(route('tardis.users.index'));
});

test('notifications reach the bell, are counted and marked read only by their owner', function () {
    expect(Tardis::notify($this->ada, 'Backup done', 'All good', '/admin/system'))->toBe(1);
    Tardis::notify([$this->ada->id, $this->bob], 'Maintenance tonight', level: 'warning');

    $this->actingAs($this->ada);

    Livewire::test('tardis::components.notifications')
        ->assertSee('Backup done')
        ->assertSee('Maintenance tonight')
        ->assertSee(__('tardis::shell.notification_center.unread', ['count' => 2]));

    $bobs = PanelNotification::query()->where('user_id', $this->bob->id)->first();

    Livewire::test('tardis::components.notifications')->call('open', $bobs->id);
    expect($bobs->refresh()->read_at)->toBeNull();

    $backup = PanelNotification::query()->where('title', 'Backup done')->first();
    Livewire::test('tardis::components.notifications')->call('open', $backup->id)->assertRedirect('/admin/system');
    expect($backup->refresh()->read_at)->not->toBeNull();

    Livewire::test('tardis::components.notifications')->call('markAllRead');
    expect(PanelNotification::query()->where('user_id', $this->ada->id)->whereNull('read_at')->count())->toBe(0)
        ->and(PanelNotification::query()->where('user_id', $this->bob->id)->whereNull('read_at')->count())->toBe(1);
});

test('a link that would leave the site through // or javascript: is dropped', function () {
    Tardis::notify($this->ada, 'A', url: '//evil.test/x');
    Tardis::notify($this->ada, 'B', url: 'javascript:alert(1)');

    expect(PanelNotification::query()->pluck('url')->filter()->all())->toBe([]);
});

test('a Laravel notification can use the tardis channel', function () {
    $this->ada->notify(new ImportFinished);

    expect(PanelNotification::query()->where('user_id', $this->ada->id)->first())
        ->title->toBe('Import finished')
        ->level->toBe('success')
        ->url->toBe('/admin/posts');
});

test('a chart widget is drawn as escaped SVG for each type', function (string $type) {
    $svg = ChartSvg::render(new SalesChart($type));

    expect($svg)->toStartWith('<svg')
        ->toContain('aria-label="Sales &lt;b&gt;"')
        ->not->toContain('<script>');
})->with(['bar', 'line', 'doughnut']);

test('a chart without data says so on the dashboard', function () {
    $chart = new class('Empty') extends ChartWidget
    {
        public function type(): string
        {
            return 'line';
        }

        public function labels(): array
        {
            return [];
        }

        public function datasets(): array
        {
            return [];
        }
    };

    expect(ChartSvg::render($chart))->toBe('')
        ->and(view('tardis::widgets.chart', ['chart' => $chart])->render())->toContain(__('tardis::dashboard.chart_no_data'));
});

test('the dashboard shows the built-in activity chart', function () {
    $this->actingAs($this->ada);

    $this->get(route('tardis.dashboard'))->assertOk()->assertSee(__('tardis::dashboard.activity_chart'));
});

test('Arabic pages are written right to left', function () {
    expect(Locales::direction('ar'))->toBe('rtl')
        ->and(Locales::direction('en'))->toBe('ltr')
        ->and(Locales::available())->toContain('de', 'fr', 'es', 'ar');

    $this->actingAs($this->ada);
    app()->setLocale('ar');
    config()->set('app.locale', 'ar');

    $this->get(route('tardis.dashboard'))->assertOk()->assertSee('dir="rtl"', false);
});
