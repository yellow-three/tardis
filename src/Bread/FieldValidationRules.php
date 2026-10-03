<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Http\UploadedFile;

/**
 * Builds Livewire validation rules from a BREAD field's "validation" list.
 *
 * The BREAD builder stores a field's full rule list verbatim — ["required",
 * "max:5"], ["required", "email"] — and the create/edit pages used to reduce
 * that to a bare required/nullable choice, silently dropping everything else.
 * A field declared "max:5" or "email" therefore accepted any value at all.
 */
class FieldValidationRules
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $form  The current form values, used to decide whether file rules apply.
     * @return array<string, array<int, string>>
     */
    public static function for(array $fields, array $form = []): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $rules['form.'.$name] = static::field($field, $form[$name] ?? null);
        }

        return $rules;
    }

    /**
     * Rules are returned as a list, never a pipe-joined string: a rule such as
     * regex:/^(a|b)$/ contains a pipe and would be split in two.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    public static function field(array $field, mixed $value = null): array
    {
        $declared = static::normalise($field['validation'] ?? []);
        $required = in_array('required', $declared, true);

        // An optional field must not be measured against its remaining rules
        // when left empty — "nullable" makes Laravel skip them, which is the
        // intent of every rule declared without "required".
        $rules = $required ? $declared : static::makeNullable($declared);

        if (($field['type'] ?? null) === 'file' && $value instanceof UploadedFile) {
            $rules[] = 'file';

            if (! empty($field['mimes'])) {
                $rules[] = 'mimes:'.implode(',', (array) $field['mimes']);
            }

            if (! empty($field['max_size'])) {
                $rules[] = 'max:'.(int) $field['max_size'];
            }
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    protected static function normalise(mixed $validation): array
    {
        if (is_string($validation)) {
            $validation = array_map('trim', explode('|', $validation));
        }

        if (! is_array($validation)) {
            return ['nullable'];
        }

        $rules = [];

        foreach ($validation as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $rule = trim($rule);

            if ($rule === '' || in_array($rule, ['sometimes'], true)) {
                continue;
            }

            $rules[] = $rule;
        }

        return $rules === [] ? ['nullable'] : $rules;
    }

    /**
     * @param  array<int, string>  $rules
     * @return array<int, string>
     */
    protected static function makeNullable(array $rules): array
    {
        $rules = array_values(array_filter(
            $rules,
            static fn (string $rule): bool => ! in_array($rule, ['nullable', 'required'], true),
        ));

        array_unshift($rules, 'nullable');

        return $rules;
    }
}
