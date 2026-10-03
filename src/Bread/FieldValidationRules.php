<?php

declare(strict_types=1);

namespace Tardis\Bread;

use Illuminate\Http\UploadedFile;
use Tardis\Classes\Translation;

/**
 * Builds Livewire validation rules from a BREAD field's "validation" list.
 *
 * The BREAD builder stores a field's full rule list verbatim — ["required",
 * "max:5"], ["required", "email"] — and the create/edit pages used to reduce
 * that to a bare required/nullable choice, silently dropping everything else.
 * A field declared "max:5" or "email" therefore accepted any value at all.
 *
 * A translatable field holds a locale-keyed array, so "required" on its own is
 * close to meaningless: Laravel only asks whether the array is non-empty, and
 * ['en' => 'Hi', 'tr' => ''] is non-empty even though one translation is
 * missing. The declared rules are therefore applied per locale as well, over
 * the locales the field is validated in — every one of them, or only the locale
 * currently being edited.
 */
class FieldValidationRules
{
    /**
     * Rules that describe the shape of a value rather than its contents. Only
     * these stay on a translatable field's array key; "string" or "max:5" on
     * the array itself would be applied to the wrong thing.
     *
     * @var array<int, string>
     */
    protected const CONTAINER_RULES = ['array', 'nullable', 'sometimes', 'present', 'filled', 'required'];

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $form  The current form values, used to decide whether file rules apply.
     * @param  string|null  $activeLocale  The locale being edited, for "active" validation mode.
     * @return array<string, array<int, string>>
     */
    public static function for(array $fields, array $form = [], ?string $activeLocale = null): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            if (static::isTranslatable($field)) {
                $rules['form.'.$name] = static::container($field);

                foreach (static::validatedLocales($field, $activeLocale) as $locale) {
                    $rules['form.'.$name.'.'.$locale] = static::field($field, data_get($form, $name.'.'.$locale));
                }

                continue;
            }

            $rules['form.'.$name] = static::field($field, $form[$name] ?? null);
        }

        return $rules;
    }

    public static function isTranslatable(array $field): bool
    {
        return ! empty($field['translatable']);
    }

    /**
     * Which locales a field's rules are applied to: all of them, or just the
     * one being edited. The mode comes from the field, then from
     * tardis.translation.validation.
     */
    public static function validationMode(array $field): string
    {
        $mode = $field['validation_mode'] ?? config('tardis.translation.validation', 'all');

        return $mode === 'active' ? 'active' : 'all';
    }

    /**
     * @return array<int, string>
     */
    public static function validatedLocales(array $field, ?string $activeLocale = null): array
    {
        $locales = Translation::locales($field['locales'] ?? null);

        if (static::validationMode($field) !== 'active') {
            return $locales;
        }

        // An active locale the field does not declare still gets validated —
        // against its own nearest neighbour, so the rule set stays consistent.
        $active = $activeLocale ?: (string) app()->getLocale();

        return in_array($active, $locales, true) ? [$active] : [$locales[0]];
    }

    /**
     * The rules kept on a translatable field's array key: only the ones that
     * describe the container. An optional field must not be measured against
     * "array" alone, which is why it stays nullable.
     *
     * @return array<int, string>
     */
    protected static function container(array $field): array
    {
        $declared = array_values(array_intersect(
            static::normalise($field['validation'] ?? []),
            self::CONTAINER_RULES,
        ));

        if ($declared !== []) {
            return $declared;
        }

        return ['nullable'];
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
