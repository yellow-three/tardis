<div class="grid grid-cols-2 gap-4">
    <label class="form-control">
        <span class="label-text text-xs">Name</span>
        <input type="text" wire:model="{{ $bind }}.name" class="input input-bordered input-sm" placeholder="e.g. sku" />
    </label>
    <label class="form-control">
        <span class="label-text text-xs">Type</span>
        <select wire:model="{{ $bind }}.type" class="select select-bordered select-sm">
            @foreach ($types as $columnType)
                <option value="{{ $columnType }}">{{ $columnType }}</option>
            @endforeach
        </select>
    </label>
    <label class="form-control">
        <span class="label-text text-xs">Length</span>
        <input type="text" wire:model="{{ $bind }}.length" class="input input-bordered input-sm" placeholder="255" />
    </label>
    <label class="form-control">
        <span class="label-text text-xs">Default</span>
        <input type="text" wire:model="{{ $bind }}.default" class="input input-bordered input-sm" placeholder="null for NULL" />
    </label>
    <label class="label cursor-pointer col-span-2">
        <span class="label-text text-xs">Nullable</span>
        <input type="checkbox" wire:model="{{ $bind }}.nullable" class="toggle toggle-sm toggle-primary" />
    </label>
</div>