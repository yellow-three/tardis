<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;

class FormfieldTypesProbe extends Model
{
    protected $table = 'formfield_types_probes';

    protected $guarded = [];
}

test('the new field types render their own controls on the create page', function () {
    $path = sys_get_temp_dir().'/tardis-formfield-types-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($path));

    Schema::create('formfield_types_probes', function (Blueprint $table) {
        $table->id();
        $table->string('location')->nullable();
        $table->text('body')->nullable();
        $table->json('tags')->nullable();
        $table->json('rows')->nullable();
        $table->json('roles')->nullable();
        $table->timestamps();
    });

    (new JsonBreadSource($path))->save([
        'slug' => 'probes',
        'model' => FormfieldTypesProbe::class,
        'name' => 'Probes',
        'fields' => [
            ['name' => 'location', 'type' => 'coordinates', 'label' => 'Location', 'add' => true, 'edit' => true, 'validation' => []],
            ['name' => 'body', 'type' => 'rich_text', 'label' => 'Body', 'add' => true, 'edit' => true, 'validation' => []],
            ['name' => 'tags', 'type' => 'simple_array', 'label' => 'Tags', 'add' => true, 'edit' => true, 'validation' => []],
            ['name' => 'rows', 'type' => 'repeater', 'label' => 'Rows', 'add' => true, 'edit' => true, 'validation' => [], 'fields' => [
                ['name' => 'title', 'type' => 'text'],
                ['name' => 'amount', 'type' => 'number'],
            ]],
            ['name' => 'roles', 'type' => 'select_multiple', 'label' => 'Roles', 'add' => true, 'edit' => true, 'validation' => [], 'options' => ['a' => 'A', 'b' => 'B']],
        ],
        'relationships' => [],
    ]);

    $html = Livewire::test('tardis::pages.bread.create', ['slug' => 'probes'])->html();

    expect($html)
        ->toContain('form.location.lat')
        ->toContain('form.location.lng')
        ->toContain('data-rich-text')
        ->toContain('form.body')
        ->toContain('form.tags')
        ->toContain('form.rows')
        ->toContain('form.roles')
        ->toContain('multiple');

    File::deleteDirectory($path);
});
