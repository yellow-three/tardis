<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'tardis.admin', 'verified'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::livewire('/plugins', 'tardis::pages.admin.plugins')->name('plugins.index');

        Route::view('/ui-components', 'tardis::ui-components')->name('ui-components');

        Route::livewire('/{slug}/create', 'tardis::bread-form')->name('bread.create');
        Route::livewire('/{slug}/{id}/read', 'tardis::bread-read')->name('bread.read');
        Route::livewire('/{slug}/{id}/edit', 'tardis::bread-form')->name('bread.edit');
        Route::livewire('/{slug}', 'tardis::bread-table')->name('bread.index');
    });
