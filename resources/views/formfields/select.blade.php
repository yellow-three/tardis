<select id="{{ $id }}" wire:model="{{ $model }}" @disabled($disabled) @readonly($readonly) class="select w-full" @foreach ($extraAttributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach>
    <option value="">—</option>
    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
    @endforeach
</select>
