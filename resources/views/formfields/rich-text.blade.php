<textarea
    id="{{ $id }}"
    wire:model="{{ $model }}"
    @disabled($disabled) @readonly($readonly)
    placeholder="{{ $placeholder }}"
    rows="8"
    class="textarea w-full font-mono text-sm"
    data-rich-text
    @foreach ($extraAttributes as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach
></textarea>
