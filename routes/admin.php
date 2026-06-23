<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'tardis.admin', 'verified'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::livewire('/plugins', 'plugins-index')->name('plugins.index');
        Route::livewire('/activity-log', 'activity-index')->name('activity.index');
        Route::livewire('/database', 'database-index')->name('database.index');

        Route::view('/ui-components', 'tardis::ui-components')->name('ui-components');
        Route::livewire('/settings', 'settings-index')->name('settings.index');

        Route::livewire('/{slug}/create', 'bread-form')->name('bread.create');
        Route::livewire('/{slug}/{id}/read', 'bread-read')->name('bread.read');
        Route::livewire('/{slug}/{id}/edit', 'bread-form')->name('bread.edit');
        Route::livewire('/{slug}', 'bread-table')->name('bread.index');
    });
