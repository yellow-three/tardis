<input
    type="time"
    name="{{ $name }}"
    value="{{ $value }}"
    class="input input-bordered w-full"
    {!! $disabled ? 'disabled' : '' !!}
    {!! $readonly ? 'readonly' : '' !!}
/>
