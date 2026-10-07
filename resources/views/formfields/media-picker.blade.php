<div>
    {{-- media-picked is dispatched by the embedded picker; only this field's
         model may receive it, otherwise every picker on the page would write
         into every field. --}}
    <span x-on:media-picked.window="if ($event.detail.model === '{{ $model }}') $wire.set('{{ $model }}', $event.detail.value)"></span>

    <div class="flex gap-2 items-center">
        <input
            type="text"
            id="{{ $id }}"
            wire:model="{{ $model }}"
            placeholder="{{ __('tardis::settings.media_path_or_url') }}"
            class="input flex-1"
            @disabled($disabled) @readonly($readonly || $multiple)
            @foreach ($extraAttributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach
        />
        <button
            type="button"
            class="btn btn-outline btn-square"
            title="{{ __('tardis::settings.browse_media') }}"
            @disabled($disabled || $readonly)
            x-on:click="$dispatch('media-picker-open', { model: '{{ $model }}', value: $wire.get('{{ $model }}') })"
        >
            <x-tardis::icon name="folder" class="w-4 h-4" />
        </button>
        <button
            type="button"
            class="btn btn-ghost btn-square"
            title="{{ __('tardis::media.clear') }}"
            @disabled($disabled || $readonly)
            x-on:click="$wire.set('{{ $model }}', null)"
        >
            <x-tardis::icon name="x-mark" class="w-4 h-4" />
        </button>
    </div>

    @if (! empty($mimes))
        <span class="text-base-content/60 text-xs">{{ __('tardis::fields.allowed_types', ['types' => implode(', ', $mimes)]) }}</span>
    @endif
    @if ($max > 1 || $min > 1)
        <span class="text-base-content/60 text-xs">
            {{ __('tardis::fields.limits', ['min' => $min, 'max' => $max]) }}
        </span>
    @endif

    <livewire:tardis::pages.media-picker
        wire:key="media-picker-{{ $id }}"
        :model="$model"
        :mimes="$mimes"
        :max="$max"
        :min="$min"
        :showFolders="$showFolders"
    />
</div>
