<div class="flex items-center gap-3">
    <input type="color" id="{{ $id }}" wire:model.live="{{ $model }}" @disabled($disabled) class="h-10 w-16 cursor-pointer rounded border border-base-300 bg-base-100 p-1" />
    <input type="text" wire:model.live="{{ $model }}" @disabled($disabled) placeholder="#000000" class="input w-32 font-mono" />
</div>
