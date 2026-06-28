<input
    type="{{ $withTime ? 'datetime-local' : 'date' }}"
    name="{{ $name }}"
    value="{{ $value }}"
    class="input input-bordered w-full"
    {!! $disabled ? 'disabled' : '' !!}
    {!! $readonly ? 'readonly' : '' !!}
/>
