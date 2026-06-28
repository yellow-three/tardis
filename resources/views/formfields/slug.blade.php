<input
    type="text"
    name="{{ $name }}"
    value="{{ $value }}"
    placeholder="{{ $placeholder ?? '' }}"
    class="input input-bordered w-full"
    {!! $disabled ? 'disabled' : '' !!}
    {!! $readonly ? 'readonly' : '' !!}
/>
<p class="text-xs text-base-content/50 mt-1">Slug will be auto-generated from the source field</p>
