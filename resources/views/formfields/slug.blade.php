<input
    type="text"
    name="{{ $name }}"
    value="{{ $value }}"
    placeholder="{{ $placeholder ?? '' }}"
    class="input input-bordered w-full"
    {!! $disabled ? 'disabled' : '' !!}
    {!! $readonly ? 'readonly' : '' !!}
/>
<p class="text-xs text-base-content/50 mt-1">{{ __('tardis::fields.slug_will_be_auto_generated_from_4e67') }}</p>
