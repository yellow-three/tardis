<select
    id="{{ $id }}"
    wire:model="{{ $model }}"
    multiple
    size="{{ max(2, min(count($options), 6)) }}"
    @disabled($disabled) @readonly($readonly)
    class="select w-full h-auto"
    @foreach ($extraAttributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach
>
    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
    @endforeach
</select>
