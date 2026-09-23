<div>
    <x-tardis::page-header
        :title="$bread['name_plural'] ?? ucfirst($slug)"
        :description="$bread['description'] ?? 'Browse records for this resource.'"
    >
        <x-slot:action>
            <a href="{{ $this->createUrl }}" class="btn btn-primary">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                New {{ $bread['name'] ?? ucfirst($slug) }}
            </a>
        </x-slot:action>
    </x-tardis::page-header>

    <div class="card bg-base-100 shadow-sm mb-6">
        <div class="card-body">
            <div class="form-control max-w-md">
                <label class="label">
                    <span class="label-text">Search</span>
                </label>
                <input type="search" wire:model.live.debounce.300ms="search" class="input input-bordered" placeholder="Search {{ $bread['name_plural'] ?? ucfirst($slug) }}…" aria-label="Search {{ $bread['name_plural'] ?? ucfirst($slug) }}" autocomplete="off" />
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow-sm">
        <div class="overflow-x-auto">
            @if ($this->rows->isEmpty())
                <div class="card-body text-center py-12">
                    <x-tardis::icon name="table-cells" class="w-16 h-16 mx-auto opacity-30" />
                    <h3 class="text-lg font-semibold mt-4">No records found</h3>
                    <p class="text-base-content/60 mt-2">Create the first item for this resource.</p>
                </div>
            @else
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            @foreach ($this->visibleFields as $field)
                                <th>{{ $field['label'] ?? ucfirst((string) ($field['name'] ?? '')) }}</th>
                            @endforeach
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows as $row)
                            <tr>
                                @foreach ($this->visibleFields as $field)
                                    @php($fieldName = $field['name'] ?? '')
                                    <td>{{ data_get($row, $fieldName, '-') }}</td>
                                @endforeach
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/'.$row->getKey()) }}" class="btn btn-ghost btn-xs">View</a>
                                        <a href="{{ url(trim(config('tardis.admin.prefix', 'admin'), '/').'/'.$slug.'/'.$row->getKey().'/edit') }}" class="btn btn-ghost btn-xs">Edit</a>
                                        <button type="button" wire:click="delete({{ $row->getKey() }})" wire:confirm="Delete this record?" class="btn btn-ghost btn-xs text-error">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="p-4">
                    {{ $this->rows->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
