<?php

declare(strict_types=1);

use Tardis\Classes\Setting;

test('setting can be created from array', function () {
    $setting = new Setting([
        'type' => 'text',
        'group' => 'general',
        'key' => 'site_name',
        'name' => 'Site Name',
        'value' => 'My Site',
    ]);

    expect($setting->type)->toBe('text')
        ->and($setting->group)->toBe('general')
        ->and($setting->key)->toBe('site_name')
        ->and($setting->name)->toBe('Site Name')
        ->and($setting->value)->toBe('My Site');
});

test('setting has defaults', function () {
    $setting = new Setting([]);

    expect($setting->type)->toBe('text')
        ->and($setting->group)->toBeNull()
        ->and($setting->key)->toBe('')
        ->and($setting->name)->toBe('')
        ->and($setting->value)->toBeNull()
        ->and($setting->info)->toBeNull()
        ->and($setting->translatable)->toBeFalse()
        ->and($setting->canBeTranslated)->toBeFalse()
        ->and($setting->options)->toBe([])
        ->and($setting->validation)->toBe([]);
});

test('setting getFullKey with group', function () {
    $setting = new Setting([
        'group' => 'general',
        'key' => 'site_name',
    ]);

    expect($setting->getFullKey())->toBe('general.site_name');
});

test('setting getFullKey without group', function () {
    $setting = new Setting([
        'key' => 'site_name',
    ]);

    expect($setting->getFullKey())->toBe('site_name');
});

test('setting displayName returns name', function () {
    $setting = new Setting([
        'name' => 'Site Name',
    ]);

    expect($setting->displayName())->toBe('Site Name');
});

test('setting toArray returns all properties', function () {
    $setting = new Setting([
        'type' => 'text',
        'group' => 'general',
        'key' => 'site_name',
        'name' => 'Site Name',
        'value' => 'My Site',
    ]);

    $array = $setting->toArray();

    expect($array)->toBeArray()
        ->and($array['type'])->toBe('text')
        ->and($array['group'])->toBe('general')
        ->and($array['key'])->toBe('site_name')
        ->and($array['name'])->toBe('Site Name')
        ->and($array['value'])->toBe('My Site');
});
