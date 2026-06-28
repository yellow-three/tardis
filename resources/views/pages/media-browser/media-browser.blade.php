<div class="flex gap-6">
    <!-- Ana İçerik -->
    <div class="flex-1 min-w-0">
        <!-- Başlık & Butonlar -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Media Library</h1>
                <p class="text-base-content/60 mt-1">Manage your media files</p>
            </div>

            <div class="flex gap-2">
                <button wire:click="$set('showNewDirModal', true)" class="btn btn-outline gap-2">
                    <x-tardis::icon name="folder" class="w-4 h-4" />
                    Create folder
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

        <!-- Arama & Filtreler & Sıralama -->
        <div class="flex items-center gap-3 mb-4">
            <!-- Arama -->
            <div class="flex-1 relative">
                <x-tardis::icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 opacity-40" />
                <input type="text" wire:model.live.debounce.300ms="searchQuery" class="input input-bordered w-full pl-10" placeholder="Search files or folder by name..." />
            </div>

            <!-- Filtre Butonu -->
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-outline gap-2">
                    <x-tardis::icon name="folder" class="w-4 h-4" />
                    Filter
                </button>
                <ul tabindex="0" class="dropdown-content menu p-4 shadow bg-base-100 rounded-box w-72 z-10">
                    <li class="menu-title">Date</li>
                    <li>
                        <select wire:model.live="dateFilter" class="select select-bordered select-sm w-full">
                            <option value="">Any time</option>
                            <option value="today">Today</option>
                            <option value="week">This week</option>
                            <option value="month">This month</option>
                            <option value="year">This year</option>
                        </select>
                    </li>
                    <li class="menu-title">Size</li>
                    <li>
                        <select wire:model.live="sizeFilter" class="select select-bordered select-sm w-full">
                            <option value="">Any size</option>
                            <option value="small">< 1 MB</option>
                            <option value="medium">1-10 MB</option>
                            <option value="large">10-100 MB</option>
                            <option value="xlarge">> 100 MB</option>
                        </select>
                    </li>
                    <li class="menu-title">Type</li>
                    <li>
                        <select wire:model.live="mimeTypeFilter" class="select select-bordered select-sm w-full">
                            <option value="">All types</option>
                            <option value="image">Images</option>
                            <option value="video">Videos</option>
                            <option value="application">Documents</option>
                        </select>
                    </li>
                </ul>
            </div>

            <!-- Sıralama -->
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-outline gap-2">
                    <x-tardis::icon name="table-cells" class="w-4 h-4" />
                    Sort
                </button>
                <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-48 z-10">
                    <li><a wire:click="$set('sortBy', 'name')">
                        <x-tardis::icon name="text" class="w-4 h-4" /> Sort by name
                    </a></li>
                    <li><a wire:click="$set('sortBy', 'updated')">
                        <x-tardis::icon name="clock" class="w-4 h-4" /> Sort by updated
                    </a></li>
                    <li><a wire:click="$set('sortBy', 'size')">
                        <x-tardis::icon name="hashtag" class="w-4 h-4" /> Sort by size
                    </a></li>
                    <li><a wire:click="$set('sortBy', 'type')">
                        <x-tardis::icon name="document-text" class="w-4 h-4" /> Sort by type
                    </a></li>
                </ul>
            </div>

            <!-- Görünüm -->
            <div class="join">
                <button wire:click="$set('viewMode', 'grid')" class="join-item btn btn-sm {{ $viewMode === 'grid' ? 'btn-active' : '' }}">
                    <x-tardis::icon name="table-cells" class="w-4 h-4" />
                </button>
                <button wire:click="$set('viewMode', 'list')" class="join-item btn btn-sm {{ $viewMode === 'list' ? 'btn-active' : '' }}">
                    <x-tardis::icon name="bars-3" class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Toplu İşlemler -->
        @if (!empty($selectedFiles))
            <div class="flex items-center gap-2 mb-4 px-4 py-3 bg-primary/5 border border-primary/20 rounded-lg">
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

        <!-- Select All -->
        @if (!empty($files) && empty($selectedFiles))
            <div class="flex items-center gap-2 mb-3">
                <label class="cursor-pointer flex items-center gap-2 text-sm">
                    <input type="checkbox" class="checkbox checkbox-sm" onchange="if(this.checked) @this.selectAll(); else @this.deselectAll();" />
                    Select all {{ count($files) }} items
                </label>
            </div>
        @endif

        <!-- Klasör İçeriği -->
        @if ($currentPath !== '')
            <div class="mb-3">
                <button wire:click="goToParent" class="btn btn-ghost btn-sm gap-2">
                    <x-tardis::icon name="folder" class="w-4 h-4" />
                    ..
                </button>
            </div>
        @endif

        <!-- Dosyalar -->
        @if (empty($files))
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body text-center py-16">
                    <x-tardis::icon name="folder" class="w-20 h-20 mx-auto opacity-20" />
                    <h3 class="text-lg font-semibold mt-4">
                        @if ($searchQuery || $mimeTypeFilter || $dateFilter || $sizeFilter)
                            No matching files
                        @else
                            No files found
                        @endif
                    </h3>
                    <p class="text-base-content/60 mt-2">
                        @if ($searchQuery || $mimeTypeFilter || $dateFilter || $sizeFilter)
                            Try adjusting your filters
                        @else
                            Upload files or create a new folder
                        @endif
                    </p>
                    @if ($searchQuery || $mimeTypeFilter || $dateFilter || $sizeFilter)
                        <button wire:click="$set('searchQuery', ''); $set('mimeTypeFilter', ''); $set('dateFilter', ''); $set('sizeFilter', '')" class="btn btn-ghost btn-sm mt-2">
                            Clear filters
                        </button>
                    @endif
                </div>
            </div>
        @elseif ($viewMode === 'grid')
            <!-- Grid Görünümü -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach ($files as $file)
                    <div class="card bg-base-100 shadow-sm cursor-pointer hover:shadow-md transition-all relative group {{ in_array($file['relative_path'], $selectedFiles) ? 'ring-2 ring-primary' : '' }}"
                         wire:click="@if ($file['type'] === 'directory') navigateTo('{{ $file['relative_path'] }}') @else toggleSelect('{{ $file['relative_path'] }}') @endif">
                        <!-- Checkbox -->
                        <div class="absolute top-2 left-2 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                            <input type="checkbox" class="checkbox checkbox-sm checkbox-primary"
                                   {{ in_array($file['relative_path'], $selectedFiles) ? 'checked' : '' }}
                                   wire:click.stop="toggleSelect('{{ $file['relative_path'] }}')" />
                        </div>
                        <!-- Önizleme -->
                        <figure class="px-3 pt-3">
                            @if ($file['type'] === 'directory')
                                <div class="w-full h-20 rounded-lg bg-base-200 flex items-center justify-center">
                                    <x-tardis::icon name="folder" class="w-10 h-10 text-primary" />
                                </div>
                            @elseif (str_starts_with($file['type'], 'image/'))
                                <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="rounded-lg h-20 w-full object-cover" loading="lazy" />
                            @else
                                <div class="w-full h-20 rounded-lg bg-base-200 flex items-center justify-center">
                                    <x-tardis::icon name="document-text" class="w-8 h-8 opacity-30" />
                                </div>
                            @endif
                        </figure>
                        <!-- Bilgi -->
                        <div class="card-body p-2">
                            <p class="text-xs truncate font-medium" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                            @if ($file['type'] !== 'directory')
                                <p class="text-[10px] opacity-50">{{ $this->formatSize($file['size']) }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- Liste Görünümü -->
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
                                <th class="w-20"></th>
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
                                    <td class="text-sm opacity-60">{{ $file['type'] !== 'directory' ? $this->formatSize($file['size']) : '-' }}</td>
                                    <td class="text-sm opacity-60">
                                        @if ($file['last_modified'])
                                            {{ \Carbon\Carbon::createFromTimestamp($file['last_modified'])->format('M d, Y') }}
                                        @endif
                                    </td>
                                    <td>
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
                                                <li><a wire:click="confirmDelete('{{ $file['relative_path'] }}')" class="text-error">
                                                    <x-tardis::icon name="x-mark" class="w-4 h-4" /> Delete
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
    </div>

    <!-- Sağ Panel: Dosya Bilgisi -->
    @if ($showInfoModal && $infoFile)
        <div class="w-80 flex-shrink-0">
            <div class="card bg-base-100 shadow-sm sticky top-20">
                <div class="card-body p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-sm">File Info</h3>
                        <button wire:click="closeFileInfo" class="btn btn-ghost btn-xs">
                            <x-tardis::icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>

                    @if (str_starts_with($infoFile['mime_type'] ?? '', 'image/'))
                        <div class="rounded-lg overflow-hidden bg-base-200 mb-4">
                            <img src="{{ $infoFile['url'] }}" alt="{{ $infoFile['name'] }}" class="w-full object-contain max-h-48" />
                        </div>
                    @endif

                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-base-content/50">Name</p>
                            <p class="text-sm font-medium break-all">{{ $infoFile['name'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-base-content/50">Type</p>
                            <p class="text-sm">{{ $infoFile['mime_type'] ?? 'directory' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-base-content/50">Size</p>
                            <p class="text-sm">{{ $this->formatSize($infoFile['size'] ?? 0) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-base-content/50">Path</p>
                            <p class="text-sm font-mono text-xs break-all">{{ $infoFile['relative_path'] }}</p>
                        </div>
                        @if ($infoFile['last_modified'] ?? null)
                            <div>
                                <p class="text-xs text-base-content/50">Modified</p>
                                <p class="text-sm">{{ \Carbon\Carbon::createFromTimestamp($infoFile['last_modified'])->format('Y-m-d H:i:s') }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="divider"></div>

                    <div class="flex gap-2">
                        @if (str_starts_with($infoFile['mime_type'] ?? '', 'image/'))
                            <a href="{{ $infoFile['url'] }}" target="_blank" class="btn btn-outline btn-sm flex-1 gap-1">
                                <x-tardis::icon name="folder" class="w-4 h-4" />
                                Download
                            </a>
                        @endif
                        <button wire:click="confirmRename('{{ $infoFile['relative_path'] }}')" class="btn btn-outline btn-sm flex-1 gap-1">
                            <x-tardis::icon name="pencil-square" class="w-4 h-4" />
                            Rename
                        </button>
                        <button wire:click="confirmDelete('{{ $infoFile['relative_path'] }}')" class="btn btn-error btn-sm gap-1">
                            <x-tardis::icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Yeni Klasör Modalı -->
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

    <!-- Yeniden Adlandırma Modalı -->
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

    <!-- Silme Onay Modalı -->
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
</div>
