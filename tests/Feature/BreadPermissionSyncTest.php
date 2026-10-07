<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Tardis\Bread\BreadManager;
use Tardis\Models\Permission;

test('saving a BREAD definition provisions its five permissions', function () {
    $this->artisan('migrate');

    app(BreadManager::class)->save(['slug' => 'posts', 'model' => 'App\\Models\\Post', 'fields' => []]);

    expect(Permission::where('group', 'BREAD')->pluck('slug')->sort()->values()->all())
        ->toBe(['add posts', 'browse posts', 'delete posts', 'edit posts', 'read posts']);
});

test('saving twice does not duplicate permissions', function () {
    $this->artisan('migrate');

    $manager = app(BreadManager::class);
    $manager->save(['slug' => 'posts', 'model' => 'App\\Models\\Post', 'fields' => []]);
    $manager->save(['slug' => 'posts', 'model' => 'App\\Models\\Post', 'fields' => []]);

    expect(Permission::count())->toBe(5);
});

test('saving still works on an install that has not run the permission migration', function () {
    expect(Schema::hasTable('tardis_permissions'))->toBeFalse();

    app(BreadManager::class)->save(['slug' => 'posts', 'model' => 'App\\Models\\Post', 'fields' => []]);

    expect(app(BreadManager::class)->has('posts'))->toBeTrue();
});
