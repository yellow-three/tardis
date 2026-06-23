<?php

declare(strict_types=1);

use Tardis\Classes\Widget;

test('widget can be created with component and title', function () {
    $widget = new Widget('stats', 'Dashboard Stats');

    expect($widget->component)->toBe('stats')
        ->and($widget->title)->toBe('Dashboard Stats');
});

test('widget has default width of 6', function () {
    $widget = new Widget('stats', 'Dashboard Stats');

    expect($widget->width)->toBe(6);
});

test('widget width is clamped between 3 and 12', function () {
    $widget1 = (new Widget('a', 'A'))->width(1);
    $widget2 = (new Widget('b', 'B'))->width(15);
    $widget3 = (new Widget('c', 'C'))->width(8);

    expect($widget1->width)->toBe(3)
        ->and($widget2->width)->toBe(12)
        ->and($widget3->width)->toBe(8);
});

test('widget fluent methods return self', function () {
    $widget = new Widget('stats', 'Stats');

    $result = $widget->icon('chart-bar')
        ->permission('view-dashboard')
        ->parameters(['key' => 'value'])
        ->order(10);

    expect($result)->toBeInstanceOf(Widget::class)
        ->and($widget->icon)->toBe('chart-bar')
        ->and($widget->permission)->toBe('view-dashboard')
        ->and($widget->parameters)->toBe(['key' => 'value'])
        ->and($widget->order)->toBe(10);
});

test('widget has default order of 50', function () {
    $widget = new Widget('stats', 'Stats');

    expect($widget->order)->toBe(50);
});

test('widget isVisible returns true when no permission set', function () {
    $widget = new Widget('stats', 'Stats');

    expect($widget->isVisible())->toBeTrue();
});
