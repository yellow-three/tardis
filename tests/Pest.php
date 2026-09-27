<?php

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
