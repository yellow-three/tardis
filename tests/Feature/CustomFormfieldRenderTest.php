<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tardis\Bread\Sources\JsonBreadSource;
use Tardis\Formfields\Formfield;
use Tardis\Manager\FormfieldManager;

class CustomFieldPost extends Model
{
    protected $table = 'custom_field_posts';

    protected $guarded = [];
}

class ColorPickerField extends Formfield
{
    protected array $configurable = ['palette'];

    public array $palette = [];

    public function type(): string
    {
        return 'color';
    }

    public function render(): string
    {
        return 'hosttest::color';
    }

    protected function extraViewData(): array
    {
        return ['palette' => $this->palette];
    }
}

test('a field type registered by the host is rendered with its own view on create and edit', function () {
    $views = sys_get_temp_dir().'/tardis-host-views-'.uniqid();
    File::ensureDirectoryExists($views);
    File::put($views.'/color.blade.php', '<input type="color" data-host-type="1" wire:model="{{ $model }}" data-palette="{{ implode(\',\', $palette) }}" />');
    view()->addNamespace('hosttest', $views);

    $path = sys_get_temp_dir().'/tardis-custom-field-'.uniqid();
    app()->instance(JsonBreadSource::class, new JsonBreadSource($path));
    app(FormfieldManager::class)->registerType('color', ColorPickerField::class);

    Schema::create('custom_field_posts', function (Blueprint $table) {
        $table->id();
        $table->string('tint')->nullable();
        $table->timestamps();
    });

    $record = CustomFieldPost::create(['tint' => '#fff']);

    (new JsonBreadSource($path))->save([
        'slug' => 'posts',
        'model' => CustomFieldPost::class,
        'name' => 'Posts',
        'fields' => [
            ['name' => 'tint', 'type' => 'color', 'label' => 'Tint', 'add' => true, 'edit' => true, 'validation' => [], 'palette' => ['red', 'blue']],
        ],
        'relationships' => [],
    ]);

    foreach ([
        Livewire::test('tardis::pages.bread.create', ['slug' => 'posts'])->html(),
        Livewire::test('tardis::pages.bread.edit', ['slug' => 'posts', 'id' => $record->id])->html(),
    ] as $html) {
        expect($html)->toContain('data-host-type="1"')
            ->toContain('wire:model="form.tint"')
            ->toContain('data-palette="red,blue"');
    }

    File::deleteDirectory($views);
    File::deleteDirectory($path);
});

test('a field without a label is shown with a readable one', function () {
    $field = app(FormfieldManager::class)->fields([['type' => 'text', 'name' => 'first_name']])[0];

    expect($field->label)->toBe('First name');
});
