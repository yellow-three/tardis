<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::livewire('/login', 'tardis::pages.login')->name('login');
    });

Route::middleware(['web', 'tardis.admin'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::livewire('/dashboard', 'tardis::pages.dashboard')->name('dashboard');

        Route::livewire('/media', 'tardis::pages.media-browser')->name('media');
        Route::livewire('/media/browse', 'tardis::pages.media-browser')->name('media.browse');
        Route::livewire('/activity-log', 'tardis::pages.activity-log')->name('activity.index');
        Route::livewire('/database', 'tardis::pages.database')->name('database.index');
        Route::livewire('/settings', 'tardis::pages.settings')->name('settings.index');

        Route::livewire('/ui-components', 'tardis::pages.ui-components')->name('ui-components');

        Route::livewire('/bread', 'tardis::pages.bread.manage')->name('bread.manage');

        Route::livewire('/{slug}/create', 'tardis::pages.bread.create')->name('bread.create');
        Route::livewire('/{slug}/{id}/read', 'tardis::pages.bread.read')->name('bread.read');
        Route::livewire('/{slug}/{id}/edit', 'tardis::pages.bread.edit')->name('bread.edit');
        Route::livewire('/{slug}', 'tardis::pages.bread.index')->name('bread.index');
    });
