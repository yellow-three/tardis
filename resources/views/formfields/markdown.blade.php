<textarea
    name="{{ $name }}"
    class="textarea textarea-bordered w-full font-mono text-sm"
    rows="10"
    {!! $disabled ? 'disabled' : '' !!}
    {!! $readonly ? 'readonly' : '' !!}
>{{ $value }}</textarea>
