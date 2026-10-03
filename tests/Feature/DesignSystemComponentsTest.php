<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('a card shows its title, actions and body', function () {
    $html = Blade::render(<<<'BLADE'
<x-tardis::card title="Posts">
    <x-slot name="actions"><button>Add</button></x-slot>
    Body text
</x-tardis::card>
BLADE);

    expect($html)->toContain('Posts')->toContain('<button>Add</button>')->toContain('Body text');
});

test('a card without a title or actions renders no header', function () {
    $html = Blade::render('<x-tardis::card>Only body</x-tardis::card>');

    expect($html)->toContain('Only body')->not->toContain('<h2');
});

test('a card escapes its title', function () {
    expect(Blade::render('<x-tardis::card :title="$t">x</x-tardis::card>', ['t' => '<script>x</script>']))->not->toContain('<script>x');
});

test('a badge takes a colour and is neutral by default', function () {
    expect(Blade::render('<x-tardis::badge color="success">OK</x-tardis::badge>'))->toContain('badge-success')
        ->and(Blade::render('<x-tardis::badge>Plain</x-tardis::badge>'))->toContain('badge-ghost');
});

test('an unknown badge colour falls back instead of writing a class from user input', function () {
    expect(Blade::render('<x-tardis::badge color="x onclick=evil">A</x-tardis::badge>'))->not->toContain('onclick')->toContain('badge-ghost');
});

test('a modal is closed by default and wired to an alpine flag', function () {
    $html = Blade::render('<x-tardis::modal name="confirm" title="Sure?">Really?</x-tardis::modal>');

    expect($html)->toContain('Sure?')->toContain('Really?')->toContain('role="dialog"')->toContain('aria-modal="true"')->toContain('x-show="open"');
});

test('a slide-in drawer renders its title and content', function () {
    $html = Blade::render('<x-tardis::slide-in title="Options">Panel</x-tardis::slide-in>');

    expect($html)->toContain('Options')->toContain('Panel')->toContain('role="dialog"');
});

test('the toast container reads the toast store and announces politely', function () {
    $html = Blade::render('<x-tardis::toasts />');

    expect($html)->toContain('$store.toasts.items')->toContain('aria-live="polite"');
});

test('the loading bar listens to Livewire navigation', function () {
    $html = Blade::render('<x-tardis::loading-bar />');

    expect($html)->toContain('livewire:navigate')->toContain('role="progressbar"');
});

test('the admin layout includes the toast container and the loading bar', function () {
    $layout = file_get_contents(__DIR__.'/../../resources/views/layouts/admin.blade.php');

    expect($layout)->toContain('<x-tardis::toasts />')->toContain('<x-tardis::loading-bar />');
});
