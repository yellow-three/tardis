<div class="flex flex-wrap gap-3">
    @foreach ($options as $value => $label)
        <label class="flex items-center gap-2 cursor-pointer">
            <input
                type="checkbox"
                name="{{ $name }}[]"
                value="{{ $value }}"
                @if(in_array($value, (array) $value)) checked @endif
                class="checkbox checkbox-sm checkbox-primary"
                {!! $disabled ? 'disabled' : '' !!}
                {!! $readonly ? 'readonly' : '' !!}
            />
            <span class="label-text">{{ $label }}</span>
        </label>
    @endforeach
</div>
