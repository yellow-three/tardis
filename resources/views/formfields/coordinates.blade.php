<div class="flex flex-wrap gap-2">
    <input
        type="number"
        step="any"
        wire:model="{{ $model }}.lat"
        placeholder="{{ __('tardis::bread.latitude') }}"
        @disabled($disabled) @readonly($readonly)
        class="input w-full flex-1"
        @foreach ($extraAttributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach
    />
    <input
        type="number"
        step="any"
        wire:model="{{ $model }}.lng"
        placeholder="{{ __('tardis::bread.longitude') }}"
        @disabled($disabled) @readonly($readonly)
        class="input w-full flex-1"
        @foreach ($extraAttributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach
    />
</div>
