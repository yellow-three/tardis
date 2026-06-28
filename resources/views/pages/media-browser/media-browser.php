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

    public $newUploads = [];

    public string $mimeTypeFilter = '';

    public string $searchQuery = '';

    public ?array $infoFile = null;

    public bool $showInfoModal = false;

    public function mount(): void
    {
        $this->loadFiles();
    }

    public function loadFiles(): void
    {
        $manager = app(MediaManager::class);
        $files = $manager->listFiles($this->currentPath);

        if ($this->mimeTypeFilter) {
            $files = $files->filter(fn ($f) =>
                $f['type'] !== 'directory' && str_starts_with($f['type'], $this->mimeTypeFilter.'/')
            );
        }

        if ($this->searchQuery) {
            $files = $files->filter(fn ($f) =>
                str_contains(strtolower($f['name']), strtolower($this->searchQuery))
            );
        }

        $this->files = $files->values()->toArray();
    }

    public function updatedMimeTypeFilter(): void
    {
        $this->selectedFiles = [];
        $this->loadFiles();
    }

    public function updatedSearchQuery(): void
    {
        $this->loadFiles();
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

    public function updatedNewUploads(): void
    {
        $files = is_array($this->newUploads) ? $this->newUploads : [$this->newUploads];

        $this->validate([
            'newUploads.*' => 'required|file|max:'.config('tardis-media.max_file_size', 10240),
        ]);

        $manager = app(MediaManager::class);
        foreach ($files as $file) {
            $manager->upload($file, $this->currentPath);
        }

        $count = count($files);
        $this->newUploads = [];
        $this->loadFiles();
        session()->flash('message', $count.' file(s) uploaded successfully');
    }

    public function showFileInfo(string $path): void
    {
        $manager = app(MediaManager::class);
        $info = $manager->getFileInfo($path);

        if ($info) {
            $this->infoFile = $info;
            $this->showInfoModal = true;
        }
    }

    public function closeFileInfo(): void
    {
        $this->showInfoModal = false;
        $this->infoFile = null;
    }

    public function getMimeTypeCategories(): array
    {
        $categories = [];

        foreach ($this->files as $file) {
            if ($file['type'] === 'directory') {
                continue;
            }

            $cat = explode('/', $file['type'])[0];
            $categories[$cat] = ($categories[$cat] ?? 0) + 1;
        }

        ksort($categories);

        return $categories;
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
}; 