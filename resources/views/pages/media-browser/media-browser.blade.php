<div class="flex gap-6">
    {{-- Main Content --}}
    <div class="flex-1 min-w-0 space-y-4">

        {{-- Header: Title & Buttons --}}
        <x-tardis::page-header :title="__('tardis::media.media_library')" :description="__('tardis::media.manage_your_media_files')">
            <x-slot:action>
                <div class="flex gap-2">
                    <button wire:click="$set('showNewDirModal', true)" class="btn btn-outline gap-2">
                        <x-tardis::icon name="folder" class="w-4 h-4" />
                        {{ __('tardis::media.create_folder') }}
                    </button>
                    <label class="btn btn-primary gap-2 cursor-pointer">
                        <x-tardis::icon name="plus" class="w-4 h-4" />
                        {{ __('tardis::media.upload') }}
                        <input type="file" wire:key="media-upload-{{ $uploadInputKey }}" wire:model.live="newUploads" class="hidden" multiple />
                    </label>
                </div>
            </x-slot:action>
        </x-tardis::page-header>

        {{-- Session Messages --}}
        @if (session('message'))
            <div class="alert alert-success">
                <x-tardis::icon name="check-circle" class="w-5 h-5" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-error">
                <x-tardis::icon name="exclamation-circle" class="w-5 h-5" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Upload Progress --}}
        @if (!empty($newUploads))
            <div class="alert alert-info">
                <span class="loading loading-spinner loading-sm"></span>
                <span>{{ trans_choice('tardis::media.uploading', is_array($newUploads) ? count($newUploads) : 1, ['count' => is_array($newUploads) ? count($newUploads) : 1]) }}</span>
            </div>
        @endif

        {{-- Breadcrumbs --}}
        <div class="flex justify-between items-center bg-base-100 p-2 rounded-xl border border-base-200">
            <div class="text-sm breadcrumbs px-2 text-base-content/70">
                <ul>
                    @foreach ($this->getBreadcrumbs() as $crumb)
                        <li>
                            @if ($loop->last)
                                <span>{{ $crumb['label'] }}</span>
                            @else
                                <a wire:click="navigateTo('{{ $crumb['path'] }}')" class="cursor-pointer hover:text-primary">{{ $crumb['label'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <button wire:click="$set('showNewDirModal', true)" class="btn btn-sm btn-primary">
                <x-tardis::icon name="folder" class="w-4 h-4" />
                {{ __('tardis::media.create_folder') }}
            </button>
        </div>

        {{-- Toolbar: Search, Filter, Sort, View Toggle --}}
        <div class="flex flex-wrap gap-2 items-center">
            {{-- Search --}}
            <div class="relative flex-1 min-w-[200px]">
                <x-tardis::icon name="magnifying-glass" class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/50" />
                <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="{{ __('tardis::media.search_files_or_folder_by_name') }}" class="input w-full pl-10 bg-base-100" />
            </div>

            {{-- Filter Dropdown --}}
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-outline bg-base-100 border-base-300 gap-2">
                    <x-tardis::icon name="funnel" class="w-4 h-4" />
                    {{ __('tardis::media.filter') }}
                </button>
                <ul tabindex="0" class="dropdown-content menu p-4 shadow bg-base-100 rounded-box w-72 z-10 border border-base-200 mt-1">
                    <li class="menu-title text-xs">{{ __('tardis::media.date') }}</li>
                    <li>
                        <select wire:model.live="dateFilter" class="select select-sm w-full">
                            <option value="">{{ __('tardis::media.any_time') }}</option>
                            <option value="today">{{ __('tardis::media.today') }}</option>
                            <option value="week">{{ __('tardis::media.this_week') }}</option>
                            <option value="month">{{ __('tardis::media.this_month') }}</option>
                            <option value="year">{{ __('tardis::media.this_year') }}</option>
                        </select>
                    </li>
                    <li class="menu-title text-xs mt-2">{{ __('tardis::media.size') }}</li>
                    <li>
                        <select wire:model.live="sizeFilter" class="select select-sm w-full">
                            <option value="">{{ __('tardis::media.any_size') }}</option>
                            <option value="small">< 1 MB</option>
                            <option value="medium">1-10 MB</option>
                            <option value="large">10-100 MB</option>
                            <option value="xlarge">> 100 MB</option>
                        </select>
                    </li>
                    <li class="menu-title text-xs mt-2">{{ __('tardis::media.type') }}</li>
                    <li>
                        <select wire:model.live="mimeTypeFilter" class="select select-sm w-full">
                            <option value="">{{ __('tardis::media.all_types') }}</option>
                            <option value="image">{{ __('tardis::media.images') }}</option>
                            <option value="video">{{ __('tardis::media.videos') }}</option>
                            <option value="application">{{ __('tardis::media.documents') }}</option>
                        </select>
                    </li>
                </ul>
            </div>

            {{-- Sort Dropdown --}}
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-outline bg-base-100 border-base-300 gap-2">
                    <x-tardis::icon name="chevron-up-down" class="w-4 h-4" />
                    {{ __('tardis::media.sort') }}
                </button>
                <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-48 z-10 border border-base-200 mt-1">
                    <li><a wire:click="$set('sortBy', 'name')" class="{{ $sortBy === 'name' ? 'menu-active' : '' }}">
                        <x-tardis::icon name="text" class="w-4 h-4" /> {{ __('tardis::media.sort_by_name') }}
                    </a></li>
                    <li><a wire:click="$set('sortBy', 'updated')" class="{{ $sortBy === 'updated' ? 'menu-active' : '' }}">
                        <x-tardis::icon name="clock" class="w-4 h-4" /> {{ __('tardis::media.sort_by_updated') }}
                    </a></li>
                    <li><a wire:click="$set('sortBy', 'size')" class="{{ $sortBy === 'size' ? 'menu-active' : '' }}">
                        <x-tardis::icon name="hashtag" class="w-4 h-4" /> {{ __('tardis::media.sort_by_size') }}
                    </a></li>
                    <li><a wire:click="$set('sortBy', 'type')" class="{{ $sortBy === 'type' ? 'menu-active' : '' }}">
                        <x-tardis::icon name="document-text" class="w-4 h-4" /> {{ __('tardis::media.sort_by_type') }}
                    </a></li>
                </ul>
            </div>

            {{-- View Toggle --}}
            <div class="join border border-base-300 rounded-lg bg-base-100">
                <button wire:click="$set('viewMode', 'grid')" class="btn btn-ghost join-item px-3 {{ $viewMode === 'grid' ? 'btn-active' : '' }}">
                    <x-tardis::icon name="table-cells" class="w-5 h-5" />
                </button>
                <button wire:click="$set('viewMode', 'list')" class="btn btn-ghost join-item px-3 {{ $viewMode === 'list' ? 'btn-active' : '' }}">
                    <x-tardis::icon name="bars-3" class="w-5 h-5" />
                </button>
            </div>
        </div>

        {{-- Results Count + Select All --}}
        @if (!empty($files) && empty($selectedFiles))
            <div class="flex justify-between items-center text-sm font-semibold text-base-content/60">
                <span>{{ __('tardis::media.all_results', ['count' => count($files)]) }}</span>
                <button wire:click="selectAll" class="hover:text-primary transition-colors">{{ __('tardis::media.select_all', ['count' => count($files)]) }}</button>
            </div>
        @endif

        {{-- Bulk Actions Bar --}}
        @if (!empty($selectedFiles))
            <div class="flex items-center gap-2 px-4 py-3 bg-primary/5 border border-primary/20 rounded-xl">
                <span class="text-sm font-medium">{{ __('tardis::media.files_selected', ['count' => count($selectedFiles)]) }}</span>
                <div class="ml-auto flex gap-2">
                    <button wire:click="downloadSelected" class="btn btn-primary btn-sm gap-1">
                        <x-tardis::icon name="arrow-down-tray" class="w-4 h-4" /> {{ __('tardis::media.download') }}
                    </button>
                    <button wire:click="bulkDelete" class="btn btn-error btn-sm gap-1">
                        <x-tardis::icon name="x-mark" class="w-4 h-4" /> {{ __('tardis::media.delete') }}
                    </button>
                    <button wire:click="deselectAll" class="btn btn-ghost btn-sm">{{ __('tardis::media.clear') }}</button>
                </div>
            </div>
        @endif

        {{-- Parent Directory --}}
        @if ($currentPath !== '')
            <div>
                <button wire:click="goToParent" class="btn btn-ghost btn-sm gap-2">
                    <x-tardis::icon name="folder" class="w-4 h-4" />
                    ..
                </button>
            </div>
        @endif

        {{-- Files --}}
        @if (empty($files))
            {{-- Empty State --}}
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body text-center py-16">
                    <x-tardis::icon name="photo" class="w-20 h-20 mx-auto text-base-content/20" />
                    <h3 class="text-lg font-semibold mt-4">
                        @if ($searchQuery || $mimeTypeFilter || $dateFilter || $sizeFilter)
                            {{ __('tardis::media.no_matching_files') }}
                        @else
                            {{ __('tardis::media.no_files_found') }}
                        @endif
                    </h3>
                    <p class="text-base-content/60 mt-2">
                        @if ($searchQuery || $mimeTypeFilter || $dateFilter || $sizeFilter)
                            {{ __('tardis::media.try_adjusting_filters') }}
                        @else
                            {{ __('tardis::media.upload_or_create_folder') }}
                        @endif
                    </p>
                    @if ($searchQuery || $mimeTypeFilter || $dateFilter || $sizeFilter)
                        <button wire:click="$set('searchQuery', ''); $set('mimeTypeFilter', ''); $set('dateFilter', ''); $set('sizeFilter', '')" class="btn btn-ghost btn-sm mt-2">
                            {{ __('tardis::media.clear_filters') }}
                        </button>
                    @endif
                </div>
            </div>
        @elseif ($viewMode === 'grid')
            {{-- Grid View (Filament-style) --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4">
                @foreach ($files as $file)
                    <div
                        @if ($file['type'] === 'directory')
                            wire:click="navigateTo('{{ $file['relative_path'] }}')"
                        @else
                            wire:click="showFileInfo('{{ $file['relative_path'] }}')"
                        @endif
                        class="group relative aspect-square rounded-2xl overflow-hidden cursor-pointer  border-2 transition-all duration-200 bg-base-100
                            {{ $showInfoModal && $infoFile && ($infoFile['relative_path'] ?? '') === $file['relative_path'] ? 'border-primary ring-2 ring-primary/30' : 'border-transparent hover:border-base-300 hover:shadow-md' }}">

                        {{-- File Preview --}}
                        @if ($file['type'] === 'directory')
                            <div class="w-full h-full flex items-center justify-center bg-base-200">
                                <x-tardis::icon name="folder" class="w-16 h-16 text-primary/60" />
                            </div>
                        @elseif (str_starts_with($file['type'], 'image/'))
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="object-cover w-full h-full" loading="lazy" />
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-base-200">
                                <x-tardis::icon name="document-text" class="w-12 h-12 text-base-content/30" />
                            </div>
                        @endif

                        {{-- Gradient Overlay (images only) --}}
                        @if (!str_starts_with($file['type'] ?? '', 'image/') && $file['type'] !== 'directory')
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
                        @elseif (str_starts_with($file['type'] ?? '', 'image/'))
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/5 to-transparent"></div>
                        @endif

                        {{-- Checkbox (top-right) --}}
                        <div class="absolute top-3 right-3 z-20"
                             wire:click.stop="toggleSelect('{{ $file['relative_path'] }}')">
                            <input type="checkbox"
                                   class="checkbox checkbox-sm checkbox-primary bg-white/90 border-white/90 {{ in_array($file['relative_path'], $selectedFiles) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100' }} transition-opacity"
                                   {{ in_array($file['relative_path'], $selectedFiles) ? 'checked' : '' }} />
                        </div>

                        {{-- Filename Overlay (bottom) --}}
                        <div class="absolute bottom-0 left-0 right-0 p-3">
                            <p class="text-white text-sm font-medium truncate drop-shadow-lg">
                                {{ $file['name'] }}
                            </p>
                            @if ($file['type'] !== 'directory')
                                <p class="text-white/80 text-xs mt-0.5 drop-shadow-lg">{{ $this->formatSize($file['size']) }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- List View (Table) --}}
            <div class="card bg-base-100 border border-base-300">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th class="w-10" aria-hidden="true"></th>
                                <th scope="col">{{ __('tardis::media.name') }}</th>
                                <th scope="col">{{ __('tardis::media.type') }}</th>
                                <th scope="col">{{ __('tardis::media.size') }}</th>
                                <th scope="col">{{ __('tardis::media.modified') }}</th>
                                <th class="w-20" aria-hidden="true"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($files as $file)
                                <tr class="{{ in_array($file['relative_path'], $selectedFiles) ? 'bg-primary/10' : 'hover' }}">
                                    <td>
                                        <input type="checkbox" class="checkbox checkbox-sm"
                                               {{ in_array($file['relative_path'], $selectedFiles) ? 'checked' : '' }}
                                               wire:click="toggleSelect('{{ $file['relative_path'] }}')" />
                                    </td>
                                    <td>
                                        @if ($file['type'] === 'directory')
                                            <a wire:click="navigateTo('{{ $file['relative_path'] }}')" class="link link-hover font-medium flex items-center gap-2 cursor-pointer">
                                                <x-tardis::icon name="folder" class="w-4 h-4 text-primary" />
                                                {{ $file['name'] }}
                                            </a>
                                        @else
                                            <span class="font-medium">{{ $file['name'] }}</span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-base-content/60">{{ $file['type'] }}</td>
                                    <td class="text-sm text-base-content/60">{{ $file['type'] !== 'directory' ? $this->formatSize($file['size']) : '-' }}</td>
                                    <td class="text-sm text-base-content/60">
                                        @if ($file['last_modified'] ?? null)
                                            {{ \Carbon\Carbon::createFromTimestamp($file['last_modified'])->format('M d, Y') }}
                                        @endif
                                    </td>
                                    <td>
                                        <div class="dropdown dropdown-end">
                                            <button tabindex="0" class="btn btn-ghost btn-xs">
                                                <x-tardis::icon name="ellipsis-vertical" class="w-4 h-4" />
                                            </button>
                                            <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-40 z-10 border border-base-200">
                                                <li><a wire:click="showFileInfo('{{ $file['relative_path'] }}')">
                                                    <x-tardis::icon name="information-circle" class="w-4 h-4" /> {{ __('tardis::media.info') }}
                                                </a></li>
                                                <li><a wire:click="confirmRename('{{ $file['relative_path'] }}')">
                                                    <x-tardis::icon name="pencil-square" class="w-4 h-4" /> {{ __('tardis::media.rename') }}
                                                </a></li>
                                                <li><a wire:click="confirmDelete('{{ $file['relative_path'] }}')" class="text-error">
                                                    <x-tardis::icon name="x-mark" class="w-4 h-4" /> {{ __('tardis::media.delete') }}
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

    {{-- Right Sidebar: File Info Panel (DaisyUI card) --}}
    @if ($showInfoModal && $infoFile)
        <div class="w-80 flex-shrink-0">
            <div class="card bg-base-100 border border-base-300 sticky top-20">
                <div class="card-body p-5 gap-4">

                    {{-- Preview Image --}}
                    @if (str_starts_with($infoFile['mime_type'] ?? '', 'image/'))
                        <figure class="rounded-xl overflow-hidden aspect-video bg-base-200">
                            <img src="{{ $infoFile['url'] }}" alt="{{ $infoFile['name'] }}" class="w-full h-full object-cover" />
                        </figure>
                    @endif

                    {{-- File Name & Meta --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="card-title text-lg break-all">{{ $infoFile['name'] }}</h2>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                <div class="badge badge-ghost badge-sm uppercase">{{ $infoFile['mime_type'] ?? 'directory' }}</div>
                                <span class="text-xs text-base-content/50">{{ $this->formatSize($infoFile['size'] ?? 0) }}</span>
                            </div>
                        </div>
                        <button wire:click="closeFileInfo" class="btn btn-ghost btn-xs btn-square shrink-0">
                            <x-tardis::icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="divider my-0"></div>

                    {{-- Information Collapse --}}
                    <div class="collapse collapse-arrow bg-base-100 border-none rounded-none">
                        <input type="checkbox" wire:model.live="showInfoSection" />
                        <div class="collapse-title px-0 min-h-0 py-2 text-xs font-semibold text-base-content/50 uppercase tracking-wider">
                            {{ __('tardis::media.information') }}
                        </div>
                        <div class="collapse-content px-0 text-sm space-y-3">
                            <div class="flex justify-between">
                                <span class="text-base-content/70">{{ __('tardis::media.name') }}</span>
                                <span class="font-medium text-right ml-4 break-all">{{ $infoFile['name'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-base-content/70">{{ __('tardis::media.type') }}</span>
                                <span class="font-medium">{{ $infoFile['mime_type'] ?? 'directory' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-base-content/70">{{ __('tardis::media.size') }}</span>
                                <span class="font-medium">{{ $this->formatSize($infoFile['size'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-base-content/70">{{ __('tardis::media.path') }}</span>
                                <span class="font-medium font-mono text-xs text-right break-all max-w-[180px]">{{ $infoFile['relative_path'] }}</span>
                            </div>
                            @if ($infoFile['last_modified'] ?? null)
                                <div class="flex justify-between">
                                    <span class="text-base-content/70">{{ __('tardis::media.modified') }}</span>
                                    <span class="font-medium">{{ \Carbon\Carbon::createFromTimestamp($infoFile['last_modified'])->format('M d, Y H:i') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tags Collapse --}}
                    <div class="collapse collapse-arrow bg-base-100 border-none rounded-none">
                        <input type="checkbox" wire:model.live="showTagsSection" />
                        <div class="collapse-title px-0 min-h-0 py-2 text-xs font-semibold text-base-content/50 uppercase tracking-wider">
                            {{ __('tardis::media.tags') }}
                        </div>
                        <div class="collapse-content px-0">
                            <div class="join w-full">
                                <input type="text" placeholder="{{ __('tardis::media.new_tag') }}" class="input input-sm join-item flex-1" />
                                <button class="btn btn-primary btn-sm join-item">{{ __('tardis::media.save') }}</button>
                            </div>
                        </div>
                    </div>

                    {{-- Divider before actions --}}
                    <div class="divider my-0"></div>

                    {{-- Action Buttons (DaisyUI join) --}}
                    <div class="join join-vertical w-full">
                        @if (str_starts_with($infoFile['mime_type'] ?? '', 'image/'))
                            <a href="{{ $infoFile['url'] }}" target="_blank"
                               class="btn btn-outline btn-sm join-item justify-start gap-2">
                                <x-tardis::icon name="arrow-down-tray" class="w-4 h-4" />
                                {{ __('tardis::media.download') }}
                            </a>
                        @endif
                        <button wire:click="confirmRename('{{ $infoFile['relative_path'] }}')"
                                class="btn btn-outline btn-sm join-item justify-start gap-2">
                            <x-tardis::icon name="pencil-square" class="w-4 h-4" />
                            {{ __('tardis::media.rename') }}
                        </button>
                        <button wire:click="confirmDelete('{{ $infoFile['relative_path'] }}')"
                                class="btn btn-outline btn-error btn-sm join-item justify-start gap-2">
                            <x-tardis::icon name="x-mark" class="w-4 h-4" />
                            {{ __('tardis::media.delete') }}
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

{{-- Create Folder Modal --}}
@if ($showNewDirModal)
    <dialog class="modal modal-open">
        <div class="modal-box">
            <h3 class="font-bold text-lg">{{ __('tardis::media.create_new_folder') }}</h3>
            <form wire:submit="createDirectory" class="py-4">
                <input type="text" wire:model="newDirectoryName" class="input w-full" placeholder="{{ __('tardis::media.folder_name') }}" autofocus />
                @error('newDirectoryName')
                    <label class="label">
                        <span class="text-error">{{ $message }}</span>
                    </label>
                @enderror
            </form>
            <div class="modal-action">
                <button wire:click="$set('showNewDirModal', false)" class="btn btn-ghost">{{ __('tardis::media.cancel') }}</button>
                <button wire:click="createDirectory" class="btn btn-primary">{{ __('tardis::media.create') }}</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button wire:click="$set('showNewDirModal', false)">{{ __('tardis::media.close') }}</button>
        </form>
    </dialog>
@endif

{{-- Rename Modal --}}
@if ($showRenameModal)
    <dialog class="modal modal-open">
        <div class="modal-box">
            <h3 class="font-bold text-lg">{{ __('tardis::media.rename') }}</h3>
            <form wire:submit="renameFile" class="py-4">
                <input type="text" wire:model="renameNewName" class="input w-full" autofocus />
                @error('renameNewName')
                    <label class="label">
                        <span class="text-error">{{ $message }}</span>
                    </label>
                @enderror
            </form>
            <div class="modal-action">
                <button wire:click="$set('showRenameModal', false)" class="btn btn-ghost">{{ __('tardis::media.cancel') }}</button>
                <button wire:click="renameFile" class="btn btn-primary">{{ __('tardis::media.rename') }}</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button wire:click="$set('showRenameModal', false)">{{ __('tardis::media.close') }}</button>
        </form>
    </dialog>
@endif

{{-- Delete Confirmation Modal --}}
@if ($showDeleteModal)
    <dialog class="modal modal-open">
        <div class="modal-box">
            <h3 class="font-bold text-lg">
                {{ $deleteIsBulk ? 'Delete ' . count($selectedFiles) . ' Item(s)' : ($deleteIsDirectory ? 'Delete Folder' : 'Delete File') }}
            </h3>
            @if ($deleteIsBulk)
                <p class="py-4">{!! __('tardis::media.confirm_delete_selected', ['count' => '<strong>'.count($selectedFiles).'</strong>']) !!}</p>
            @else
                <p class="py-4">{!! __('tardis::media.confirm_delete_path', ['path' => '<strong>'.e($deletePath).'</strong>']) !!}</p>
            @endif
            <div class="modal-action">
                <button wire:click="$set('showDeleteModal', false)" class="btn btn-ghost">{{ __('tardis::media.cancel') }}</button>
                <button wire:click="deleteFile" class="btn btn-error">{{ __('tardis::media.delete') }}</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button wire:click="$set('showDeleteModal', false)">{{ __('tardis::media.close') }}</button>
        </form>
    </dialog>
@endif
</div>
