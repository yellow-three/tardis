<div>
    @if (session('message'))
        <div class="alert alert-success mb-4 shadow-lg">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <h2 class="card-title text-2xl">Media Library</h2>
                <div class="flex gap-2">
                    <button wire:click="$set('viewMode', 'grid')" class="btn btn-ghost btn-sm {{ $viewMode === 'grid' ? 'btn-active' : '' }}">
                        <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                    </button>
                    <button wire:click="$set('viewMode', 'list')" class="btn btn-ghost btn-sm {{ $viewMode === 'list' ? 'btn-active' : '' }}">
                        <x-heroicon-o-list-bullet class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <h3 class="card-title text-sm mb-3">Upload New File</h3>
            <form wire:submit="upload" class="flex flex-col gap-3">
                <input
                    type="file"
                    wire:model="upload"
                    class="file-input file-input-bordered file-input-primary w-full"
                />
                <div wire:loading wire:target="upload" class="text-sm opacity-70">Uploading...</div>
                @error('upload')
                    <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                @enderror
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <select wire:model="uploadCollection" class="select select-bordered w-full">
                        <option value="default">default</option>
                        @foreach ($collections as $col)
                            <option value="{{ $col }}">{{ $col }}</option>
                        @endforeach
                    </select>
                    <input
                        type="text"
                        wire:model="altText"
                        placeholder="Alt text (optional)"
                        class="input input-bordered w-full"
                    />
                </div>
            </form>
        </div>
    </div>

    <div class="flex flex-wrap gap-3 mb-6">
        <label class="input input-bordered flex items-center gap-2 flex-1 min-w-40">
            <x-heroicon-o-magnifying-glass class="w-4 h-4 opacity-70" />
            <input type="text" class="grow" placeholder="Search media..." wire:model.live="search" />
        </label>

        <select class="select select-bordered" wire:model.live="collection">
            <option value="">All Collections</option>
            @foreach ($collections as $col)
                <option value="{{ $col }}">{{ $col }}</option>
            @endforeach
        </select>

        <select class="select select-bordered" wire:model.live="mimeType">
            <option value="">All Types</option>
            @foreach ($mimeTypes as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>

    @if (!empty($selectedIds))
        <div class="flex items-center gap-3 mb-4 p-3 bg-base-200 rounded-lg">
            <span class="text-sm font-medium">{{ count($selectedIds) }} selected</span>
            <button wire:click="bulkDelete" class="btn btn-error btn-sm" wire:confirm="Delete selected files?">
                <x-heroicon-o-trash class="w-4 h-4" /> Delete
            </button>
            <button wire:click="downloadZip" class="btn btn-primary btn-sm">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" /> Download ZIP
            </button>
            <button wire:click="$set('selectedIds', [])" class="btn btn-ghost btn-sm">Clear</button>
        </div>
    @endif

    @error('selectedIds')
        <div class="alert alert-error mb-4">{{ $message }}</div>
    @enderror

    @if ($viewMode === 'grid')
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @forelse ($media as $item)
                <div class="card bg-base-100 shadow hover:shadow-lg transition-shadow @if(in_array($item->id, $selectedIds)) ring-2 ring-primary @endif">
                    <label class="absolute top-2 left-2 z-10 cursor-pointer">
                        <input type="checkbox" wire:model.live="selectedIds" value="{{ $item->id }}" class="checkbox checkbox-primary checkbox-xs" />
                    </label>
                    <figure class="px-2 pt-2">
                        @if ($item->isImage())
                            <img src="{{ $item->thumbnail_url }}" alt="{{ $item->alt_text ?? $item->original_name }}" class="rounded-xl h-32 w-full object-cover" loading="lazy" />
                        @else
                            <div class="h-32 w-full rounded-xl bg-base-200 flex items-center justify-center">
                                <x-heroicon-o-document class="w-10 h-10 opacity-40" />
                            </div>
                        @endif
                    </figure>
                    <div class="card-body p-3">
                        <p class="text-xs truncate font-medium" title="{{ $item->original_name }}">{{ $item->original_name }}</p>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-xs opacity-60">{{ $item->formatted_size }}</span>
                            <button wire:click="confirmDelete({{ $item->id }})" class="btn btn-ghost btn-xs text-error px-1">
                                <x-heroicon-o-trash class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16 opacity-60">
                    <x-heroicon-o-photo class="w-16 h-16 mx-auto opacity-30 mb-3" />
                    <p>No media files found.</p>
                </div>
            @endforelse
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="selectedIds" value="{{ 0 }}" />
                        </th>
                        <th>Preview</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Collection</th>
                        <th class="w-20">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($media as $item)
                        <tr>
                            <td>
                                <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="selectedIds" value="{{ $item->id }}" />
                            </td>
                            <td>
                                @if ($item->isImage())
                                    <img src="{{ $item->thumbnail_url }}" alt="{{ $item->alt_text }}" class="w-12 h-12 object-cover rounded" />
                                @else
                                    <div class="w-12 h-12 rounded bg-base-200 flex items-center justify-center">
                                        <x-heroicon-o-document class="w-6 h-6 opacity-60" />
                                    </div>
                                @endif
                            </td>
                            <td class="max-w-xs truncate font-medium">{{ $item->original_name }}</td>
                            <td><span class="badge badge-ghost badge-sm">{{ $item->mime_type }}</span></td>
                            <td class="text-sm">{{ $item->formatted_size }}</td>
                            <td><span class="badge badge-outline badge-sm">{{ $item->collection }}</span></td>
                            <td>
                                <button wire:click="confirmDelete({{ $item->id }})" class="btn btn-ghost btn-xs text-error">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 opacity-60">No media files found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-6">
        {{ $media->links() }}
    </div>

    @if ($deleteId)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Delete Media</h3>
                <p class="py-4">Are you sure you want to delete this file? This action cannot be undone.</p>
                <div class="modal-action">
                    <button wire:click="cancelDelete" class="btn btn-ghost">Cancel</button>
                    <button wire:click="delete" class="btn btn-error">Delete</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelDelete">close</button>
            </form>
        </dialog>
    @endif
</div>
