<?php

use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
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

    public bool $deleteIsDirectory = false;

    public bool $deleteIsBulk = false;

    public bool $showDeleteModal = false;

    public $newUploads = [];

    /**
     * Bumped after every successful upload so the file input is re-created with an
     * empty native value. Without it the browser keeps the previously chosen file
     * in the input, and re-selecting that same file fires no change event, so the
     * second upload is silently ignored.
     */
    public int $uploadInputKey = 0;

    public string $mimeTypeFilter = '';

    public string $searchQuery = '';

    public string $dateFilter = '';

    public string $sizeFilter = '';

    public string $sortBy = 'name';

    public bool $showFilters = false;

    public bool $showSort = false;

    public bool $showInfoSection = true;

    public bool $showTagsSection = false;

    public ?array $infoFile = null;

    public bool $showInfoModal = false;

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so a permission revoked after the page opened must
     * still stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_BROWSE);
    }

    public function mount(): void
    {
        $this->loadFiles();
    }

    public function loadFiles(): void
    {
        $manager = app(MediaManager::class);
        $files = $manager->listFiles($this->currentPath);

        if ($this->mimeTypeFilter) {
            $files = $files->filter(fn ($f) => $f['type'] !== 'directory' && str_starts_with($f['type'], $this->mimeTypeFilter.'/')
            );
        }

        if ($this->dateFilter) {
            $now = now();
            $files = $files->filter(function ($f) {
                if ($f['type'] === 'directory' || ! isset($f['last_modified'])) {
                    return true;
                }

                return match ($this->dateFilter) {
                    'today' => Carbon::createFromTimestamp($f['last_modified'])->isToday(),
                    'week' => Carbon::createFromTimestamp($f['last_modified'])->isThisWeek(),
                    'month' => Carbon::createFromTimestamp($f['last_modified'])->isThisMonth(),
                    'year' => Carbon::createFromTimestamp($f['last_modified'])->isThisYear(),
                    default => true,
                };
            });
        }

        if ($this->sizeFilter) {
            $files = $files->filter(function ($f) {
                if ($f['type'] === 'directory') {
                    return true;
                }
                $sizeKB = $f['size'] / 1024;

                return match ($this->sizeFilter) {
                    'small' => $sizeKB < 1024,
                    'medium' => $sizeKB >= 1024 && $sizeKB < 10240,
                    'large' => $sizeKB >= 10240 && $sizeKB < 102400,
                    'xlarge' => $sizeKB >= 102400,
                    default => true,
                };
            });
        }

        if ($this->searchQuery) {
            $files = $files->filter(fn ($f) => str_contains(strtolower($f['name']), strtolower($this->searchQuery))
            );
        }

        $files = match ($this->sortBy) {
            'name' => $files->sortBy('name'),
            'size' => $files->sortBy('size'),
            'type' => $files->sortBy('type'),
            'updated' => $files->sortBy('last_modified', SORT_REGULAR, true),
            default => $files->sortBy('name'),
        };

        $this->files = $files->values()->toArray();
    }

    public function formatSize(int $bytes): string
    {
        if ($bytes > 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes > 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function updatedMimeTypeFilter(): void
    {
        $this->selectedFiles = [];
        $this->loadFiles();
    }

    public function updatedDateFilter(): void
    {
        $this->selectedFiles = [];
        $this->loadFiles();
    }

    public function updatedSizeFilter(): void
    {
        $this->selectedFiles = [];
        $this->loadFiles();
    }

    public function updatedSortBy(): void
    {
        $this->loadFiles();
    }

    public function updatedSearchQuery(): void
    {
        $this->loadFiles();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
        $this->showSort = false;
    }

    public function toggleSort(): void
    {
        $this->showSort = ! $this->showSort;
        $this->showFilters = false;
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
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_UPLOAD);

        $files = is_array($this->newUploads) ? $this->newUploads : [$this->newUploads];

        $allowed = implode(',', (array) config('tardis-media.allowed_mimes', []));

        $this->validate([
            'newUploads.*' => array_values(array_filter([
                'required',
                'file',
                'max:'.config('tardis-media.max_file_size', 10240),
                // The disk is usually public: without an allow-list a .php or
                // .html upload is served (or executed) from the web root.
                $allowed !== '' ? 'extensions:'.$allowed : null,
                $allowed !== '' ? 'mimes:'.$allowed : null,
            ])),
        ]);

        $manager = app(MediaManager::class);
        foreach ($files as $file) {
            $manager->upload($file, $this->currentPath);
        }

        $count = count($files);
        $this->newUploads = [];
        $this->uploadInputKey++;
        $this->loadFiles();
        session()->flash('message', __('tardis::media.uploaded', ['count' => $count]));
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
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_UPLOAD);

        $this->validate([
            'newDirectoryName' => ['required', 'string', 'max:255', 'not_regex:/\.\./', 'not_regex:/[\/\\\\]/'],
        ]);

        $manager = app(MediaManager::class);
        $manager->createDirectory($this->currentPath, $this->newDirectoryName);

        $this->newDirectoryName = null;
        $this->showNewDirModal = false;
        $this->loadFiles();
    }

    public function confirmRename(string $path): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_RENAME);

        $this->renamePath = $path;
        $this->renameNewName = basename($path);
        $this->showRenameModal = true;
    }

    public function renameFile(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_RENAME);

        $this->validate([
            'renameNewName' => ['required', 'string', 'max:255', 'not_regex:/\.\./', 'not_regex:/[\/\\\\]/'],
        ]);

        $manager = app(MediaManager::class);
        $manager->rename($this->renamePath, $this->renameNewName);

        $this->renamePath = null;
        $this->showRenameModal = false;
        $this->loadFiles();
    }

    public function confirmDelete(string $path): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_DELETE);

        $this->deletePath = $path;
        $this->deleteIsDirectory = $this->isDirectory($path);
        $this->deleteIsBulk = false;
        $this->showDeleteModal = true;
    }

    public function deleteFile(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_DELETE);

        $manager = app(MediaManager::class);

        if ($this->deleteIsBulk) {
            // A bulk delete is irreversible, so the modal has to be confirmed before anything is removed.
            foreach ($this->selectedFiles as $path) {
                $this->deleteTarget($manager, $path);
            }

            $this->selectedFiles = [];
        } elseif ($this->deletePath) {
            $this->deleteTarget($manager, $this->deletePath);
        }

        $this->deletePath = null;
        $this->deleteIsDirectory = false;
        $this->deleteIsBulk = false;
        $this->showDeleteModal = false;
        $this->loadFiles();
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
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_DELETE);

        if (empty($this->selectedFiles)) {
            return;
        }

        // Deleting the whole selection in one click used to wipe it with no confirmation and no undo,
        // so this only arms the confirmation modal; deleteFile() does the actual work.
        $this->deletePath = null;
        $this->deleteIsDirectory = false;
        $this->deleteIsBulk = true;
        $this->showDeleteModal = true;
    }

    public function downloadSelected(): void
    {
        if (empty($this->selectedFiles)) {
            return;
        }

        $manager = app(MediaManager::class);

        try {
            $zipPath = $manager->downloadZip($this->selectedFiles);
        } catch (RuntimeException $e) {
            // Keep the selection so the user can adjust it instead of losing the work.
            session()->flash('error', $e->getMessage());

            return;
        }

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

    protected function isDirectory(string $relativePath): bool
    {
        foreach ($this->files as $file) {
            if (($file['relative_path'] ?? null) === $relativePath) {
                return ($file['type'] ?? null) === 'directory';
            }
        }

        return false;
    }

    protected function deleteTarget(MediaManager $manager, string $relativePath): void
    {
        if ($this->isDirectory($relativePath)) {
            $manager->deleteDirectory($relativePath);

            return;
        }

        $manager->deleteFile($relativePath);
    }
};
