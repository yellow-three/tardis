<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Auth\Notifications\ResetPasswordNotification;
use Tardis\Bread\BreadManager;
use Tardis\Bread\Export\CsvExporter;
use Tardis\Bread\Export\Xlsx;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Media\MediaManager;
use Tardis\Media\SvgSanitizer;
use Tardis\Menu\MenuManager;
use Tardis\Plugins\PluginManager;

class SecurityOutputGatePlugin implements AuthorizationPlugin
{
    /** @var array<int, string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'security-output-gate';
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

class SecurityOutputGateUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

class SecurityOutputResetUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

class SecurityOutputExportPost extends Model
{
    protected $table = 'security_output_posts';

    public $timestamps = false;

    protected $guarded = [];
}

function securityOutputGateAllows(array $abilities): void
{
    SecurityOutputGatePlugin::$allowed = $abilities;

    $plugins = app(PluginManager::class);
    $plugins->register('security-output-gate', SecurityOutputGatePlugin::class);
    $plugins->enableByDefault('security-output-gate');
}

function securityOutputSchema(): void
{
    Schema::dropIfExists('tardis_media');
    Schema::create('tardis_media', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('original_name');
        $table->string('path');
        $table->string('disk');
        $table->string('mime_type')->nullable();
        $table->unsignedBigInteger('size')->default(0);
        $table->string('alt_text')->nullable();
        $table->string('collection')->default('default');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('security_output_posts');
    Schema::create('security_output_posts', function ($table): void {
        $table->id();
        $table->string('title');
    });

    Schema::dropIfExists('users');
    Schema::create('users', function ($table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    config()->set('tardis.media.disk', 'public');
    config()->set('tardis.media.path', 'media');

    Storage::fake('public');
}

beforeEach(function (): void {
    securityOutputSchema();
    SecurityOutputGatePlugin::$allowed = [];
});

afterEach(function (): void {
    Schema::dropIfExists('tardis_media');
    Schema::dropIfExists('security_output_posts');
});

// P7: a file name is a JS string literal in wire:click, so a quote breaks out.
test('a media file name cannot inject JavaScript into a wire expression', function (): void {
    $name = "x'+alert(1)+'.pdf";
    Storage::disk('public')->put('media/'.$name, 'bytes');

    $html = Livewire::test('tardis::pages.media-browser')->html();

    // The attribute must carry a JSON-escaped literal, never a break-out.
    expect($html)->not->toContain("showFileInfo('x'+alert(1)+'.pdf')")
        ->not->toContain("'+alert(1)+'")
        ->toContain("showFileInfo('x\\u0027+alert(1)+\\u0027.pdf')");
});

test('no Blade view interpolates a raw quoted value into a wire expression', function (): void {
    $files = (new Filesystem)->allFiles(__DIR__.'/../../resources/views');
    $unsafe = [];

    foreach ($files as $file) {
        $lines = explode("\n", (string) $file->getContents());
        foreach ($lines as $number => $line) {
            if (preg_match('/wire:[a-z.]*="[a-zA-Z]+\(\'\{\{/i', $line) === 1) {
                $unsafe[] = $file->getRelativePathname().':'.($number + 1);
            }
        }
    }

    expect($unsafe)->toBe([]);
});

test('an upload loses quotes and angle brackets, a rename with them is refused', function (): void {
    $manager = app(MediaManager::class);

    $media = $manager->upload(UploadedFile::fake()->createWithContent("x'+alert(1)+'.pdf", 'bytes'));
    $quoted = $manager->upload(UploadedFile::fake()->createWithContent('John\'s <photo>.pdf', 'bytes'));

    expect($media->name)->toBe('x-+alert(1)+-.pdf')
        ->and($quoted->name)->toBe('John-s -photo-.pdf');

    Storage::disk('public')->put('media/photo.jpg', 'bytes');

    expect(fn () => $manager->rename('photo.jpg', 'x".pdf'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $manager->rename('photo.jpg', 'x<y>.pdf'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $manager->rename('photo.jpg', 'x`y`.pdf'))->toThrow(InvalidArgumentException::class);

    Storage::disk('public')->assertExists('media/photo.jpg');
});

// P9: every public picker method that can list files needs browse media.
test('loadFiles is forbidden without the browse media ability and leaks no files', function (): void {
    securityOutputGateAllows([]);
    Storage::disk('public')->put('media/secret.jpg', 'bytes');

    $picker = Livewire::test('tardis::components.media-picker', ['model' => 'form.image'])
        ->call('loadFiles')
        ->assertForbidden();

    expect((array) $picker->get('files'))->toBe([])
        ->and(app(BreadAuthorization::class)->allowsAbility(Abilities::MEDIA_BROWSE))->toBeFalse();
});

// P4: styles can fetch external CSS and xml:base rewrites relative references.
test('the SVG sanitizer drops style elements and xml:base attributes', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
        .'<style>@import url(https://evil.test/x.css)</style>'
        .'<a xml:base="javascript:alert(1)" href="relative.svg"><rect width="1" height="1"/></a>'
        .'<rect xml:base="https://evil.test/" width="2" height="2"/></svg>';

    $clean = SvgSanitizer::clean($svg);

    expect($clean)->not->toContain('<style')
        ->not->toContain('@import')
        ->not->toContain('evil.test')
        ->not->toContain('xml:base')
        ->toContain('<rect');
});

// P3: a leading '=' must never become a live formula in a spreadsheet.
test('an XLSX export writes values as strings and neutralises formula prefixes', function (): void {
    SecurityOutputExportPost::create(['title' => '=HYPERLINK("http://evil.test")']);
    SecurityOutputExportPost::create(['title' => '+cmd|calc']);

    $columns = [['name' => 'title', 'label' => 'Title']];
    $exporter = new CsvExporter($columns);
    $query = SecurityOutputExportPost::query();

    $response = Xlsx::download($exporter, $query, 'posts.xlsx');
    $path = $response->getFile()->getPathname();

    $reader = new XlsxReader;
    $reader->open($path);
    $cells = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            /** @var Row $row */
            $cells[] = $row->getCellAtIndex(0);
        }

        break;
    }

    $reader->close();

    expect($cells[1])->toBeInstanceOf(StringCell::class)
        ->and($cells[1]->getValue())->toBe("'=HYPERLINK(\"http://evil.test\")")
        ->and($cells[2]->getValue())->toBe("'+cmd|calc");

    ob_start();
    $exporter->download($query, 'posts.csv')->sendContent();
    $csv = (string) ob_get_clean();

    $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));

    expect($lines[1][0])->toBe("'=HYPERLINK(\"http://evil.test\")")
        ->and($lines[2][0])->toBe("'+cmd|calc");
});

