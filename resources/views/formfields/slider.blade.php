<div class="flex items-center gap-4">
    <input
        type="range"
        name="{{ $name }}"
        min="{{ $min }}"
        max="{{ $max }}"
        step="{{ $step }}"
        value="{{ $value }}"
        class="range range-primary flex-1"
        {!! $disabled ? 'disabled' : '' !!}
        {!! $readonly ? 'readonly' : '' !!}
    />
    <span class="text-sm font-mono w-12 text-right">{{ $value }}</span>
</div>
