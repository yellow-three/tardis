<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('@tardisStyles directive renders HTML', function () {
    $html = Blade::render('@tardisStyles');

    expect($html)->toContain('<!-- Tardis Styles -->');
    expect($html)->toContain('<link rel="stylesheet"');
    expect($html)->toContain('/_assets/')->toContain('.css');
});

test('@tardisScripts directive renders HTML', function () {
    $html = Blade::render('@tardisScripts');

    expect($html)->toContain('<!-- Tardis Scripts -->');
});
