<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
    $this->artisan('migrate');

    BrowserUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => Hash::make('correct horse')]);
});

test('signing in opens the dashboard', function () {
    visit('/admin/login')
        ->assertSee(__('tardis::auth.log_in'))
        ->type('#email', 'ada@example.test')
        ->type('#password', 'correct horse')
        ->press(__('tardis::auth.log_in'))
        ->assertPathIs('/admin/dashboard')
        ->assertNoJavaScriptErrors();
});

test('a wrong password keeps the login page and says why', function () {
    visit('/admin/login')
        ->type('#email', 'ada@example.test')
        ->type('#password', 'wrong')
        ->press(__('tardis::auth.log_in'))
        ->assertPathIs('/admin/login')
        ->assertSee(__('auth.failed'));
});
