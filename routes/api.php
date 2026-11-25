<?php

use Illuminate\Support\Facades\Route;
use Tardis\Http\Controllers\Api\BreadApiController;
use Tardis\Http\Middleware\ApiAccess;
use Tardis\Http\Middleware\ApiJson;

// The JSON API over the BREAD definitions (tardis.api). Loaded only when
// tardis.api.enabled is true; every action asks the panel's authorization.
// ApiJson goes before the configurable middleware so auth errors are JSON too.
Route::middleware([ApiJson::class, ...(array) config('tardis.api.middleware', ['api', 'auth:sanctum', 'throttle:60,1']), ApiAccess::class])
    ->prefix((string) config('tardis.api.prefix', 'api/tardis'))
    ->name('tardis.api.')
    ->group(function () {
        Route::get('/', [BreadApiController::class, 'definitions'])->name('definitions');
        Route::get('/{slug}', [BreadApiController::class, 'index'])->name('index');
        Route::post('/{slug}', [BreadApiController::class, 'store'])->name('store');
        Route::get('/{slug}/{id}', [BreadApiController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{slug}/{id}', [BreadApiController::class, 'update'])->name('update');
        Route::delete('/{slug}/{id}', [BreadApiController::class, 'destroy'])->name('destroy');
    });
