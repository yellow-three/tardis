<?php

declare(strict_types=1);

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tardis\Classes\MenuItem;
use Tardis\Manager\MenuManager;
use Tardis\Manager\PluginManager;

/**
 * Render guard for the admin sidebar shell.
 *
 * The 21-page guard renders Livewire page components, which emit page content
 * only and never embed the admin layout, so nothing covered this component.
 */
class SidebarGuardUser extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function (): void {
    $this->artisan('migrate')->assertSuccessful();

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->timestamps();
    });

    $this->actingAs(SidebarGuardUser::create([
        'name' => 'Sidebar Guard',
        'email' => 'guard@example.test',
    ]));
});

function tardisRenderSidebar(): string
{
    return Blade::render('<x-tardis::admin-sidebar />');
}

test('the admin sidebar renders without error', function (): void {
    expect(tardisRenderSidebar())
        ->toContain('TARDIS')
        ->toContain('aria-label="Ana gezinme"');
});

test('the account footer exposes a CSRF-protected logout form', function (): void {
    // The footer used to be a static avatar with no action, so signing out was
    // unreachable from the sidebar.
    expect(tardisRenderSidebar())
        ->toContain('Logout')
        ->toContain('name="_token"')
        ->toContain('name="_method" value="POST"');
});

test('the sidebar keeps entries whose route is registered and drops the rest', function (): void {
    $menu = app(MenuManager::class);
    $menu->collectFromPlugins(app(PluginManager::class));

    expect($menu->userMenu()->pluck('title')->all())->toContain('Profile');

    // Profile targets `profile.edit`, which no shipped route defines. Rendering it
    // calls route() and 500s the shell, so the footer drops it while keeping Logout.
    expect(tardisRenderSidebar())
        ->not->toContain('>Profile<')
        ->toContain('>Logout<');
});

test('an active menu item carries aria-current and the accent bar', function (): void {
    $item = (new MenuItem('Guard Active', 'heroicon-o-home'))->route('guard.active');

    Route::get('/guard/active', fn () => Blade::render(
        '{!! $__env->make("tardis::partials.menu-item", ["item" => $item, "level" => 0])->render() !!}',
        ['item' => $item]
    ))->name('guard.active');

    $this->get('/guard/active')
        ->assertOk()
        ->assertSee('aria-current="page"', false)
        ->assertSee('bg-primary', false);
});

test('the sidebar does not emit a shadow-sm class', function (): void {
    expect(tardisRenderSidebar())->not->toContain('shadow-sm');
});

test('the sidebar avoids drawer helpers that are scoped to drawer-content', function (): void {
    // tardis-drawer-open-only / tardis-drawer-closed-only only match inside
    // .drawer-content. The header's toggle icons rely on that; the sidebar lives
    // in .drawer-side, so the same classes silently do nothing there and a
    // collapsed drawer keeps rendering elements sized for the expanded width.
    expect(tardisRenderSidebar())
        ->not->toContain('tardis-drawer-open-only')
        ->not->toContain('tardis-drawer-closed-only');
});

test('the collapsed drawer hides the full width menu search field', function (): void {
    expect(tardisRenderSidebar())
        ->toContain('Menüde ara')
        ->toContain('is-drawer-close:hidden');
});
