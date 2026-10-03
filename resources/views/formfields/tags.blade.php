<input type="text" id="{{ $id }}" wire:model="{{ $model }}" @disabled($disabled) @readonly($readonly) list="{{ $id }}_suggestions" placeholder="{{ $placeholder ?: __('tardis::bread.comma_separated') }}" class="input w-full" @foreach ($extraAttributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach />
@if ($suggestions)
    <datalist id="{{ $id }}_suggestions">
        @foreach ($suggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>
@endif
