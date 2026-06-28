<div class="flex gap-6">
    <!-- Ana İçerik -->
    <div class="flex-1 min-w-0">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Media Library</h1>
                <p class="text-sm text-base-content/60 mt-1">Manage your files and folders</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('showNewDirModal', true)" class="btn btn-outline btn-sm gap-1">
                    <x-tardis::icon name="folder" class="w-4 h-4" />
                    Create folder
                </button>
                <label class="btn btn-primary btn-sm cursor-pointer gap-1">
                    <x-tardis::icon name="plus" class="w-4 h-4" />
                    Upload
                    <input type="file" wire:model.live="newUploads" class="hidden" multiple />
                </label>
            </div>
        </div>

        @if (session('message'))
            <div class="alert alert-success mb-4">
                <x-tardis::icon name="check-circle" class="w-5 h-5" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        @if (!empty($newUploads))
            <div class="alert alert-info mb-4">
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
                            <span class="font-medium">{{ $crumb['label'] }}</span>
                        @else
                            <a wire:click="navigateTo('{{ $crumb['path'] }}')" class="link link-hover">{{ $crumb['label'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- Toolbar -->
        <div class="flex items-center gap-3 mb-4">
            <div class="flex-1">
                <label class="input input-bordered input-sm w-full">
                    <x-tardis::icon name="magnifying-glass" class="w-4 h-4 opacity-50" />
                    <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="Search files and folders..." />
                </label>
            </div>
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-outline btn-sm gap-1">
                    <x-tardis::icon name="folder" class="w-4 h-4" />
                    Filter
                </button>
                <div tabindex="0" class="dropdown-content z-10 mt-2 w-64 card card-sm bg-base-100 shadow">
                    <div class="card-body p-4 gap-3">
                        <div class="form-control">
                            <label class="label"><span class="label-text text-xs">Date</span></label>
                            <select wire:model.live="dateFilter" class="select select-bordered select-sm">
                                <option value="">Any time</option>
                                <option value="today">Today</option>
                                <option value="week">This week</option>
                                <option value="month">This month</option>
                                <option value="year">This year</option>
                            </select>
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text text-xs">Size</span></label>
                            <select wire:model.live="sizeFilter" class="select select-bordered select-sm">
                                <option value="">Any size</option>
                                <option value="small">&lt; 1 MB</option>
                                <option value="medium">1-10 MB</option>
                                <option value="large">10-100 MB</option>
                                <option value="xlarge">&gt; 100 MB</option>
                            </select>
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text text-xs">Type</span></label>
                            <select wire:model.live="mimeTypeFilter" class="select select-bordered select-sm">
                                <option value="">All types</option>
                                <option value="image">Images</option>
                                <option value="video">Videos</option>
                                <option value="application">Documents</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-outline btn-sm gap-1">
                    <x-tardis::icon name="table-cells" class="w-4 h-4" />
                    Sort
                </button>
                <div tabindex="0" class="dropdown-content z-10 mt-2 w-52 card card-sm bg-base-100 shadow">
                    <div class="card-body p-2">
                        <button wire:click="$set('sortBy', 'name')" class="btn btn-sm justify-start {{ $sortBy === 'name' ? 'btn-active' : 'btn-ghost' }}">
                            Sort by name
                        </button>
                        <button wire:click="$set('sortBy', 'updated')" class="btn btn-sm justify-start {{ $sortBy === 'updated' ? 'btn-active' : 'btn-ghost' }}">
                            Sort by updated
                        </button>
                        <button wire:click="$set('sortBy', 'size')" class="btn btn-sm justify-start {{ $sortBy === 'size' ? 'btn-active' : 'btn-ghost' }}">
                            Sort by size
                        </button>
                        <button wire:click="$set('sortBy', 'type')" class="btn btn-sm justify-start {{ $sortBy === 'type' ? 'btn-active' : 'btn-ghost' }}">
                            Sort by type
                        </button>
                    </div>
                </div>
            </div>
            <button wire:click="$set('viewMode', $viewMode === 'grid' ? 'list' : 'grid')" class="btn btn-ghost btn-sm">
                <x-tardis::icon name="{{ $viewMode === 'grid' ? 'bars-3' : 'table-cells' }}" class="w-4 h-4" />
            </button>
        </div>

        <!-- Results Header -->
        <div class="flex items-center justify-between py-2">
            <span class="text-sm text-base-content/60">
                ALL RESULTS &middot; {{ count($files) }}
            </span>
            @if (!empty($files))
                <button wire:click="{{ empty($selectedFiles) ? 'selectAll' : 'deselectAll' }}" class="text-sm link link-hover">
                    {{ empty($selectedFiles) ? 'Select all '.count($files) : 'Deselect all' }}
                </button>
            @endif
        </div>

        <!-- Bulk Actions -->
        @if (!empty($selectedFiles))
            <div class="flex items-center gap-3 mb-4 px-4 py-3 bg-primary/5 border border-primary/20 rounded-lg">
                <span class="text-sm font-medium">{{ count($selectedFiles) }} file(s) selected</span>
                <div class="ml-auto flex gap-2">
                    <button wire:click="downloadSelected" class="btn btn-primary btn-sm gap-1">
                        <x-tardis::icon name="folder" class="w-4 h-4" /> Download
                    </button>
                    <button wire:click="bulkDelete" class="btn btn-error btn-sm gap-1">
                        <x-tardis::icon name="x-mark" class="w-4 h-4" /> Delete
                    </button>
                    <button wire:click="deselectAll" class="btn btn-ghost btn-sm">Clear</button>
                </div>
            </div>
        @endif

        <!-- Files Content -->
        @if (empty($files))
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body text-center py-16">
                    <div class="w-24 h-24 mx-auto bg-base-200 rounded-box flex items-center justify-center">
                        <x-tardis::icon name="folder" class="w-12 h-12 opacity-30" />
                    </div>
                    <p class="mt-4 text-lg font-medium">
                        @if ($searchQuery || $mimeTypeFilter || $dateFilter || $sizeFilter)
                            No matching files
                        @else
                            No files found
                        @endif
                    </p>
                    <p class="text-sm text-base-content/60 mt-1">
                        @if ($searchQuery || $mimeTypeFilter || $dateFilter || $sizeFilter)
                            Try adjusting your search or filters
                        @else
                            Upload files to get started
                        @endif
                    </p>
                    @if ($searchQuery || $mimeTypeFilter || $dateFilter || $sizeFilter)
                        <button wire:click="$set('searchQuery', ''); $set('mimeTypeFilter', ''); $set('dateFilter', ''); $set('sizeFilter', '')" class="btn btn-ghost btn-sm mt-4">
                            Clear filters
                        </button>
                    @endif
                </div>
            </div>
        @elseif ($viewMode === 'grid')
            <!-- Grid View -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                @foreach ($files as $file)
                    <div class="card card-sm bg-base-100 shadow cursor-pointer transition-all hover:shadow-md {{ in_array($file['relative_path'], $selectedFiles) ? 'ring-2 ring-primary' : '' }}"
                         wire:click="@if ($file['type'] === 'directory') navigateTo('{{ $file['relative_path'] }}') @else toggleSelect('{{ $file['relative_path'] }}') @endif">
                        <div class="relative">
                            <!-- Checkbox -->
                            <div class="absolute top-2 left-2 z-10">
                                <input type="checkbox" class="checkbox checkbox-xs checkbox-primary"
                                       {{ in_array($file['relative_path'], $selectedFiles) ? 'checked' : '' }}
                                       wire:click.stop="toggleSelect('{{ $file['relative_path'] }}')" />
                            </div>
                            <!-- Preview -->
                            <div class="aspect-video bg-base-200 flex items-center justify-center overflow-hidden rounded-t-box">
                                @if ($file['type'] === 'directory')
                                    <x-tardis::icon name="folder" class="w-12 h-12 text-primary" />
                                @elseif (str_starts_with($file['type'], 'image/'))
                                    <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="w-full h-full object-cover" loading="lazy" />
                                @else
                                    <x-tardis::icon name="document-text" class="w-10 h-10 opacity-30" />
                                @endif
                            </div>
                        </div>
                        <div class="card-body p-2">
                            <p class="text-xs font-medium truncate" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- List View -->
            <div class="card bg-base-100 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th class="w-10"></th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Modified</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($files as $file)
                                <tr class="{{ in_array($file['relative_path'], $selectedFiles) ? 'bg-primary/5' : '' }}">
                                    <td>
                                        <input type="checkbox" class="checkbox checkbox-xs"
                                               {{ in_array($file['relative_path'], $selectedFiles) ? 'checked' : '' }}
                                               wire:click="toggleSelect('{{ $file['relative_path'] }}')" />
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            @if ($file['type'] === 'directory')
                                                <x-tardis::icon name="folder" class="w-5 h-5 text-primary" />
                                            @elseif (str_starts_with($file['type'], 'image/'))
                                                <x-tardis::icon name="photo" class="w-5 h-5 opacity-50" />
                                            @else
                                                <x-tardis::icon name="document-text" class="w-5 h-5 opacity-50" />
                                            @endif
                                            @if ($file['type'] === 'directory')
                                                <a wire:click.stop="navigateTo('{{ $file['relative_path'] }}')" class="link link-hover font-medium">
                                                    {{ $file['name'] }}
                                                </a>
                                            @else
                                                <span class="font-medium">{{ $file['name'] }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-sm opacity-60">{{ $file['type'] !== 'directory' ? pathinfo($file['name'], PATHINFO_EXTENSION) : 'folder' }}</td>
                                    <td class="text-sm opacity-60">{{ $file['type'] !== 'directory' ? $this->formatSize($file['size']) : '-' }}</td>
                                    <td class="text-sm opacity-60">
                                        {{ $file['last_modified'] ? \Carbon\Carbon::createFromTimestamp($file['last_modified'])->format('M d, Y') : '-' }}
                                    </td>
                                    <td>
                                        <div class="dropdown dropdown-end">
                                            <button tabindex="0" class="btn btn-ghost btn-xs">
                                                <x-tardis::icon name="cog-6-tooth" class="w-4 h-4" />
                                            </button>
                                            <div tabindex="0" class="dropdown-content z-10 mt-1 w-40 card card-sm bg-base-100 shadow">
                                                <ul class="menu p-2">
                                                    <li><a wire:click.stop="showFileInfo('{{ $file['relative_path'] }}')"><x-tardis::icon name="information-circle" class="w-4 h-4" /> Info</a></li>
                                                    <li><a wire:click.stop="confirmRename('{{ $file['relative_path'] }}')"><x-tardis::icon name="pencil-square" class="w-4 h-4" /> Rename</a></li>
                                                    <li><a wire:click.stop="confirmDelete('{{ $file['relative_path'] }}')" class="text-error"><x-tardis::icon name="x-mark" class="w-4 h-4" /> Delete</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <!-- Right Sidebar: File Info -->
    @if ($showInfoModal && $infoFile)
        <div class="w-80 flex-shrink-0">
            <div class="card bg-base-100 shadow-sm sticky top-20">
                <div class="card-body p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-sm">File details</h3>
                        <button wire:click="$set('showInfoModal', false)" class="btn btn-ghost btn-xs">
                            <x-tardis::icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>

                    @if (str_starts_with($infoFile['mime_type'] ?? '', 'image/'))
                        <div class="rounded-lg overflow-hidden bg-base-200 mb-4">
                            <img src="{{ $infoFile['url'] }}" alt="{{ $infoFile['name'] }}" class="w-full object-cover max-h-48" />
                        </div>
                    @endif

                    <h4 class="font-medium">{{ $infoFile['name'] }}</h4>
                    <p class="text-sm text-base-content/60">
                        {{ strtoupper(pathinfo($infoFile['name'], PATHINFO_EXTENSION)) }} &middot; {{ $this->formatSize($infoFile['size'] ?? 0) }}
                    </p>

                    <div class="divider my-2"></div>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Author</span>
                            <span>{{ $infoFile['author'] ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Uploaded</span>
                            <span>{{ $infoFile['last_modified'] ? \Carbon\Carbon::createFromTimestamp($infoFile['last_modified'])->format('M d, Y \a\t g:i A') : '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Modified</span>
                            <span>{{ $infoFile['last_modified'] ? \Carbon\Carbon::createFromTimestamp($infoFile['last_modified'])->format('M d, Y \a\t g:i A') : '-' }}</span>
                        </div>
                    </div>

                    <div class="divider my-2"></div>

                    <div class="space-y-2">
                        <input type="text" placeholder="Add tags..." class="input input-bordered input-sm w-full" />
                        <button class="btn btn-primary btn-sm w-full">
                            <x-tardis::icon name="plus" class="w-4 h-4" /> Add tag
                        </button>
                    </div>

                    <div class="divider my-2"></div>

                    <div class="flex flex-wrap gap-2">
                        <button class="btn btn-outline btn-sm flex-1">Move</button>
                        <button wire:click="confirmRename('{{ $infoFile['relative_path'] }}')" class="btn btn-outline btn-sm flex-1">Rename</button>
                        <button class="btn btn-outline btn-sm flex-1">Regenerate</button>
                        <button wire:click="confirmDelete('{{ $infoFile['relative_path'] }}')" class="btn btn-error btn-sm flex-1">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Create Folder Modal -->
    @if ($showNewDirModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-1">Create new folder</h3>
                <p class="text-sm text-base-content/60 mb-4">Enter a name for the new folder</p>
                <form wire:submit="createDirectory" class="space-y-4">
                    <input type="text" wire:model="newDirectoryName" class="input input-bordered w-full" placeholder="Folder name" autofocus />
                    @error('newDirectoryName')
                        <span class="text-error text-sm">{{ $message }}</span>
                    @enderror
                </form>
                <div class="modal-action">
                    <button wire:click="$set('showNewDirModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="createDirectory" class="btn btn-primary">Create folder</button>
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
                <h3 class="font-bold text-lg mb-1">Rename</h3>
                <p class="text-sm text-base-content/60 mb-4">Enter a new name for this item</p>
                <form wire:submit="renameFile" class="space-y-4">
                    <input type="text" wire:model="renameNewName" class="input input-bordered w-full" autofocus />
                    @error('renameNewName')
                        <span class="text-error text-sm">{{ $message }}</span>
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

    <!-- Delete Confirm Modal -->
    @if ($showDeleteModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-1">Delete file</h3>
                <p class="text-sm text-base-content/60 mb-4">Are you sure you want to delete <strong>{{ $deletePath }}</strong>? This action cannot be undone.</p>
                <div class="modal-action">
                    <button wire:click="$set('showDeleteModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="deleteFile" class="btn btn-error">Delete file</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showDeleteModal', false)">close</button>
            </form>
        </dialog>
    @endif
</div>
