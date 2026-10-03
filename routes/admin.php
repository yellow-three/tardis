<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tardis\Http\BreadRoutes;
use Tardis\Http\Controllers\AssetController;
use Tardis\Http\Controllers\LocaleController;
use Tardis\Http\Controllers\ThemeController;
use Tardis\Http\PluginRoutes;

// Plugin and host assets, addressed by content hash. No middleware on purpose:
// they are code, not data, the login page needs them too, and a session cookie
// must never ride on a response that is cached for a year.
Route::prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::get('/_assets/{hash}.{extension}', AssetController::class)
            ->where(['hash' => '[a-f0-9]{16}', 'extension' => 'css|js'])
            ->name('assets');
    });

Route::middleware(['web', 'tardis.locale'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::post('/preferences/locale', LocaleController::class)->name('preferences.locale');
        Route::post('/preferences/theme', ThemeController::class)->name('preferences.theme');

        Route::livewire('/login', 'tardis::pages.login')->name('login');
        Route::livewire('/forgot-password', 'tardis::pages.forgot-password')->name('password.request');
        Route::livewire('/reset-password/{token}', 'tardis::pages.reset-password')->name('password.reset');
    });

Route::middleware(['web', 'tardis.locale', 'tardis.admin'])
    ->prefix(config('tardis.admin.prefix', 'admin'))
    ->name('tardis.')
    ->group(function () {
        Route::livewire('/dashboard', 'tardis::pages.dashboard')->name('dashboard');

        Route::livewire('/plugins', 'tardis::pages.plugins')->name('plugins.index');
        Route::livewire('/media', 'tardis::pages.media-browser')->name('media');
        Route::livewire('/media/browse', 'tardis::pages.media-browser')->name('media.browse');
        Route::livewire('/activity-log', 'tardis::pages.activity-log')->name('activity.index');
        Route::livewire('/database', 'tardis::pages.database')->name('database.index');
        Route::livewire('/database/create', 'tardis::pages.database.create')->name('database.create');
        Route::livewire('/database/{table}/edit', 'tardis::pages.database.edit')->name('database.edit');
        Route::livewire('/settings', 'tardis::pages.settings')->name('settings.index');

        Route::livewire('/search', 'tardis::pages.search')->name('search');
        Route::post('/logout', function () {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('tardis.login');
        })->name('logout');

        Route::livewire('/permissions', 'tardis::pages.permissions')->name('permissions');
        Route::livewire('/roles', 'tardis::pages.roles')->name('roles');
        Route::livewire('/users', 'tardis::pages.users')->name('users.index');

        Route::livewire('/ui-components', 'tardis::pages.ui-components')->name('ui-components');

        Route::livewire('/bread', 'tardis::pages.bread.manage')->name('bread.manage');
        Route::livewire('/bread/create', 'tardis::pages.bread-builder')->name('bread.create');
        Route::livewire('/bread/{slug}/edit', 'tardis::pages.bread-builder')->name('bread.edit');
    });

// BREAD routes are generated from the definitions, after every fixed screen.
BreadRoutes::define();

// Plugins that provide routes add them inside the panel's own group.
PluginRoutes::define();
