<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Edit {{ $bread['name'] ?? ucfirst($slug) }}</h1>
            <p class="text-base-content/60 mt-1">Update the selected record.</p>
        </div>
    </div>

    <form wire:submit="save" class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-5">
            @foreach ($this->fields as $field)
                @php($name = $field['name'] ?? '')
                @php($label = $field['label'] ?? ucfirst((string) $name))
                @php($type = $field['type'] ?? 'text')

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">{{ $label }}</span>
                    </label>

                    @if ($type === 'textarea')
                        <textarea wire:model="form.{{ $name }}" class="textarea textarea-bordered" rows="4"></textarea>
                    @elseif ($type === 'toggle')
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" wire:model="form.{{ $name }}" class="checkbox" />
                            <span class="label-text">{{ $label }}</span>
                        </label>
                    @else
                        <input type="{{ $type === 'number' ? 'number' : 'text' }}" wire:model="form.{{ $name }}" class="input input-bordered" />
                    @endif
                </div>
            @endforeach

            <div class="card-actions justify-end">
                <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug) }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </div>
    </form>
</div>
