<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\File;
use Tardis\Bread\Sources\JsonBreadSource;
use Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature', 'Integration', 'Browser');

// A browser step that cannot find what it waits for fails after 10 seconds
// instead of holding the run.
if (function_exists('pest') && method_exists(pest(), 'browser')) {
    pest()->browser()->timeout(10_000);
}

// Testbench keeps sessions in an array that lives for one request; a browser
// signs in on one request and comes back on the next, so it needs a store that
// outlasts the request (under the per-test storage path).
pest()->beforeEach(function () {
    File::ensureDirectoryExists(storage_path('framework/sessions'));
    config()->set('session.driver', 'file');
})->in('Browser');

beforeEach(function () {
    // Safety net: bind JsonBreadSource to a disposable temp dir so tests that
    // trigger container resolution never write into the real storage path.
    $this->breadStoragePath = sys_get_temp_dir().'/tardis-bread-tests-'.uniqid();

    app()->instance(JsonBreadSource::class, new JsonBreadSource($this->breadStoragePath));
});

afterEach(function () {
    File::deleteDirectory($this->breadStoragePath);
});

/**
 * Re-register the package routes against the BREAD definitions saved so far.
 *
 * BREAD routes are generated from the definitions when the routes load, which
 * happens at boot; a test that saves a definition afterwards calls this to get
 * the routes a real request would have.
 */
function reloadAdminRoutes(): void
{
    $router = app('router');
    $router->setRoutes(new RouteCollection);

    require __DIR__.'/../routes/admin.php';

    $router->getRoutes()->refreshNameLookups();
    $router->getRoutes()->refreshActionLookups();
}

/**
 * Add the routes of BREAD definitions saved after boot, keeping every route
 * already registered (Livewire's update route among them, which
 * reloadAdminRoutes() would drop). For tests that drive a real browser.
 */
function addBreadRoutes(): void
{
    require __DIR__.'/../routes/admin.php';

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
}