// P5: the reset token must not be sent to a host supplied by the request.
test('a reset link uses app.url even when the request has a forged Host', function (): void {
    config()->set('app.url', 'https://panel.test');

    $request = Request::create('http://evil.test/admin/forgot-password', 'POST');
    $this->app->instance('request', $request);
    $this->app['url']->setRequest($request);

    Notification::fake();
    $user = new SecurityOutputResetUser(['email' => 'ada@example.test']);
    $notification = new ResetPasswordNotification('reset-token');

    $url = $notification->toMail($user)->actionUrl;

    expect($url)->toStartWith('https://panel.test/')
        ->not->toContain('evil.test')
        ->toContain('/admin/reset-password/reset-token');
});

// P10: a broken icon in one definition must not take down the whole panel.
test('a BREAD with an unknown icon still renders the dashboard', function (): void {
    securityOutputGateAllows([Abilities::ACCESS, 'browse first', 'browse second']);
    $this->actingAs(SecurityOutputGateUser::create(['name' => 'Ada', 'email' => 'ada@example.test']));

    foreach ([['nope:missing', 'first'], ['foo"><script>', 'second']] as [$icon, $slug]) {
        app(BreadManager::class)->save([
            'slug' => $slug,
            'model' => SecurityOutputExportPost::class,
            'name' => 'Post',
            'name_plural' => ucfirst($slug),
            'icon' => $icon,
            'fields' => [],
        ]);
    }

    app()->forgetInstance(MenuManager::class);

    $response = $this->get(route('tardis.dashboard'));

    $response->assertOk();

    $items = app(MenuManager::class)->all();
    expect($items->firstWhere('title', 'First')->icon)->toBe('heroicon-o-table-cells')
        ->and($items->firstWhere('title', 'Second')->icon)->toBe('heroicon-o-table-cells');
});

test('valid BREAD icons still render as their components', function (): void {
    securityOutputGateAllows([Abilities::ACCESS, 'browse links']);
    $this->actingAs(SecurityOutputGateUser::create(['name' => 'Ada', 'email' => 'ada@example.test']));

    app(BreadManager::class)->save([
        'slug' => 'links',
        'model' => SecurityOutputExportPost::class,
        'name' => 'Link',
        'name_plural' => 'Links',
        'icon' => 'link',
        'fields' => [],
    ]);

    app()->forgetInstance(MenuManager::class);

    $this->get(route('tardis.dashboard'))->assertOk();

    $component = app(MenuManager::class)->all()->firstWhere('title', 'Links')->icon;

    expect($component)->toBe('heroicon-o-link');
    expect(Blade::getClassComponentAliases())->toHaveKey($component);
});
