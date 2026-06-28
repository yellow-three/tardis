<div class="flex gap-6">
    <!-- Ana İçerik -->
    <div class="flex-1 min-w-0">
        <!-- Başlık -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Media Library</h1>
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

        <!-- Arama & Filtreler -->
        <div class="flex items-center gap-3 mb-4">
            <div class="flex-1 relative">
                <x-tardis::icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 opacity-40" />
                <input type="text" wire:model.live.debounce.300ms="searchQuery" class="input input-bordered w-full pl-10" placeholder="Search files or folder by name..." />
            </div>
            <button wire:click="toggleFilters" class="btn btn-outline gap-2">
                <x-tardis::icon name="folder" class="w-4 h-4" />
                Filter
            </button>
            <button wire:click="toggleSort" class="btn btn-outline gap-2">
                <x-tardis::icon name="table-cells" class="w-4 h-4" />
                Sort
            </button>
            <button wire:click="$set('viewMode', $viewMode === 'grid' ? 'list' : 'grid')" class="btn btn-ghost btn-sm">
                <x-tardis::icon name="{{ $viewMode === 'grid' ? 'bars-3' : 'table-cells' }}" class="w-4 h-4" />
            </button>
        </div>

        <!-- Filtre Paneli -->
        @if ($showFilters)
            <div class="card bg-base-100 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="flex gap-4 flex-wrap">
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
                                <option value="small">< 1 MB</option>
                                <option value="medium">1-10 MB</option>
                                <option value="large">10-100 MB</option>
                                <option value="xlarge">> 100 MB</option>
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
        @endif

        <!-- Sıralama Paneli -->
        @if ($showSort)
            <div class="card bg-base-100 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="flex gap-2">
                        <button wire:click="$set('sortBy', 'name')" class="btn btn-sm {{ $sortBy === 'name' ? 'btn-active' : 'btn-ghost' }}">
                            <x-tardis::icon name="text" class="w-4 h-4" /> Sort by name
                        </button>
                        <button wire:click="$set('sortBy', 'updated')" class="btn btn-sm {{ $sortBy === 'updated' ? 'btn-active' : 'btn-ghost' }}">
                            <x-tardis::icon name="clock" class="w-4 h-4" /> Sort by updated
                        </button>
                        <button wire:click="$set('sortBy', 'size')" class="btn btn-sm {{ $sortBy === 'size' ? 'btn-active' : 'btn-ghost' }}">
                            <x-tardis::icon name="hashtag" class="w-4 h-4" /> Sort by size
                        </button>
                        <button wire:click="$set('sortBy', 'type')" class="btn btn-sm {{ $sortBy === 'type' ? 'btn-active' : 'btn-ghost' }}">
                            <x-tardis::icon name="document-text" class="w-4 h-4" /> Sort by type
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Sonuç Sayısı & Select All -->
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-base-content/60">
                ALL RESULTS · {{ count($files) }}
            </span>
            @if (!empty($files))
                <button wire:click="{{ empty($selectedFiles) ? 'selectAll' : 'deselectAll' }}" class="text-sm text-primary hover:underline">
                    {{ empty($selectedFiles) ? 'Select all '.count($files) : 'Deselect all' }}
                </button>
            @endif
        </div>

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
            <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                @foreach ($files as $file)
                    <div class="relative cursor-pointer group {{ in_array($file['relative_path'], $selectedFiles) ? 'ring-2 ring-primary rounded-xl' : '' }}"
                         wire:click="@if ($file['type'] === 'directory') navigateTo('{{ $file['relative_path'] }}') @else toggleSelect('{{ $file['relative_path'] }}') @endif">
                        <!-- Checkbox -->
                        <div class="absolute top-2 left-2 z-10">
                            <input type="checkbox" class="checkbox checkbox-sm checkbox-primary"
                                   {{ in_array($file['relative_path'], $selectedFiles) ? 'checked' : '' }}
                                   wire:click.stop="toggleSelect('{{ $file['relative_path'] }}')" />
                        </div>
                        <!-- Önizleme -->
                        <div class="rounded-xl overflow-hidden bg-base-200">
                            @if ($file['type'] === 'directory')
                                <div class="h-28 flex items-center justify-center">
                                    <x-tardis::icon name="folder" class="w-12 h-12 text-primary" />
                                </div>
                            @elseif (str_starts_with($file['type'], 'image/'))
                                <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="h-28 w-full object-cover" loading="lazy" />
                            @else
                                <div class="h-28 flex items-center justify-center">
                                    <x-tardis::icon name="document-text" class="w-10 h-10 opacity-30" />
                                </div>
                            @endif
                        </div>
                        <!-- İsim -->
                        <p class="text-xs font-medium mt-1 px-1 truncate" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
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

        <!-- Sayfalama -->
        <div class="flex items-center justify-center mt-4">
            <div class="join">
                <button class="join-item btn btn-sm" disabled>«</button>
                <button class="join-item btn btn-sm btn-disabled">1</button>
                <button class="join-item btn btn-sm" disabled>»</button>
            </div>
        </div>
    </div>

    <!-- Sağ Panel: Dosya Bilgisi -->
    @if ($showInfoModal && $infoFile)
        <div class="w-72 flex-shrink-0">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <!-- Önizleme -->
                    @if (str_starts_with($infoFile['mime_type'] ?? '', 'image/'))
                        <div class="rounded-xl overflow-hidden bg-base-200 mb-4">
                            <img src="{{ $infoFile['url'] }}" alt="{{ $infoFile['name'] }}" class="w-full object-cover max-h-48" />
                        </div>
                    @endif

                    <!-- Dosya Adı -->
                    <h3 class="font-bold">{{ $infoFile['name'] }}</h3>
                    <p class="text-sm text-base-content/60">
                        {{ strtoupper(pathinfo($infoFile['name'], PATHINFO_EXTENSION)) }} ~ {{ $this->formatSize($infoFile['size'] ?? 0) }}
                    </p>

                    <!-- Bilgi Bölümü -->
                    <div class="mt-4">
                        <button wire:click="$set('showInfoSection', ! $showInfoSection)" class="btn btn-ghost btn-xs w-full justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide">Information</span>
                            <x-tardis::icon name="chevron-up-down" class="w-3 h-3" />
                        </button>
                        @if ($showInfoSection)
                            <div class="space-y-2 mt-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-base-content/60">Author</span>
                                    <span>{{ $infoFile['author'] ?? 'Unknown' }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-base-content/60">Uploaded</span>
                                    <span>{{ $infoFile['last_modified'] ? \Carbon\Carbon::createFromTimestamp($infoFile['last_modified'])->format('M d, Y \a\t g:i A') : '-' }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-base-content/60">Updated</span>
                                    <span>{{ $infoFile['last_modified'] ? \Carbon\Carbon::createFromTimestamp($infoFile['last_modified'])->format('M d, Y \a\t g:i A') : '-' }}</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Etiketler -->
                    <div class="mt-4">
                        <button wire:click="$set('showTagsSection', ! $showTagsSection)" class="btn btn-ghost btn-xs w-full justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide">Tags</span>
                            <x-tardis::icon name="chevron-up-down" class="w-3 h-3" />
                        </button>
                        @if ($showTagsSection)
                            <div class="mt-2">
                                <input type="text" placeholder="New tag" class="input input-bordered input-sm w-full" />
                                <button class="text-xs text-primary mt-1">Save</button>
                            </div>
                        @endif
                    </div>

                    <div class="divider"></div>

                    <!-- Aksiyon Butonları -->
                    <div class="flex flex-wrap gap-2">
                        <button class="btn btn-outline btn-sm gap-1">
                            <x-tardis::icon name="folder" class="w-4 h-4" /> Move
                        </button>
                        <button wire:click="confirmRename('{{ $infoFile['relative_path'] }}')" class="btn btn-outline btn-sm gap-1">
                            <x-tardis::icon name="pencil-square" class="w-4 h-4" /> Rename
                        </button>
                        <button class="btn btn-outline btn-sm gap-1">
                            <x-tardis::icon name="check-circle" class="w-4 h-4" /> Regenerate
                        </button>
                        <button wire:click="confirmDelete('{{ $infoFile['relative_path'] }}')" class="btn btn-error btn-sm gap-1">
                            <x-tardis::icon name="x-mark" class="w-4 h-4" /> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Toplu İşlemler (Seçim yapıldığında) -->
    @if (!empty($selectedFiles) && !$showInfoModal)
        <div class="w-72 flex-shrink-0">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="font-bold text-sm mb-4">{{ count($selectedFiles) }} file(s) selected</h3>
                    <div class="flex flex-wrap gap-2">
                        <button wire:click="downloadSelected" class="btn btn-primary btn-sm gap-1">
                            <x-tardis::icon name="folder" class="w-4 h-4" /> Download
                        </button>
                        <button wire:click="bulkDelete" class="btn btn-error btn-sm gap-1">
                            <x-tardis::icon name="x-mark" class="w-4 h-4" /> Delete
                        </button>
                    </div>
                    <button wire:click="deselectAll" class="text-xs text-primary mt-3">Deselect all</button>
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
