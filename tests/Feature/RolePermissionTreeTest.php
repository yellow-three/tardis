<?php

declare(strict_types=1);

use Livewire\Livewire;
use Tardis\Database\Seeders\PermissionSeeder;
use Tardis\Models\Permission;
use Tardis\Models\Role;

beforeEach(function () {
    $this->artisan('migrate');
    (new PermissionSeeder)->run();
    Permission::forBread('posts');
    Permission::forBread('pages');

    $this->role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
});

test('permissions are grouped, and BREAD ones by resource', function () {
    $tree = Livewire::test('tardis::pages.roles')->instance()->permissionTree();

    expect($tree)->toHaveKeys(['admin', 'media', 'BREAD'])
        ->and(array_keys($tree['BREAD']))->toEqualCanonicalizing(['posts', 'pages'])
        ->and(array_keys($tree['admin']))->toBe([''])
        ->and(collect($tree['BREAD']['posts'])->pluck('slug')->all())->each->toEndWith('posts');
});

test('a group can be ticked and cleared in one click', function () {
    $page = Livewire::test('tardis::pages.roles')->call('editRole', $this->role->id);

    $media = Permission::where('group', 'media')->pluck('id')->map(fn ($id) => (int) $id)->all();

    $page->call('toggleGroup', 'media');
    expect(collect($page->get('editRolePermissions'))->map(fn ($id) => (int) $id)->sort()->values()->all())->toBe(collect($media)->sort()->values()->all());

    $page->call('toggleGroup', 'media');
    expect($page->get('editRolePermissions'))->toBe([]);
});

test('one BREAD resource can be toggled without touching another', function () {
    $page = Livewire::test('tardis::pages.roles')->call('editRole', $this->role->id)->call('toggleGroup', 'BREAD', 'posts');

    $slugs = Permission::whereIn('id', $page->get('editRolePermissions'))->pluck('slug')->all();

    expect($slugs)->toHaveCount(5)->each->toEndWith('posts');

    $page->call('toggleGroup', 'BREAD');
    expect(Permission::whereIn('id', $page->get('editRolePermissions'))->count())->toBe(10);
});

test('saving stores the ticked permissions on the role', function () {
    Livewire::test('tardis::pages.roles')
        ->call('editRole', $this->role->id)
        ->call('toggleGroup', 'BREAD', 'pages')
        ->call('saveRolePermissions');

    expect($this->role->permissions()->count())->toBe(5);
});
