<div class="flex flex-wrap gap-3">
    @foreach ($options as $value => $label)
        <label class="flex items-center gap-2 cursor-pointer">
            <input
                type="radio"
                name="{{ $name }}"
                value="{{ $value }}"
                @if($value === $value) checked @endif
                class="radio radio-sm radio-primary"
                {!! $disabled ? 'disabled' : '' !!}
                {!! $readonly ? 'readonly' : '' !!}
            />
            <span class="label-text">{{ $label }}</span>
        </label>
    @endforeach
</div>
