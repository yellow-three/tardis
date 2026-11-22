@php
    // Read from the slug on every request; it is not part of the snapshot.
    $bread = $this->bread;
@endphp
@use('Tardis\Support\Translation')

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

                    $value = $translatable
                        ? Translation::value($stored, $locales)
                        : (($this->formfields[$name] ?? null)?->read($stored) ?? $stored ?? '-');
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

    @php
        $history = $this->history;
        $mayRevert = $this->canRevert();
    @endphp
    @if (session('message'))
        <div class="alert alert-success mt-6 text-sm" role="status">{{ session('message') }}</div>
    @endif
    <div class="card bg-base-100 border border-base-300 mt-6">
        <div class="card-body">
            <h2 class="card-title text-base">{{ __('tardis::bread.history') }}</h2>
            @forelse ($history as $entry)
                <div wire:key="history-{{ $entry['id'] }}" class="border-b border-base-200 py-3 last:border-0">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="badge badge-ghost badge-sm">{{ __('tardis::activity.'.$entry['action']) }}</span>
                        <span class="font-medium">{{ $entry['user'] }}</span>
                        <span class="text-base-content/50">{{ $entry['at'] }}</span>
                        @if ($entry['revertible'] && $mayRevert)
                            <button type="button" wire:click="revert({{ $entry['id'] }})" wire:confirm="{{ __('tardis::bread.history_revert_confirm') }}" class="btn btn-ghost btn-xs ml-auto">{{ __('tardis::bread.history_revert') }}</button>
                        @endif
                    </div>
                    @if ($entry['changes'] !== [])
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($entry['changes'] as $change)
                                @php
                                    $oldText = is_scalar($change['old']) || $change['old'] === null ? (string) $change['old'] : json_encode($change['old'], JSON_UNESCAPED_UNICODE);
                                    $newText = is_scalar($change['new']) || $change['new'] === null ? (string) $change['new'] : json_encode($change['new'], JSON_UNESCAPED_UNICODE);
                                @endphp
                                <li class="flex flex-wrap gap-x-2">
                                    <span class="text-base-content/60">{{ $change['label'] }}:</span>
                                    @if ($entry['action'] === 'updated')
                                        <del class="text-error/80">{{ \Illuminate\Support\Str::limit($oldText, 120) }}</del>
                                        <span aria-hidden="true">→</span>
                                    @endif
                                    <ins class="text-success no-underline">{{ \Illuminate\Support\Str::limit($newText, 120) }}</ins>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <p class="text-sm text-base-content/60">{{ __('tardis::bread.history_empty') }}</p>
            @endforelse
        </div>
    </div>
</div>
