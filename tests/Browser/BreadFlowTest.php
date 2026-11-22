<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tardis\Demo\Models\DemoCategory;
use Tests\Browser\Support\BrowserUser;

beforeEach(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });

    config()->set('auth.providers.users.model', BrowserUser::class);
    $this->path = sys_get_temp_dir().'/tardis-browser-'.uniqid();
    config()->set('tardis.bread.path', $this->path);

    $this->artisan('migrate');
    $this->artisan('tardis:demo');
    addBreadRoutes();

    BrowserUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => Hash::make('correct horse')]);
});

afterEach(fn () => File::deleteDirectory($this->path));

function signIn(): mixed
{
    return visit('/admin/login')
        ->type('#email', 'ada@example.test')
        ->type('#password', 'correct horse')
        ->press(__('tardis::auth.log_in'))
        ->assertPathIs('/admin/dashboard');
}

/*
 * One test per file that signs in: the browser server keeps serving the
 * application of the first test in the file, so a second test that signs in
 * finds neither its user nor its session. Each flow is a step of one test.
 */
test('the sidebar leads to a demo BREAD, where a record is created and edited', function () {
    $page = signIn();

    // The sidebar starts collapsed to icons, so the link is found by where it
    // goes rather than by its (hidden) label.
    $page->navigate('/admin/dashboard')
        ->click('aside a[href$="/admin/posts"]')
        ->assertPathIs('/admin/posts')
        ->assertSee('Welcome to Tardis');

    $page->navigate('/admin/categories/create')
        ->type('#field_form_name', 'Recipes')
        ->type('#field_form_slug', 'recipes')
        ->press(__('tardis::bread.save'))
        ->assertPathIs('/admin/categories')
        ->assertSee('Recipes');

    $recipes = DemoCategory::where('slug', 'recipes')->firstOrFail();

    $page->navigate('/admin/categories/'.$recipes->id.'/edit')
        ->clear('#field_form_name')
        ->type('#field_form_name', 'Kitchen')
        ->press(__('tardis::bread.update'))
        ->assertPathIs('/admin/categories')
        ->assertSee('Kitchen')
        ->assertDontSee('Recipes')
        ->assertNoJavaScriptErrors();

    expect($recipes->fresh()->name)->toBe('Kitchen');
});
