<div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Media</h1>
            <p class="text-base-content/60 mt-1">Manage your media files</p>
        </div>

        <div class="flex gap-2">
            <button wire:click="$set('showNewDirModal', true)" class="btn btn-outline gap-2">
                <x-tardis::icon name="folder" class="w-4 h-4" />
                New Folder
            </button>
            <label class="btn btn-primary gap-2 cursor-pointer">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                Upload
                <input type="file" wire:model.live="newUploads" class="hidden" multiple />
            </label>
        </div>
    </div>

    @if (session('message'))
        <div class="alert alert-success mb-4 shadow-sm">
            <x-tardis::icon name="check-circle" class="w-5 h-5" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if (!empty($newUploads))
        <div class="alert alert-info mb-4 shadow-sm">
            <span class="loading loading-spinner loading-sm"></span>
            <span>Uploading {{ is_array($newUploads) ? count($newUploads).' files' : '1 file' }}...</span>
        </div>
    @endif

    <!-- Breadcrumbs -->
    <div class="text-sm breadcrumbs mb-4">
        <ul>
            @foreach ($this->getBreadcrumbs() as $crumb)
                <li>
                    @if ($loop->last)
                        <span class="font-semibold">{{ $crumb['label'] }}</span>
                    @else
                        <a wire:click="navigateTo('{{ $crumb['path'] }}')" class="link link-hover">{{ $crumb['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    <!-- Search & Filter -->
    <div class="flex flex-col gap-3 mb-4">
        <div class="flex items-center gap-3">
            <div class="join flex-1">
                <div class="join-item flex-1 relative">
                    <x-tardis::icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 opacity-40" />
                    <input type="text" wire:model.live.debounce.300ms="searchQuery" class="input input-bordered w-full pl-10" placeholder="Search files..." />
                </div>
            </div>

            <div class="flex gap-2">
                <button wire:click="$set('viewMode', 'grid')" class="btn btn-ghost btn-sm {{ $viewMode === 'grid' ? 'btn-active' : '' }}">
                    <x-tardis::icon name="table-cells" class="w-4 h-4" />
                </button>
                <button wire:click="$set('viewMode', 'list')" class="btn btn-ghost btn-sm {{ $viewMode === 'list' ? 'btn-active' : '' }}">
                    <x-tardis::icon name="bars-3" class="w-4 h-4" />
                </button>
            </div>
        </div>

        @php $categories = $this->getMimeTypeCategories(); @endphp
        @if (!empty($categories))
            <div class="flex items-center gap-2 flex-wrap">
                <button wire:click="$set('mimeTypeFilter', '')"
                        class="btn btn-xs {{ $mimeTypeFilter === '' ? 'btn-primary' : 'btn-ghost' }}">
                    All
                </button>
                @foreach ($categories as $cat => $count)
                    <button wire:click="$set('mimeTypeFilter', '{{ $cat }}')"
                            class="btn btn-xs {{ $mimeTypeFilter === $cat ? 'btn-primary' : 'btn-ghost' }}">
                        {{ ucfirst($cat) }}
                        <span class="opacity-60">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Bulk Actions -->
    @if (!empty($selectedFiles))
        <div class="flex items-center gap-2 mb-4 px-3 py-2 bg-primary/5 rounded-box">
            <span class="text-sm font-medium">{{ count($selectedFiles) }} selected</span>
            <button wire:click="downloadSelected" class="btn btn-ghost btn-xs gap-1">
                <x-tardis::icon name="folder" class="w-3 h-3" /> Download
            </button>
            <button wire:click="bulkDelete" class="btn btn-ghost btn-xs gap-1 text-error">
                <x-tardis::icon name="x-mark" class="w-3 h-3" /> Delete
            </button>
            <button wire:click="deselectAll" class="btn btn-ghost btn-xs">Clear</button>
        </div>
    @endif

    <!-- Select All (visible when files exist) -->
    @if (!empty($files) && empty($selectedFiles))
        <div class="mb-2">
            <label class="cursor-pointer flex items-center gap-2 text-sm">
                <input type="checkbox" class="checkbox checkbox-xs" onchange="if(this.checked) @this.selectAll(); else @this.deselectAll();" />
                Select all
            </label>
        </div>
    @endif

    <!-- Files -->
    @if ($currentPath !== '')
        <div class="mb-4">
            <button wire:click="goToParent" class="btn btn-ghost btn-sm gap-2">
                <x-tardis::icon name="folder" class="w-4 h-4" />
                ..
            </button>
        </div>
    @endif

    @if (empty($files))
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body text-center py-12">
                <x-tardis::icon name="folder" class="w-16 h-16 mx-auto opacity-20" />
                <h3 class="text-lg font-semibold mt-4">
                    @if ($searchQuery || $mimeTypeFilter)
                        No matching files
                    @else
                        No files found
                    @endif
                </h3>
                <p class="text-base-content/60 mt-2">
                    @if ($searchQuery && $mimeTypeFilter)
                        No {{ $mimeTypeFilter }} files matching "{{ $searchQuery }}"
                    @elseif ($searchQuery)
                        No files matching "{{ $searchQuery }}"
                    @elseif ($mimeTypeFilter)
                        No {{ $mimeTypeFilter }} files in this directory
                    @else
                        Upload files or create a new folder
                    @endif
                </p>
                @if ($searchQuery || $mimeTypeFilter)
                    <button wire:click="$set('searchQuery', ''); $set('mimeTypeFilter', '')" class="btn btn-ghost btn-sm mt-2">
                        Clear filters
                    </button>
                @endif
            </div>
        </div>
    @elseif ($viewMode === 'grid')
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach ($files as $file)
                <div class="card bg-base-100 shadow-sm cursor-pointer hover:shadow-md transition-shadow relative group {{ in_array($file['relative_path'], $selectedFiles) ? 'ring-2 ring-primary' : '' }}"
                     wire:click="@if ($file['type'] === 'directory') navigateTo('{{ $file['relative_path'] }}') @else toggleSelect('{{ $file['relative_path'] }}') @endif">
                    <figure class="px-4 pt-4">
                        @if ($file['type'] === 'directory')
                            <div class="w-full h-24 rounded-lg bg-base-200 flex items-center justify-center">
                                <x-tardis::icon name="folder" class="w-12 h-12 text-primary" />
                            </div>
                        @elseif (str_starts_with($file['type'], 'image/'))
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="rounded-lg h-24 w-full object-cover" />
                        @else
                            <div class="w-full h-24 rounded-lg bg-base-200 flex items-center justify-center">
                                <x-tardis::icon name="document-text" class="w-12 h-12 opacity-30" />
                            </div>
                        @endif
                    </figure>
                    <div class="card-body p-3">
                        <p class="text-xs truncate font-medium" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                        @if ($file['type'] !== 'directory')
                            <p class="text-xs opacity-50">{{ number_format($file['size'] / 1024, 1) }} KB</p>
                        @endif
                    </div>
                    @if ($file['type'] !== 'directory')
                        <button wire:click.stop="showFileInfo('{{ $file['relative_path'] }}')"
                                class="absolute top-2 right-2 btn btn-ghost btn-xs opacity-0 group-hover:opacity-100 transition-opacity"
                                title="File info">
                            <x-tardis::icon name="information-circle" class="w-4 h-4" />
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="card bg-base-100 shadow-sm">
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr class="{{ in_array($file['relative_path'], $selectedFiles) ? 'bg-primary/10' : '' }}">
                                <td>
                                    <input type="checkbox" class="checkbox checkbox-sm"
                                           {{ in_array($file['relative_path'], $selectedFiles) ? 'checked' : '' }}
                                           wire:click="toggleSelect('{{ $file['relative_path'] }}')" />
                                </td>
                                <td>
                                    @if ($file['type'] === 'directory')
                                        <a wire:click="navigateTo('{{ $file['relative_path'] }}')" class="link link-hover font-medium flex items-center gap-2">
                                            <x-tardis::icon name="folder" class="w-4 h-4 text-primary" />
                                            {{ $file['name'] }}
                                        </a>
                                    @else
                                        <span class="font-medium">{{ $file['name'] }}</span>
                                    @endif
                                </td>
                                <td class="text-sm opacity-60">{{ $file['type'] }}</td>
                                <td class="text-sm opacity-60">{{ $file['type'] !== 'directory' ? number_format($file['size'] / 1024, 1) . ' KB' : '-' }}</td>
                                <td class="text-right">
                                    <div class="dropdown dropdown-end">
                                        <button tabindex="0" class="btn btn-ghost btn-xs">
                                            <x-tardis::icon name="cog-6-tooth" class="w-4 h-4" />
                                        </button>
                                        <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-40 z-10">
                                            <li><a wire:click="showFileInfo('{{ $file['relative_path'] }}')">
                                                <x-tardis::icon name="information-circle" class="w-4 h-4" /> Info
                                            </a></li>
                                            <li><a wire:click="confirmRename('{{ $file['relative_path'] }}')">
                                                <x-tardis::icon name="pencil-square" class="w-4 h-4" /> Rename
                                            </a></li>
                                            <li><a wire:click="confirmDelete('{{ $file['relative_path'] }}')">
                                                <x-tardis::icon name="x-mark" class="w-4 h-4 text-error" /> Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- New Directory Modal -->
    @if ($showNewDirModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Create New Folder</h3>
                <form wire:submit="createDirectory" class="py-4">
                    <input type="text" wire:model="newDirectoryName" class="input input-bordered w-full" placeholder="Folder name" autofocus />
                    @error('newDirectoryName')
                        <label class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </label>
                    @enderror
                </form>
                <div class="modal-action">
                    <button wire:click="$set('showNewDirModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="createDirectory" class="btn btn-primary">Create</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showNewDirModal', false)">close</button>
            </form>
        </dialog>
    @endif

    <!-- Rename Modal -->
    @if ($showRenameModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Rename</h3>
                <form wire:submit="renameFile" class="py-4">
                    <input type="text" wire:model="renameNewName" class="input input-bordered w-full" autofocus />
                    @error('renameNewName')
                        <label class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </label>
                    @enderror
                </form>
                <div class="modal-action">
                    <button wire:click="$set('showRenameModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="renameFile" class="btn btn-primary">Rename</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showRenameModal', false)">close</button>
            </form>
        </dialog>
    @endif

    <!-- Delete Modal -->
    @if ($showDeleteModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Delete File</h3>
                <p class="py-4">Are you sure you want to delete <strong>{{ $deletePath }}</strong>?</p>
                <div class="modal-action">
                    <button wire:click="$set('showDeleteModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="deleteFile" class="btn btn-error">Delete</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showDeleteModal', false)">close</button>
            </form>
        </dialog>
    @endif

    <!-- File Info Modal -->
    @if ($showInfoModal && $infoFile)
        <dialog class="modal modal-open">
            <div class="modal-box max-w-lg">
                <h3 class="font-bold text-lg flex items-center gap-2">
                    <x-tardis::icon name="information-circle" class="w-5 h-5" />
                    File Info
                </h3>

                <div class="py-4 space-y-4">
                    @if (str_starts_with($infoFile['mime_type'] ?? '', 'image/'))
                        <div class="rounded-box overflow-hidden bg-base-200">
                            <img src="{{ $infoFile['url'] }}" alt="{{ $infoFile['name'] }}" class="max-h-48 w-full object-contain" />
                        </div>
                    @endif

                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td class="font-medium text-sm w-32">Name</td>
                                <td class="text-sm break-all">{{ $infoFile['name'] }}</td>
                            </tr>
                            <tr>
                                <td class="font-medium text-sm">Type</td>
                                <td class="text-sm">{{ $infoFile['mime_type'] ?? 'directory' }}</td>
                            </tr>
                            <tr>
                                <td class="font-medium text-sm">Size</td>
                                <td class="text-sm">
                                    @php
                                        $size = $infoFile['size'] ?? 0;
                                        if ($size > 1048576) {
                                            echo number_format($size / 1048576, 2).' MB';
                                        } elseif ($size > 1024) {
                                            echo number_format($size / 1024, 1).' KB';
                                        } else {
                                            echo $size.' B';
                                        }
                                    @endphp
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium text-sm">Path</td>
                                <td class="text-sm font-mono text-xs break-all">{{ $infoFile['relative_path'] }}</td>
                            </tr>
                            @if ($infoFile['last_modified'] ?? null)
                                <tr>
                                    <td class="font-medium text-sm">Modified</td>
                                    <td class="text-sm">{{ \Carbon\Carbon::createFromTimestamp($infoFile['last_modified'])->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>

                    @if (str_starts_with($infoFile['mime_type'] ?? '', 'image/'))
                        <div class="flex gap-2">
                            <a href="{{ $infoFile['url'] }}" target="_blank" class="btn btn-outline btn-sm gap-1">
                                <x-tardis::icon name="arrow-down-tray" class="w-4 h-4" />
                                Download
                            </a>
                        </div>
                    @endif
                </div>

                <div class="modal-action">
                    <button wire:click="closeFileInfo" class="btn">Close</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="closeFileInfo">close</button>
            </form>
        </dialog>
    @endif
</div>