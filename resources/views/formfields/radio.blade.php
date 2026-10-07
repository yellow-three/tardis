<div class="flex flex-col gap-1">
    @foreach ($options as $optionValue => $optionLabel)
        <label class="label cursor-pointer justify-start gap-3">
            <input type="radio" wire:model="{{ $model }}" value="{{ $optionValue }}" @disabled($disabled) class="radio radio-sm" />
            <span class="text-base-content">{{ $optionLabel }}</span>
        </label>
    @endforeach
</div>
