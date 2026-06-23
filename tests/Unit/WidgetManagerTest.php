<?php

declare(strict_types=1);

use Tardis\Classes\Widget;
use Tardis\Manager\WidgetManager;

test('widget manager can be instantiated', function () {
    $manager = new WidgetManager;

    expect($manager)->toBeInstanceOf(WidgetManager::class);
});

test('widget manager all starts empty', function () {
    $manager = new WidgetManager;

    expect($manager->all())->toHaveCount(0);
});

test('widget manager addWidgets adds widgets', function () {
    $manager = new WidgetManager;
    $widget1 = new Widget('stats', 'Stats');
    $widget2 = new Widget('chart', 'Chart');

    $manager->addWidgets($widget1, $widget2);

    expect($manager->all())->toHaveCount(2);
});

test('widget manager all returns sorted by order', function () {
    $manager = new WidgetManager;
    $widget1 = (new Widget('chart', 'Chart'))->order(10);
    $widget2 = (new Widget('stats', 'Stats'))->order(1);

    $manager->addWidgets($widget1, $widget2);

    $widgets = $manager->all();
    expect($widgets->first()->title)->toBe('Stats')
        ->and($widgets->last()->title)->toBe('Chart');
});
