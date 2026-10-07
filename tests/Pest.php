<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\File;
use Tardis\Bread\Sources\JsonBreadSource;
use Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature', 'Integration');

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
