@props(['field', 'context' => [], 'activeLocale' => null])

{{--
    One BREAD field: label, the control the field type renders for itself
    (Formfield::render()), the validation error and the help text. Create and
    edit both use this, so a type registered by a plugin shows up without
    touching either page.

    A translatable field holds one value per locale. Rendering a stacked control
    for every locale makes a form with two languages twice as tall and pushes
    the field's help text off screen, so the locales are tabs instead: one
    control, bound to the locale being edited. The values of the locales that are
    not showing stay in the component's own state and come back when their tab
    is selected again — no hidden inputs are needed to carry them through a save.
--}}
@php
    foreach ($field->assets() as $fieldAsset) {
        app(\Tardis\Manager\AssetManager::class)->require($fieldAsset);
    }

    $locales = $field->translatable ? $field->resolvedLocales() : [];
    $tabbed = $locales !== [] && (bool) config('tardis.translation.tabs', true);

    // A locale the page never selected (no translatable field declares it, or it
    // arrived from a stale request) must not leave the field without a control.
    $current = $tabbed
        ? (in_array($activeLocale, $locales, true) ? $activeLocale : $locales[0])
        : null;

    $fieldErrors = [];

    foreach (array_merge([''], $locales) as $suffix) {
        $key = 'form.'.$field->name.($suffix === '' ? '' : '.'.$suffix);
        $message = $errors->get($key);

        if (is_array($message)) {
            foreach ($message as $single) {
                $fieldErrors[$suffix] = $single;
            }

            continue;
        }

        if (is_string($message) && $message !== '') {
            $fieldErrors[$suffix] = $message;
        }
    }
@endphp

@if ($field->type() === 'hidden')
    @include($field->render(), $field->viewData($context))
@else
<div class="flex flex-col gap-2 w-full {{ $field->wrapperClass }}">
    <label class="label" for="field_form_{{ $field->name }}">
        <span class="text-base-content font-medium">
            {{ $field->resolvedLabel() }}
            @if (in_array('required', $field->rules, true))
                <span class="text-error">*</span>
            @endif
        </span>
    </label>

    @if ($tabbed)
        <div role="tablist" class="tabs tabs-box tabs-sm w-fit">
            @foreach ($locales as $locale)
                <button
                    type="button"
                    role="tab"
                    class="tab {{ $locale === $current ? 'tab-active' : '' }}"
                    wire:click="setActiveLocale('{{ $locale }}')"
                    wire:key="{{ $field->name }}-tab-{{ $locale }}"
                >{{ strtoupper($locale) }}</button>
            @endforeach
        </div>

        <div wire:key="{{ $field->name }}-locale-{{ $current }}">
            @include($field->render(), $field->viewData($context + ['model' => 'form.'.$field->name.'.'.$current]))
        </div>
    @elseif ($locales !== [])
        @foreach ($locales as $locale)
            <div class="mb-2" wire:key="{{ $field->name }}-locale-{{ $locale }}">
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

    @foreach ($fieldErrors as $suffix => $message)
        <div class="flex flex-wrap items-center gap-2">
            {{-- In "all locales" mode a failing locale may not be the one on screen, so the error names its tab. --}}
            @if ($tabbed && $suffix !== '')
                <button type="button" class="link link-error text-sm" wire:click="setActiveLocale('{{ $suffix }}')">{{ strtoupper($suffix) }}</button>
            @endif
            <span class="text-error text-sm">{{ $message }}</span>
        </div>
    @endforeach
</div>
@endif