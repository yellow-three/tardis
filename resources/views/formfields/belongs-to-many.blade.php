<select
    name="{{ $name }}[]"
    multiple
    class="select select-bordered w-full"
    {!! $disabled ? 'disabled' : '' !!}
    {!! $readonly ? 'readonly' : '' !!}
>
    @if(is_array($value))
        @foreach($value as $id)
            <option value="{{ $id }}" selected>{{ $id }}</option>
        @endforeach
    @endif
</select>
<p class="text-xs text-base-content/50 mt-1">{{ __('tardis::fields.hold_ctrl_cmd_to_select_multiple') }}</p>
