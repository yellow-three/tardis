<?php

declare(strict_types=1);

use Tardis\Models\ActivityLog;
use Tardis\Models\DataRow;
use Tardis\Models\DataType;
use Tardis\Models\Media;

test('DataType model exists', function () {
    expect(class_exists(DataType::class))->toBeTrue();
});

test('DataRow model exists', function () {
    expect(class_exists(DataRow::class))->toBeTrue();
});

test('ActivityLog model exists', function () {
    expect(class_exists(ActivityLog::class))->toBeTrue();
});

test('Media model exists', function () {
    expect(class_exists(Media::class))->toBeTrue();
});
