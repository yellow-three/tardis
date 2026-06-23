<?php

declare(strict_types=1);

use Tardis\Facades\Tardis;

test('Tardis facade is accessible', function () {
    $version = Tardis::version();
    expect($version)->toBe('1.0.0');
});

test('Tardis version returns string', function () {
    $version = Tardis::version();
    expect($version)->toBeString();
});
