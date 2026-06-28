<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Tardis\Manager\MediaManager;

new #[Title('Media')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use WithFileUploads;

    public string $currentPath = '';

    public array $files = [];

    public array $selectedFiles = [];

    public string $viewMode = 'grid';

    public ?string $newDirectoryName = null;

    public bool $showNewDirModal = false;

    public ?string $renamePath = null;

    public string $renameNewName = '';

    public bool $showRenameModal = false;

    public ?string $deletePath = null;

    public bool $showDeleteModal = false;

    public $uploadFile = null;

    public function mount(): void
    {
        $this->loadFiles();
    }

    public function loadFiles(): void
    {
        $manager = app(MediaManager::class);
        $this->files = $manager->listFiles($this->currentPath)->toArray();
    }

    public function navigateTo(string $path): void
    {
        $this->currentPath = $path;
        $this->selectedFiles = [];
        $this->loadFiles();
    }

    public function goToParent(): void
    {
        $parent = dirname($this->currentPath);
        if ($parent === '.' || $parent === '') {
            $parent = '';
        }
        $this->navigateTo($parent);
    }

    public function uploadFile(): void
    {
        $this->validate([
            'uploadFile' => 'required|file|max:'.config('tardis-media.max_file_size', 10240),
        ]);

        $manager = app(MediaManager::class);
        $manager->upload($this->uploadFile, $this->currentPath);

        $this->uploadFile = null;
        $this->loadFiles();
        session()->flash('message', 'File uploaded successfully');
    }

    public function createDirectory(): void
    {
        $this->validate([
            'newDirectoryName' => 'required|string|max:255',
        ]);

        $manager = app(MediaManager::class);
        $manager->createDirectory($this->currentPath, $this->newDirectoryName);

        $this->newDirectoryName = null;
        $this->showNewDirModal = false;
        $this->loadFiles();
    }

    public function confirmRename(string $path): void
    {
        $this->renamePath = $path;
        $this->renameNewName = basename($path);
        $this->showRenameModal = true;
    }

    public function renameFile(): void
    {
        $this->validate([
            'renameNewName' => 'required|string|max:255',
        ]);

        $manager = app(MediaManager::class);
        $manager->rename($this->renamePath, $this->renameNewName);

        $this->renamePath = null;
        $this->showRenameModal = false;
        $this->loadFiles();
    }

    public function confirmDelete(string $path): void
    {
        $this->deletePath = $path;
        $this->showDeleteModal = true;
    }

    public function deleteFile(): void
    {
        if ($this->deletePath) {
            $manager = app(MediaManager::class);
            $manager->deleteFile($this->deletePath);

            $this->deletePath = null;
            $this->showDeleteModal = false;
            $this->loadFiles();
        }
    }

    public function toggleSelect(string $path): void
    {
        if (in_array($path, $this->selectedFiles)) {
            $this->selectedFiles = array_values(array_diff($this->selectedFiles, [$path]));
        } else {
            $this->selectedFiles[] = $path;
        }
    }

    public function selectAll(): void
    {
        $this->selectedFiles = array_map(fn ($file) => $file['relative_path'], $this->files);
    }

    public function deselectAll(): void
    {
        $this->selectedFiles = [];
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedFiles)) {
            return;
        }

        $manager = app(MediaManager::class);

        foreach ($this->selectedFiles as $path) {
            $manager->deleteFile($path);
        }

        $this->selectedFiles = [];
        $this->loadFiles();
    }

    public function downloadSelected(): void
    {
        if (empty($this->selectedFiles)) {
            return;
        }

        $manager = app(MediaManager::class);
        $zipPath = $manager->downloadZip($this->selectedFiles);

        $this->selectedFiles = [];

        $this->dispatch('download-zip', path: $zipPath);
    }

    public function getBreadcrumbs(): array
    {
        $parts = $this->currentPath ? explode('/', $this->currentPath) : [];
        $breadcrumbs = [['label' => 'Home', 'path' => '']];

        $current = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $current = $current ? $current.'/'.$part : $part;
            $breadcrumbs[] = ['label' => $part, 'path' => $current];
        }

        return $breadcrumbs;
    }
}; ?>

<div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Media</h1>
            <p class="text-base-content/60 mt-1">Manage your media files</p>
        </div>

        <div class="flex gap-2">
            @if (!empty($selectedFiles))
                <button wire:click="downloadSelected" class="btn btn-outline gap-2">
                    <x-tardis::icon name="folder" class="w-4 h-4" />
                    Download ({{ count($selectedFiles) }})
                </button>
                <button wire:click="bulkDelete" class="btn btn-error gap-2">
                    <x-tardis::icon name="x-mark" class="w-4 h-4" />
                    Delete ({{ count($selectedFiles) }})
                </button>
            @endif
            <button wire:click="$set('showNewDirModal', true)" class="btn btn-outline gap-2">
                <x-tardis::icon name="folder" class="w-4 h-4" />
                New Folder
            </button>
            <label class="btn btn-primary gap-2 cursor-pointer">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                Upload
                <input type="file" wire:model="uploadFile" wire:change="uploadFile" class="hidden" multiple />
            </label>
        </div>
    </div>

    @if (session('message'))
        <div class="alert alert-success mb-4 shadow-sm">
            <x-tardis::icon name="check-circle" class="w-5 h-5" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if ($uploadFile)
        <div class="alert alert-info mb-4 shadow-sm">
            <span class="loading loading-spinner loading-sm"></span>
            <span>Uploading {{ is_array($uploadFile) ? count($uploadFile) . ' files' : $uploadFile->getClientOriginalName() }}...</span>
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

    <!-- View Toggle & Actions -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            @if (!empty($files))
                <label class="cursor-pointer flex items-center gap-2">
                    <input type="checkbox" class="checkbox checkbox-sm" onchange="if(this.checked) @this.selectAll(); else @this.deselectAll();" />
                    <span class="text-sm">Select all</span>
                </label>
            @endif
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
                <h3 class="text-lg font-semibold mt-4">No files found</h3>
                <p class="text-base-content/60 mt-2">Upload files or create a new folder</p>
            </div>
        </div>
    @elseif ($viewMode === 'grid')
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach ($files as $file)
                <div class="card bg-base-100 shadow-sm cursor-pointer hover:shadow-md transition-shadow {{ in_array($file['relative_path'], $selectedFiles) ? 'ring-2 ring-primary' : '' }}"
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
</div>
