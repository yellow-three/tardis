@use('Tardis\Classes\Translation')

<div>
    <x-tardis::page-header
        :title="$bread['name'] ?? ucfirst($slug)"
        :description="__('tardis::bread.record_details')"
    >
        <x-slot:action>
            <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug) }}" class="btn btn-ghost">{{ __('tardis::bread.back_to_list') }}</a>
        </x-slot:action>
    </x-tardis::page-header>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body grid grid-cols-6 gap-4">
            @php
                $layout = $bread['layout'] ?? [];
                $widths = $layout['widths'] ?? [];
                $legends = $layout['legends'] ?? [];
                $spanClasses = [1 => 'col-span-1', 2 => 'col-span-2', 3 => 'col-span-3', 4 => 'col-span-4', 5 => 'col-span-5', 6 => 'col-span-6'];
            @endphp
            @foreach (($this->layoutFields ?? $this->fields) as $field)
                @php
                    $name = $field['name'] ?? '';
                    $locales = $field['locales'] ?? null;

                    // A field label may be a plain string, a translation key or a
                    // locale map, and a translatable record value is a locale map
                    // too. Both resolve for the active locale — and when the answer
                    // had to be borrowed from another language, it says which one
                    // instead of passing as if it were written in this language.
                    $rawLabel = $field['label'] ?? null;
                    $label = Translation::label($rawLabel ?? ucfirst((string) $name), $locales);
                    $labelLocale = Translation::sourceLocale($rawLabel, $locales);

                    $stored = data_get($record, $name);
                    $translatable = ! empty($field['translatable']);

                    $value = $translatable ? Translation::value($stored, $locales) : ($stored ?? '-');
                    $valueLocale = $translatable ? Translation::sourceLocale($stored, $locales) : null;
                @endphp

                @php
                    $span = $widths[$name] ?? 6;
                    $spanClass = $spanClasses[$span] ?? 'col-span-6';
                    $legend = $legends[$name] ?? null;
                @endphp

                @if ($legend)
                    <h3 class="col-span-full text-sm font-semibold uppercase tracking-wide text-base-content/70 border-b border-base-300 pb-1 mt-2">{{ $legend }}</h3>
                @endif

                <div class="border-b border-base-300 pb-3 {{ $spanClass }}">
                    <div class="text-xs uppercase tracking-wide text-base-content/50">
                        {{ $label }}
                        @if ($labelLocale)
                            <span class="badge badge-ghost badge-xs uppercase" title="{{ __('tardis::bread.fallback_locale', ['locale' => $labelLocale]) }}">{{ $labelLocale }}</span>
                        @endif
                    </div>
                    <div class="mt-1 text-base font-medium">
                        {{ $value }}
                        @if ($valueLocale)
                            <span class="badge badge-warning badge-sm uppercase" title="{{ __('tardis::bread.fallback_locale', ['locale' => $valueLocale]) }}">{{ $valueLocale }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
