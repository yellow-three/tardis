<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::livewire('/login', 'tardis::pages.login')->name('login');
        Route::livewire('/forgot-password', 'tardis::pages.forgot-password')->name('password.request');
        Route::livewire('/reset-password/{token}', 'tardis::pages.reset-password')->name('password.reset');
    });

Route::middleware(['web', 'tardis.admin'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::livewire('/dashboard', 'tardis::pages.dashboard')->name('dashboard');

        Route::livewire('/plugins', 'tardis::pages.plugins')->name('plugins.index');
        Route::livewire('/media', 'tardis::pages.media-browser')->name('media');
        Route::livewire('/media/browse', 'tardis::pages.media-browser')->name('media.browse');
        Route::livewire('/activity-log', 'tardis::pages.activity-log')->name('activity.index');
        Route::livewire('/database', 'tardis::pages.database')->name('database.index');
        Route::livewire('/settings', 'tardis::pages.settings')->name('settings.index');

        Route::livewire('/search', 'tardis::pages.search')->name('search');

        Route::livewire('/permissions', 'tardis::pages.permissions')->name('permissions');
        Route::livewire('/roles', 'tardis::pages.roles')->name('roles');

        Route::livewire('/ui-components', 'tardis::pages.ui-components')->name('ui-components');

        Route::livewire('/bread', 'tardis::pages.bread.manage')->name('bread.manage');
        Route::livewire('/bread/create', 'tardis::pages.bread-builder')->name('bread.create');
        Route::livewire('/bread/{slug}/edit', 'tardis::pages.bread-builder')->name('bread.edit');

        Route::livewire('/{slug}', 'tardis::pages.bread.index')->name('bread.index');
        Route::livewire('/{slug}/create', 'tardis::pages.bread.create')->name('bread.add');
        Route::livewire('/{slug}/{id}', 'tardis::pages.bread.read')->name('bread.read');
        Route::livewire('/{slug}/{id}/edit', 'tardis::pages.bread.edit')->name('bread.edit.item');
    });
