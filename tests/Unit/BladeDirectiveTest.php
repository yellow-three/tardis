<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

test('@tardisStyles directive renders HTML', function () {
    $html = Blade::render('@tardisStyles');

    expect($html)->toContain('<!-- TARDIS Styles -->');
    expect($html)->toContain('<link rel="stylesheet"');
    expect($html)->toContain('vendor/tardis/assets/app.css');
});

test('@tardisScripts directive renders HTML', function () {
    $html = Blade::render('@tardisScripts');

    expect($html)->toContain('<!-- TARDIS Scripts -->');
});
