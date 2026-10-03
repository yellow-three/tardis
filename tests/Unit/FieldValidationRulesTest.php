<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Tardis\Bread\FieldValidationRules;

test('a regex rule containing a pipe stays a single rule', function () {
    $rules = FieldValidationRules::for([
        ['name' => 'colour', 'validation' => ['required', 'regex:/^(red|blue)$/']],
    ]);

    expect($rules['form.colour'])->toBe(['required', 'regex:/^(red|blue)$/']);

    expect(Validator::make(['form' => ['colour' => 'red']], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['form' => ['colour' => 'green']], $rules)->passes())->toBeFalse();
});

test('a pipe separated string is still split into rules', function () {
    $rules = FieldValidationRules::for([
        ['name' => 'title', 'validation' => 'required|max:5'],
    ]);

    expect($rules['form.title'])->toBe(['required', 'max:5']);
});

test('an optional field is made nullable so its other rules skip empty input', function () {
    $rules = FieldValidationRules::for([
        ['name' => 'site', 'validation' => ['url']],
    ]);

    expect($rules['form.site'])->toBe(['nullable', 'url']);
});

test('a field without validation is nullable', function () {
    expect(FieldValidationRules::for([['name' => 'note']])['form.note'])->toBe(['nullable']);
});
