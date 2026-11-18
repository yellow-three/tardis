<?php

use Illuminate\Support\Facades\Route;
use Tardis\Http\BreadRoutes;
use Tardis\Http\Controllers\AssetController;
use Tardis\Http\Controllers\LocaleController;
use Tardis\Http\Controllers\LogoutController;
use Tardis\Http\Controllers\ThemeController;
use Tardis\Http\PanelRoutes;
use Tardis\Http\PluginRoutes;

// Plugin and host assets, addressed by content hash. No middleware on purpose:
// they are code, not data, the login page needs them too, and a session cookie
// must never ride on a response that is cached for a year.
PanelRoutes::bare(function () {
    Route::get('/_assets/{hash}.{extension}', AssetController::class)
        ->where(['hash' => '[a-f0-9]{16}', 'extension' => 'css|js'])
        ->name('assets');
});

PanelRoutes::guest(function () {
    Route::post('/preferences/locale', LocaleController::class)->name('preferences.locale');
    Route::post('/preferences/theme', ThemeController::class)->name('preferences.theme');

    // Outside the panel group: a user without "access admin" must be able
    // to sign out, and the root has to reach the login page for a guest.
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::get('/', fn () => redirect()->route('tardis.dashboard'))->name('home');

    Route::livewire('/login', 'tardis::pages.login')->name('login');
    Route::livewire('/forgot-password', 'tardis::pages.forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'tardis::pages.reset-password')->name('password.reset');
});

PanelRoutes::panel(function () {
    Route::livewire('/dashboard', 'tardis::pages.dashboard')->name('dashboard');

    Route::livewire('/themes', 'tardis::pages.theme-editor')->name('themes.index');
    Route::livewire('/menus', 'tardis::pages.menu-builder')->name('menus.index');
    Route::livewire('/menus/site', 'tardis::pages.site-menus')->name('site-menus.index');
    Route::livewire('/plugins', 'tardis::pages.plugins')->name('plugins.index');
    Route::livewire('/media', 'tardis::pages.media-browser')->name('media.index');
    // The media screen used to answer here too; old links land on it.
    Route::get('/media/browse', fn () => redirect()->route('tardis.media.index'))->name('media.browse');
    Route::livewire('/media/{id}/edit', 'tardis::pages.media-edit')->whereNumber('id')->name('media.edit');
    Route::livewire('/activity-log', 'tardis::pages.activity-log')->name('activity.index');
    Route::livewire('/database', 'tardis::pages.database')->name('database.index');
    Route::livewire('/database/create', 'tardis::pages.database.create')->name('database.create');
    Route::livewire('/database/{table}/edit', 'tardis::pages.database.edit')->name('database.edit');
    Route::livewire('/settings', 'tardis::pages.settings')->name('settings.index');

    // System tools. Each screen checks its own ability on mount and on
    // every update, so the routes only carry the shared panel middleware.
    Route::livewire('/system', 'tardis::pages.system')->name('system.index');
    Route::livewire('/system/logs', 'tardis::pages.system.logs')->name('system.logs');
    Route::livewire('/system/commands', 'tardis::pages.system.commands')->name('system.commands');

    Route::livewire('/search', 'tardis::pages.search')->name('search');
    Route::livewire('/permissions', 'tardis::pages.permissions')->name('permissions.index');
    Route::livewire('/roles', 'tardis::pages.roles')->name('roles.index');
    Route::livewire('/users', 'tardis::pages.users')->name('users.index');

    Route::livewire('/ui-components', 'tardis::pages.ui-components')->name('ui-components');

    Route::livewire('/bread', 'tardis::pages.bread.manage')->name('builder.index');
    Route::livewire('/bread/create', 'tardis::pages.bread-builder')->name('builder.create');
    Route::livewire('/bread/{slug}/edit', 'tardis::pages.bread-builder')->name('builder.edit');
});

// Plugin routes, then the BREAD routes generated from the definitions, both
// after every fixed screen. Plugins register in their own providers, so this
// waits for boot; plugin routes come first so a BREAD can never shadow one
// (their first segment is reserved and a BREAD with that slug gets no routes).
PanelRoutes::whenBooted(function () {
    PluginRoutes::define();
    BreadRoutes::define();

    // Names are given after a route is added, and the provider's own refresh
    // ran before this; without it route('tardis.bread.index') is unknown.
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
});
