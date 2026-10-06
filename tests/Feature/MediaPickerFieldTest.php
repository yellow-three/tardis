<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tardis\Auth\Abilities;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Formfields\Types\MediaPickerField;
use Tardis\Manager\PluginManager;

/**
 * Answers from an allow-list, so a test states exactly which abilities the
 * picker may use. Unique to this file: the Pest process is shared, and
 * AdminScreenAuthorizationTest already owns ScreenGatePlugin/gateAllows.
 */
class PickerGatePlugin implements AuthorizationPlugin
{
    /** @var array<int, string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'picker-gate';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        return in_array($ability, static::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        $this->can($ability, $model) || abort(403);
    }
}

function pickerGateAllows(array $abilities): void
{
    PickerGatePlugin::$allowed = $abilities;

    $manager = app(PluginManager::class);
    $manager->register('picker-gate', PickerGatePlugin::class);
    $manager->enableByDefault('picker-gate');
}

beforeEach(function () {
    config()->set('tardis-media.disk', 'public');
    config()->set('tardis-media.path', 'media');

    Storage::fake('public');
    Storage::disk('public')->makeDirectory('media');

    PickerGatePlugin::$allowed = [];
});

it('renders the bridge, input and browse button for the field', function () {
    view()->share('errors', new ViewErrorBag);

    $field = (new MediaPickerField('image', 'Image'))->mimes(['image/*']);

    $html = Blade::render('<x-tardis::form-field :field="$field" />', ['field' => $field]);

    expect($html)
        ->toContain('id="field_form_image"')
        ->toContain('wire:model="form.image"')
        ->toContain('x-on:media-picked.window')
        ->toContain("\$event.detail.model === 'form.image'")
        ->toContain("\$wire.set('form.image'")
        ->toContain("\$dispatch('media-picker-open'")
        ->toContain(__('tardis::settings.browse_media'))
        ->toContain(__('tardis::fields.allowed_types', ['types' => 'image/*']));
});

it('filters the file list by mime type and keeps folders when enabled', function () {
    Storage::disk('public')->put('media/photo.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\x00", 16));
    Storage::disk('public')->put('media/notes.txt', 'plain text');
    Storage::disk('public')->makeDirectory('media/gallery');

    Livewire::test('tardis::pages.media-picker', [
        'model' => 'form.image',
        'mimes' => ['image/*'],
        'showFolders' => true,
    ])
        ->dispatch('media-picker-open', model: 'form.image')
        ->assertSet('open', true)
        ->assertSee('photo.jpg')
        ->assertDontSee('notes.txt')
        ->assertSee('gallery');
});

it('picks a single file and dispatches media-picked with the storage path', function () {
    Storage::disk('public')->put('media/photo.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\x00", 16));

    Livewire::test('tardis::pages.media-picker', ['model' => 'form.image'])
        ->dispatch('media-picker-open', model: 'form.image')
        ->call('toggleSelect', 'media/photo.jpg')
        ->call('confirm')
        ->assertDispatched('media-picked', model: 'form.image', value: 'media/photo.jpg')
        ->assertSet('open', false);
});

it('enforces the max selection limit in multiple mode', function () {
    Livewire::test('tardis::pages.media-picker', ['model' => 'form.image', 'max' => 2])
        ->dispatch('media-picker-open', model: 'form.image')
        ->call('toggleSelect', 'media/a.jpg')
        ->call('toggleSelect', 'media/b.jpg')
        ->call('toggleSelect', 'media/c.jpg')
        ->assertSet('selected', ['media/a.jpg', 'media/b.jpg'])
        ->assertSet('error', __('tardis::media.select_limit', ['max' => 2]));
});

it('enforces the min selection limit before confirming', function () {
    $component = Livewire::test('tardis::pages.media-picker', ['model' => 'form.image', 'min' => 2])
        ->dispatch('media-picker-open', model: 'form.image')
        ->call('toggleSelect', 'media/a.jpg')
        ->call('confirm')
        ->assertNotDispatched('media-picked')
        ->assertSet('open', true)
        ->assertSet('error', __('tardis::media.select_minimum', ['min' => 2]));

    $component
        ->call('toggleSelect', 'media/b.jpg')
        ->call('confirm')
        ->assertDispatched('media-picked', model: 'form.image', value: ['media/a.jpg', 'media/b.jpg'])
        ->assertSet('open', false);
});

it('forbids opening the picker without the browse media ability', function () {
    pickerGateAllows([]);

    Livewire::test('tardis::pages.media-picker', ['model' => 'form.image'])
        ->call('openPicker', 'form.image', null)
        ->assertForbidden();
});

it('opens the picker when the browse media ability is granted', function () {
    pickerGateAllows([Abilities::MEDIA_BROWSE]);

    Livewire::test('tardis::pages.media-picker', ['model' => 'form.image'])
        ->call('openPicker', 'form.image', null)
        ->assertSet('open', true);
});
