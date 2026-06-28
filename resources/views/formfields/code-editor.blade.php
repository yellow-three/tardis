<textarea
    name="{{ $name }}"
    class="textarea textarea-bordered w-full font-mono text-sm"
    rows="15"
    {!! $disabled ? 'disabled' : '' !!}
    {!! $readonly ? 'readonly' : '' !!}
>{{ $value }}</textarea>
<p class="text-xs text-base-content/50 mt-1">Language: {{ $language }}</p>
