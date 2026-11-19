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

test('a record is created, listed, edited and searched', function () {
    $page = signIn();

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

test('the sidebar leads to the demo BREADs', function () {
    signIn()
        ->assertSee('Posts')
        ->click('Posts')
        ->assertPathIs('/admin/posts')
        ->assertSee('Welcome to Tardis')
        ->assertNoJavaScriptErrors();
});
