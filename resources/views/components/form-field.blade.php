@props(['field', 'context' => []])

{{--
    One BREAD field: label, the control the field type renders for itself
    (Formfield::render()), a control per locale when it is translatable, the
    validation error and the help text. Create and edit both use this, so a
    type registered by a plugin shows up without touching either page.
--}}
@php
    $locales = $field->translatable ? $field->resolvedLocales() : [];
    $errorKey = 'form.'.$field->name;
@endphp

<div class="flex flex-col gap-2 w-full {{ $field->wrapperClass }}">
    <label class="label" for="field_form_{{ $field->name }}">
        <span class="text-base-content font-medium">
            {{ $field->label }}
            @if (in_array('required', $field->rules, true))
                <span class="text-error">*</span>
            @endif
        </span>
    </label>

    @if ($locales !== [])
        @foreach ($locales as $locale)
            <div class="mb-2">
                <span class="badge badge-ghost badge-sm uppercase mr-2">{{ $locale }}</span>
                @include($field->render(), $field->viewData($context + ['model' => 'form.'.$field->name.'.'.$locale]))
            </div>
        @endforeach
    @else
        @include($field->render(), $field->viewData($context))
    @endif

    @if ($field->helpText)
        <span class="text-base-content/60 text-sm">{{ $field->helpText }}</span>
    @endif

    @error($errorKey)
        <span class="text-error text-sm">{{ $message }}</span>
    @enderror
</div>
