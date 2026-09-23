<?php

declare(strict_types=1);

use Tardis\Models\ActivityLog;
use Tardis\Models\Media;

test('ActivityLog model exists', function () {
    expect(class_exists(ActivityLog::class))->toBeTrue();
});

test('Media model exists', function () {
    expect(class_exists(Media::class))->toBeTrue();
});
